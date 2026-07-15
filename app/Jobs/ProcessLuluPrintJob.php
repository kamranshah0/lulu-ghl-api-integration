<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\GhlApiService;
use App\Services\LuluApiService;
use App\Exceptions\LuluApiException;
use App\Mail\OrderConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessLuluPrintJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Maximum retry attempts if Lulu API fails.
     */
    public int $tries = 3;

    /**
     * Retry after these delays (in seconds) — exponential backoff.
     */
    public function backoff(): array
    {
        return [60, 300, 900]; // 1 min, 5 min, 15 min
    }

    /**
     * How long before the job is considered timed out (seconds).
     */
    public int $timeout = 60;

    public function __construct(protected Order $order)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | Main Handler
    |--------------------------------------------------------------------------
    */

    public function handle(LuluApiService $luluApi, GhlApiService $ghlApi): void
    {
        $order = $this->order->fresh(); // Always get latest from DB

        Log::info("ProcessLuluPrintJob: Starting for order #{$order->id}", [
            'ghl_order_id' => $order->ghl_order_id,
            'attempt'      => $this->attempts(),
        ]);

        // ── Guard: Skip if already submitted ──────────────────────────────
        if (in_array($order->fulfillment_status, ['submitted_to_lulu', 'print_job_created', 'in_production', 'shipped'])) {
            Log::info("ProcessLuluPrintJob: Order #{$order->id} already processed. Skipping.");
            return;
        }

        // ── Update status: processing ─────────────────────────────────────
        $order->updateFulfillmentStatus('processing', 'Job picked up by queue worker');

        try {
            // ── Step 1: Validate we have required fields ──────────────────
            $this->validateOrder($order);

            // ── Step 1.5: Calculate Cost ─────────────────────────────
            try {
                $costResponse = $luluApi->calculateCost(
                    shippingAddress: $order->getShippingAddressArray(),
                    quantity: $order->quantity
                );
                
                $costs = $costResponse['costs'][0] ?? $costResponse['costs'] ?? [];
                if (!empty($costs)) {
                    $order->update([
                        'print_cost_estimate'    => $costs['print_cost'] ?? 0,
                        'shipping_cost_estimate' => $costs['shipping_cost'] ?? 0,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning("ProcessLuluPrintJob: Cost calculation failed for order #{$order->id}: " . $e->getMessage());
                $order->logEvent('lulu_cost_calculation_failed', 'lulu', [
                    'error' => $e->getMessage(),
                    'shipping_address' => $order->getShippingAddressArray(),
                ], 'Lulu cost calculation failed. Print job submission will still be attempted.');
            }

            // ── Step 2: Create Lulu Print Job ─────────────────────────────
            $order->logEvent('lulu_api_call_started', 'lulu', [], 'Calling Lulu API to create print job');

            $luluResponse = $luluApi->createPrintJob(
                shippingAddress: $order->getShippingAddressArray(),
                ghlOrderId: $order->ghl_order_id,
                quantity: $order->quantity
            );

            $luluJobId = $luluResponse['id'] ?? null;

            if (! $luluJobId) {
                throw new \RuntimeException('Lulu returned no job ID. Response: ' . json_encode($luluResponse));
            }

            // ── Step 3: Store Lulu job ID & update status ─────────────────
            $order->update([
                'lulu_job_id'        => $luluJobId,
                'lulu_status'        => $luluResponse['status']['name'] ?? 'CREATED',
                'fulfillment_status' => 'print_job_created',
            ]);

            $order->logEvent('lulu_job_created', 'lulu', $luluResponse, "Lulu Job ID: {$luluJobId}");

            Log::info("ProcessLuluPrintJob: Print job created for order #{$order->id}", [
                'lulu_job_id' => $luluJobId,
            ]);

            // ── Step 4: Optional — Update GHL Contact ─────────────────────
            if ($order->ghl_contact_id) {
                try {
                    $ghlStatusUpdated = $ghlApi->updateContactFulfillmentStatus(
                        contactId: $order->ghl_contact_id,
                        luluJobId: $luluJobId,
                        status: 'Print Job Created'
                    );
                    $ghlNoteAdded = $ghlApi->addContactNote(
                        contactId: $order->ghl_contact_id,
                        noteBody: "✅ Forever Wellthy book print job submitted to Lulu. Job ID: {$luluJobId}"
                    );

                    $order->logEvent('ghl_status_synced', 'ghl', [
                        'status_updated' => $ghlStatusUpdated,
                        'note_added' => $ghlNoteAdded,
                    ], 'GHL contact was updated with Lulu print job details.');
                } catch (\Throwable $e) {
                    Log::warning("ProcessLuluPrintJob: GHL update failed for order #{$order->id}: " . $e->getMessage());
                    $order->logEvent('ghl_status_sync_failed', 'ghl', [
                        'error' => $e->getMessage(),
                    ], 'Lulu job was created, but GHL contact update failed.');
                }
            }

            // ── Step 5: Send Confirmation Email ───────────────────────────
            try {
                if (!empty($order->buyer_email)) {
                    Mail::to($order->buyer_email)->send(new OrderConfirmationMail($order));
                    Log::info("ProcessLuluPrintJob: Confirmation email sent to {$order->buyer_email}");
                }
            } catch (\Exception $e) {
                Log::warning("ProcessLuluPrintJob: Failed to send confirmation email for order #{$order->id}: " . $e->getMessage());
            }

            // ── Done ──────────────────────────────────────────────────────
            $order->update(['retry_count' => 0, 'error_message' => null]);

        } catch (LuluApiException $e) {
            $detailedError = $this->summarizeLuluApiException($e);
            $this->handleFailure($order, $e, $detailedError);
        } catch (\Throwable $e) {
            $this->handleFailure($order, $e);
        }
    }

    /**
     * Called when all retries are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        $order = $this->order->fresh();

        Log::error("ProcessLuluPrintJob: ALL retries exhausted for order #{$order->id}", [
            'error' => $exception->getMessage(),
        ]);

        $order->update([
            'fulfillment_status' => 'failed',
            'error_message'      => 'Max retries exceeded: ' . $exception->getMessage(),
        ]);

        $order->logEvent('max_retries_exceeded', 'system', [
            'error'   => $exception->getMessage(),
            'attempts' => $this->tries,
        ], 'This order needs manual review.');
    }

    private function validateOrder(Order $order): void
    {
        $missing = [];

        if (empty($order->buyer_name))      $missing[] = 'buyer_name';
        if (empty($order->buyer_email))     $missing[] = 'buyer_email';
        if (empty($order->shipping_address1)) $missing[] = 'shipping_address1';
        if (empty($order->shipping_city))   $missing[] = 'shipping_city';
        if (empty($order->shipping_zip))    $missing[] = 'shipping_zip';
        if (empty($order->shipping_country)) $missing[] = 'shipping_country';
        if (in_array(strtoupper((string) $order->shipping_country), ['US', 'CA']) && empty($order->shipping_state)) {
            $missing[] = 'shipping_state';
        }

        if (! empty($missing)) {
            throw new \InvalidArgumentException(
                'Order is missing required fields: ' . implode(', ', $missing)
            );
        }

        if (strlen((string) $order->shipping_country) !== 2) {
            throw new \InvalidArgumentException('Shipping country must be a 2-letter ISO code for Lulu.');
        }

        if (!empty($order->shipping_state) && strlen((string) $order->shipping_state) !== 2) {
            throw new \InvalidArgumentException(
                "Shipping state must be a 2-letter code for Lulu. Current value: {$order->shipping_state}"
            );
        }
    }

    private function handleFailure(Order $order, \Throwable $e, ?string $customMessage = null): void
    {
        $attempt = $this->attempts();
        $errorMessage = $customMessage ?? $e->getMessage();

        Log::warning("ProcessLuluPrintJob: Attempt {$attempt} failed for order #{$order->id}", [
            'error' => $errorMessage,
        ]);

        $order->update([
            'fulfillment_status' => 'failed',
            'retry_count'        => $attempt,
            'error_message'      => $errorMessage,
        ]);

        $order->logEvent($e instanceof LuluApiException ? 'lulu_job_failed' : 'retry_attempted', $e instanceof LuluApiException ? 'lulu' : 'system', [
            'attempt' => $attempt,
            'error'   => $errorMessage,
            'shipping_address' => $order->getShippingAddressArray(),
        ], "Attempt {$attempt} failed. Will retry if attempts remain.");

        // Re-throw so Laravel's retry mechanism kicks in
        throw $e;
    }

    private function summarizeLuluApiException(LuluApiException $e): string
    {
        $body = $e->getResponseBody();
        $decoded = is_string($body) ? json_decode($body, true) : null;
        $message = null;

        if (is_array($decoded)) {
            $message = $decoded['detail']
                ?? $decoded['message']
                ?? $decoded['error_description']
                ?? $decoded['error']
                ?? null;

            if (!$message && !empty($decoded['errors'])) {
                $message = json_encode($decoded['errors']);
            }
        }

        $summary = $message ?: trim((string) $body);
        $summary = $summary !== '' ? substr($summary, 0, 500) : 'No response body returned.';

        return "{$e->getMessage()} | Lulu response: {$summary}";
    }
}
