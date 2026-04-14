<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\GhlApiService;
use App\Services\LuluApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
        if (in_array($order->fulfillment_status, ['submitted_to_lulu', 'print_job_created', 'shipped'])) {
            Log::info("ProcessLuluPrintJob: Order #{$order->id} already processed. Skipping.");
            return;
        }

        // ── Update status: processing ─────────────────────────────────────
        $order->updateFulfillmentStatus('processing', 'Job picked up by queue worker');

        try {
            // ── Step 1: Validate we have required fields ──────────────────
            $this->validateOrder($order);

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
                $ghlApi->updateContactFulfillmentStatus(
                    contactId: $order->ghl_contact_id,
                    luluJobId: $luluJobId,
                    status: 'Print Job Created'
                );
                $ghlApi->addContactNote(
                    contactId: $order->ghl_contact_id,
                    noteBody: "✅ Forever Wellthy book print job submitted to Lulu. Job ID: {$luluJobId}"
                );
            }

            // ── Done ──────────────────────────────────────────────────────
            $order->update(['retry_count' => 0, 'error_message' => null]);

        } catch (\Throwable $e) {
            $this->handleFailure($order, $e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Failure Handling
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Private Helpers
    |--------------------------------------------------------------------------
    */

    private function validateOrder(Order $order): void
    {
        $missing = [];

        if (empty($order->buyer_name))      $missing[] = 'buyer_name';
        if (empty($order->buyer_email))     $missing[] = 'buyer_email';
        if (empty($order->shipping_address1)) $missing[] = 'shipping_address1';
        if (empty($order->shipping_city))   $missing[] = 'shipping_city';
        if (empty($order->shipping_zip))    $missing[] = 'shipping_zip';
        if (empty($order->shipping_country)) $missing[] = 'shipping_country';

        if (! empty($missing)) {
            throw new \InvalidArgumentException(
                'Order is missing required fields: ' . implode(', ', $missing)
            );
        }
    }

    private function handleFailure(Order $order, \Throwable $e): void
    {
        $attempt = $this->attempts();

        Log::warning("ProcessLuluPrintJob: Attempt {$attempt} failed for order #{$order->id}", [
            'error' => $e->getMessage(),
        ]);

        $order->update([
            'retry_count'  => $attempt,
            'error_message' => $e->getMessage(),
        ]);

        $order->logEvent('retry_attempted', 'system', [
            'attempt' => $attempt,
            'error'   => $e->getMessage(),
        ], "Attempt {$attempt} failed. Will retry if attempts remain.");

        // Re-throw so Laravel's retry mechanism kicks in
        throw $e;
    }
}
