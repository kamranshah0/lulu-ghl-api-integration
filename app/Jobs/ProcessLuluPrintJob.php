<?php

namespace App\Jobs;

use App\Exceptions\LuluApiException;
use App\Models\Order;
use App\Services\GhlApiService;
use App\Services\LuluApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessLuluPrintJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

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
    public int $timeout = 240;

    public function __construct(protected Order $order) {}

    /*
    |--------------------------------------------------------------------------
    | Main Handler
    |--------------------------------------------------------------------------
    */

    public function handle(LuluApiService $luluApi, GhlApiService $ghlApi): void
    {
        $lock = Cache::lock('lulu-order-'.$this->order->id, 300);
        if (! $lock->get()) {
            $this->release(30);

            return;
        }
        try {
            $this->process($luluApi, $ghlApi);
        } finally {
            $lock->release();
        }
    }

    private function process(LuluApiService $luluApi, GhlApiService $ghlApi): void
    {
        $order = $this->order->fresh(); // Always get latest from DB
        if (! $order) {
            return;
        }

        Log::info("ProcessLuluPrintJob: Starting for order #{$order->id}", [
            'ghl_order_id' => $order->ghl_order_id,
            'attempt' => $this->attempts(),
        ]);

        // ── Guard: Skip if already submitted ──────────────────────────────
        if ($order->lulu_job_id || in_array($order->fulfillment_status, ['submitted_to_lulu', 'print_job_created', 'in_production', 'shipped', 'cancelled'])) {
            Log::info("ProcessLuluPrintJob: Order #{$order->id} already processed. Skipping.");

            return;
        }

        if ($order->submission_started_at) {
            $order->update(['fulfillment_status' => 'failed', 'error_message' => 'Submission outcome is uncertain. Check Lulu by GHL order ID before creating another job.']);
            $order->logEvent('submission_reconciliation_required', 'system', [], 'Automatic submission blocked because a previous print request may have been accepted.');

            return;
        }

        // ── Update status: processing ─────────────────────────────────────
        $order->updateFulfillmentStatus('processing', 'Job picked up by queue worker');

        try {
            // ── Step 1: Validate we have required fields ──────────────────
            $this->validateOrder($order);
            $environment = config('services.lulu.use_sandbox', true) ? 'sandbox' : 'production';
            if (! $order->lulu_environment) {
                throw new \InvalidArgumentException('Historical order environment is unverified. Classify it with lulu:classify before retrying.');
            }
            if ($order->lulu_environment && $order->lulu_environment !== $environment) {
                throw new \InvalidArgumentException('Order belongs to a different Lulu environment. Do not replay sandbox orders in production.');
            }
            $order->update(['lulu_environment' => $environment]);

            // ── Step 1.5: Calculate Cost ─────────────────────────────
            try {
                $costResponse = $luluApi->calculateCost(
                    shippingAddress: $order->getShippingAddressArray(),
                    quantity: $order->quantity
                );

                $costs = LuluApiService::extractCostBreakdown($costResponse);
                if ($costs['print_cost'] !== null || $costs['shipping_cost'] !== null) {
                    $order->update([
                        'print_cost_estimate' => $costs['print_cost'],
                        'shipping_cost_estimate' => $costs['shipping_cost'],
                    ]);
                    $order->logEvent('lulu_cost_calculated', 'lulu', [
                        'parsed_costs' => $costs,
                        'raw_response' => $costResponse,
                    ], 'Lulu print and shipping cost estimates were stored.');
                } else {
                    $order->logEvent('lulu_cost_calculation_failed', 'lulu', [
                        'raw_response' => $costResponse,
                    ], 'Lulu cost response did not contain recognizable print/shipping cost fields.');
                }
            } catch (\Exception $e) {
                Log::warning("ProcessLuluPrintJob: Cost calculation failed for order #{$order->id}: ".$e->getMessage());
                $order->logEvent('lulu_cost_calculation_failed', 'lulu', [
                    'error' => $e->getMessage(),
                    'shipping_address' => $order->getShippingAddressArray(),
                ], 'Lulu cost calculation failed. Print job submission will still be attempted.');
            }

            // ── Step 2: Create Lulu Print Job ─────────────────────────────
            $order->logEvent('lulu_api_call_started', 'lulu', [], 'Calling Lulu API to create print job');

            // Authenticate before recording the durable submission marker. Auth cannot create a book.
            $luluApi->validatePrintConfiguration();
            $luluApi->getAccessToken();
            $order->update(['submission_started_at' => now()]);

            $luluResponse = $luluApi->createPrintJob(
                shippingAddress: $order->getShippingAddressArray(),
                ghlOrderId: $order->ghl_order_id,
                quantity: $order->quantity
            );

            $luluJobId = $luluResponse['id'] ?? null;

            if (! $luluJobId) {
                throw new \RuntimeException('Lulu returned no job ID. Response: '.json_encode($luluResponse));
            }

            // ── Step 3: Store Lulu job ID & update status ─────────────────
            $order->update([
                'lulu_job_id' => $luluJobId,
                'lulu_status' => $luluResponse['status']['name'] ?? 'CREATED',
                'fulfillment_status' => Order::fulfillmentStatusFor($luluResponse['status']['name'] ?? 'CREATED'),
            ]);

            $order->logEvent('lulu_job_created', 'lulu', $luluResponse, "Lulu Job ID: {$luluJobId}");

            Log::info("ProcessLuluPrintJob: Print job created for order #{$order->id}", [
                'lulu_job_id' => $luluJobId,
            ]);

            // ── Step 4: Optional — Update GHL Contact ─────────────────────
            if ($order->ghl_contact_id) {
                $ghlStatusUpdated = false;
                $ghlNoteAdded = false;
                $statusError = null;
                $noteError = null;
                try {
                    $ghlStatusUpdated = $ghlApi->updateContactFulfillmentStatus(
                        contactId: $order->ghl_contact_id,
                        luluJobId: $luluJobId,
                        status: $order->lulu_status
                    );
                    if ($ghlStatusUpdated) {
                        $order->update(['ghl_synced_status' => $order->lulu_status]);
                    } else {
                        $statusError = 'GHL did not confirm the status update.';
                    }
                } catch (\Throwable $e) {
                    $statusError = $e->getMessage();
                }
                // A status-field failure must not suppress the independent contact note.
                try {
                    $ghlNoteAdded = $ghlApi->addContactNote(
                        contactId: $order->ghl_contact_id,
                        noteBody: "✅ Forever Wellthy book print job submitted to Lulu. Job ID: {$luluJobId}"
                    );
                    if (! $ghlNoteAdded) {
                        $noteError = 'GHL did not confirm the contact note.';
                    }
                } catch (\Throwable $e) {
                    $noteError = $e->getMessage();
                }
                $order->logEvent($ghlStatusUpdated ? 'ghl_status_synced' : 'ghl_status_sync_failed', 'ghl', [
                    'status_updated' => $ghlStatusUpdated,
                    'error' => $statusError,
                ], $ghlStatusUpdated ? 'GHL fulfillment fields updated.' : 'Lulu job was created, but GHL fulfillment fields were not updated.');
                $order->logEvent($ghlNoteAdded ? 'ghl_note_added' : 'ghl_note_failed', 'ghl', [
                    'note_added' => $ghlNoteAdded,
                    'error' => $noteError,
                ], $ghlNoteAdded ? 'GHL contact note added.' : 'GHL contact note failed; this does not block order emails.');
            }

            // ── Done ──────────────────────────────────────────────────────
            $order->update(['retry_count' => 0, 'error_message' => $order->fulfillment_status === 'failed' ? 'Lulu rejected the job. Inspect the Lulu job event for details.' : null]);
            SendOrderEmails::dispatch($order);

        } catch (LuluApiException $e) {
            if ($e->getStatusCode() >= 400 && $e->getStatusCode() < 500 && $e->getStatusCode() !== 408) {
                $order->update(['submission_started_at' => null]);
            }
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
        if (! $order || $order->lulu_job_id) {
            return;
        }

        Log::error("ProcessLuluPrintJob: ALL retries exhausted for order #{$order->id}", [
            'error' => $exception->getMessage(),
        ]);

        $order->update([
            'fulfillment_status' => 'failed',
            'error_message' => 'Max retries exceeded: '.$exception->getMessage(),
        ]);

        $order->logEvent('max_retries_exceeded', 'system', [
            'error' => $exception->getMessage(),
            'attempts' => $this->tries,
        ], 'This order needs manual review.');
    }

    private function validateOrder(Order $order): void
    {
        $missing = [];

        if (empty($order->buyer_name)) {
            $missing[] = 'buyer_name';
        }
        if (empty($order->buyer_email)) {
            $missing[] = 'buyer_email';
        }
        if (empty($order->shipping_address1)) {
            $missing[] = 'shipping_address1';
        }
        if (empty($order->shipping_city)) {
            $missing[] = 'shipping_city';
        }
        if (empty($order->shipping_zip)) {
            $missing[] = 'shipping_zip';
        }
        if (empty($order->shipping_country)) {
            $missing[] = 'shipping_country';
        }
        if (in_array(strtoupper((string) $order->shipping_country), ['US', 'CA']) && empty($order->shipping_state)) {
            $missing[] = 'shipping_state';
        }

        if (! empty($missing)) {
            throw new \InvalidArgumentException(
                'Order is missing required fields: '.implode(', ', $missing)
            );
        }

        if (strlen((string) $order->shipping_country) !== 2) {
            throw new \InvalidArgumentException('Shipping country must be a 2-letter ISO code for Lulu.');
        }
        if (! filter_var($order->buyer_email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Buyer email must be valid.');
        }
        if ($order->quantity < 1) {
            throw new \InvalidArgumentException('Order quantity must be positive.');
        }

        if (in_array($order->shipping_country, ['US', 'CA']) && strlen((string) $order->shipping_state) !== 2) {
            throw new \InvalidArgumentException(
                "Shipping state must be a 2-letter code for Lulu. Current value: {$order->shipping_state}"
            );
        }
    }

    private function handleFailure(Order $order, \Throwable $e, ?string $customMessage = null): void
    {
        $attempt = $this->attempts();
        $errorMessage = $customMessage ?? $e->getMessage();
        if ($order->fresh()->lulu_job_id) {
            $order->logEvent('post_submission_failed', 'system', [], 'Print job exists; follow-up failed. Do not resubmit the print job.');

            return;
        }

        Log::warning("ProcessLuluPrintJob: Attempt {$attempt} failed for order #{$order->id}", [
            'error' => $errorMessage,
        ]);

        $order->update([
            'fulfillment_status' => 'failed',
            'retry_count' => $attempt,
            'error_message' => $errorMessage,
        ]);

        $order->logEvent($e instanceof LuluApiException ? 'lulu_job_failed' : 'retry_attempted', $e instanceof LuluApiException ? 'lulu' : 'system', [
            'attempt' => $attempt,
            'error' => $errorMessage,
            'http_status' => $e instanceof LuluApiException ? $e->getStatusCode() : null,
            'environment' => $order->lulu_environment,
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

            if (! $message && ! empty($decoded['errors'])) {
                $message = json_encode($decoded['errors']);
            }
        }

        $summary = is_array($message) ? json_encode($message) : ($message ?: trim((string) $body));
        $summary = $summary !== '' ? substr($summary, 0, 500) : 'No response body returned.';

        return "{$e->getMessage()} | Lulu response: {$summary}";
    }
}
