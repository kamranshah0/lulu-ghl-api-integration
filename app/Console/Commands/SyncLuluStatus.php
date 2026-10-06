<?php

namespace App\Console\Commands;

use App\Jobs\SendOrderEmails;
use App\Models\Order;
use App\Services\GhlApiService;
use App\Services\LuluApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncLuluStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lulu:sync-status
        {--ghl-only : Retry pending GHL updates from saved statuses without Lulu calls or app emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll Lulu API for print job status updates and sync to GHL';

    /**
     * Create a new command instance.
     */
    public function __construct(
        protected LuluApiService $luluApi,
        protected GhlApiService $ghlApi
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ghlOnly = (bool) $this->option('ghl-only');
        $this->info($ghlOnly
            ? 'Retrying pending GHL updates from saved Lulu statuses (no Lulu calls or app emails).'
            : '🔄 Starting Lulu status synchronization...');

        // Pending GHL updates include completed jobs whose last sync failed.
        $environment = config('services.lulu.use_sandbox', true) ? 'sandbox' : 'production';
        $unknown = Order::whereNull('lulu_environment')->whereNotNull('lulu_job_id')->count();
        if ($unknown) {
            $this->warn("{$unknown} historical jobs have an unverified environment. Use lulu:reconcile after checking their portal.");
        }
        $orders = Order::where('lulu_environment', $environment)->where(function ($query) use ($ghlOnly) {
            $query->where(function ($pending) {
                $pending->whereNotNull('ghl_contact_id')->where('ghl_contact_id', '!=', '')->where(function ($status) {
                    $status->whereNull('ghl_synced_status')->orWhereColumn('ghl_synced_status', '!=', 'lulu_status');
                });
            });

            if ($ghlOnly) {
                return;
            }

            $query->orWhereIn('fulfillment_status', [
                'submitted_to_lulu',
                'print_job_created',
                'in_production',
            ])->orWhere(function ($email) {
                $email->whereNotIn('fulfillment_status', ['failed', 'cancelled'])->where(function ($missing) {
                    $missing->whereDoesntHave('events', fn ($q) => $q->where('event_type', 'confirmation_email_sent'))
                        ->orWhereDoesntHave('events', fn ($q) => $q->where('event_type', 'admin_notification_email_sent'));
                });
            });
        })->whereNotNull('lulu_job_id')->where('lulu_job_id', '!=', '')->lazyById(100);
        $failures = 0;

        if ($orders->isEmpty()) {
            $this->info($ghlOnly ? 'No pending GHL updates.' : '✅ No active orders to sync.');

            return 0;
        }

        foreach ($orders as $order) {
            try {
                if ($ghlOnly) {
                    if (! $this->syncGhl($order, (string) $order->lulu_status)) {
                        $failures++;
                    }

                    continue;
                }

                $this->syncMissingCostEstimate($order);

                $statusData = $this->luluApi->getPrintJobStatus($order->lulu_job_id);
                $newStatus = $statusData['status']['name'] ?? 'UNKNOWN';

                if ($newStatus !== $order->lulu_status || $order->fulfillment_status !== Order::fulfillmentStatusFor($newStatus)) {
                    $this->updateOrderStatus($order, $newStatus, $statusData);
                }
                if ($order->ghl_contact_id && $order->ghl_synced_status !== $newStatus) {
                    if (! $this->syncGhl($order, $newStatus)) {
                        $failures++;
                    }
                }
                if (! in_array($order->fulfillment_status, ['failed', 'cancelled']) &&
                    $order->events()->whereIn('event_type', ['confirmation_email_sent', 'admin_notification_email_sent'])->distinct()->count('event_type') < 2) {
                    SendOrderEmails::dispatch($order);
                }
            } catch (\Exception $e) {
                $failures++;
                $this->error("❌ Failed to sync order #{$order->id}: {$e->getMessage()}");
                Log::warning("SyncLuluStatus: Failed to sync order #{$order->id}", [
                    'lulu_job_id' => $order->lulu_job_id,
                    'error' => $e->getMessage(),
                ]);
                $order->logEvent($ghlOnly ? 'ghl_status_sync_failed' : 'lulu_status_sync_failed', $ghlOnly ? 'ghl' : 'lulu', [
                    'lulu_job_id' => $order->lulu_job_id,
                    'error' => $e->getMessage(),
                ], $ghlOnly ? 'Failed to retry saved GHL status.' : 'Failed to fetch latest Lulu status.');
            }
        }

        $this->info('🏁 Sync complete.');

        return $failures > 0 ? 1 : 0;
    }

    protected function syncMissingCostEstimate(Order $order): void
    {
        if ($order->print_cost_estimate !== null && $order->shipping_cost_estimate !== null) {
            return;
        }

        try {
            $costResponse = $this->luluApi->calculateCost(
                shippingAddress: $order->getShippingAddressArray(),
                quantity: $order->quantity
            );
            $costs = LuluApiService::extractCostBreakdown($costResponse);

            if ($costs['print_cost'] !== null || $costs['shipping_cost'] !== null) {
                $order->update([
                    'print_cost_estimate' => $order->print_cost_estimate ?? $costs['print_cost'],
                    'shipping_cost_estimate' => $order->shipping_cost_estimate ?? $costs['shipping_cost'],
                ]);
                $order->logEvent('lulu_cost_calculated', 'lulu', [
                    'parsed_costs' => $costs,
                    'raw_response' => $costResponse,
                ], 'Missing Lulu cost estimates were backfilled during status sync.');
            }
        } catch (\Throwable $e) {
            Log::warning("SyncLuluStatus: Failed to backfill costs for order #{$order->id}", [
                'error' => $e->getMessage(),
            ]);
            $order->logEvent('lulu_cost_calculation_failed', 'lulu', [
                'error' => $e->getMessage(),
            ], 'Could not backfill Lulu cost estimates during status sync.');
        }
    }

    /**
     * Update the order in local DB and GHL.
     */
    protected function updateOrderStatus(Order $order, string $luluStatus, array $fullData): void
    {
        $this->info("⬆️ Updating #{$order->id}: {$order->lulu_status} -> {$luluStatus}");

        $oldStatus = $order->lulu_status;
        $order->lulu_status = $luluStatus;
        $order->fulfillment_status = Order::fulfillmentStatusFor($luluStatus);

        $order->error_message = in_array($luluStatus, ['REJECTED', 'ERROR']) ? $this->extractErrorMessage($fullData) : null;

        $order->save();

        // Log the change
        $order->logEvent('status_synced', 'lulu', [
            'old_lulu_status' => $oldStatus,
            'new_lulu_status' => $luluStatus,
            'full_response' => $fullData,
        ], "Status synced from Lulu: {$luluStatus}");

    }

    protected function syncGhl(Order $order, string $luluStatus): bool
    {
        if ($order->ghl_contact_id) {
            try {
                if (trim($luluStatus) === '' || $luluStatus === 'UNKNOWN') {
                    throw new \RuntimeException('No known Lulu status to sync. Fetch the current Lulu status before retrying GHL.');
                }

                $synced = $this->ghlApi->updateContactFulfillmentStatus(
                    $order->ghl_contact_id,
                    $order->lulu_job_id,
                    $luluStatus
                );
                if (! $synced) {
                    throw new \RuntimeException('GHL status update was not accepted or custom fields are not configured.');
                }
                $order->update(['ghl_synced_status' => $luluStatus]);
                $order->logEvent('ghl_status_synced', 'ghl', ['status' => $luluStatus], 'GHL fulfillment status updated.');
                $this->info("GHL synced for order #{$order->id}.");
            } catch (\Throwable $e) {
                Log::warning("SyncLuluStatus: Failed to sync GHL for order #{$order->id}", [
                    'error' => $e->getMessage(),
                ]);
                $order->logEvent('ghl_status_sync_failed', 'ghl', [
                    'error' => $e->getMessage(),
                    'lulu_status' => $luluStatus,
                ], 'GHL status sync failed; the saved Lulu job is unchanged. Check the error details.');
                $this->error("GHL sync failed for order #{$order->id}: {$e->getMessage()}");

                return false;
            }
        }

        return true;
    }

    /**
     * Try to find a human-readable error if rejected.
     */
    protected function extractErrorMessage(array $data): ?string
    {
        $rejection = $data['status']['rejection_reason'] ?? null;
        if ($rejection) {
            return is_string($rejection) ? $rejection : json_encode($rejection);
        }

        // Check line items for errors
        foreach ($data['line_items'] ?? [] as $item) {
            if (! empty($item['printable_normalization']['errors'])) {
                return json_encode($item['printable_normalization']['errors']);
            }
        }

        if (! empty($data['status']['message'])) {
            return is_string($data['status']['message']) ? $data['status']['message'] : json_encode($data['status']['message']);
        }

        return 'Order rejected by Lulu (Check dashboard for details)';
    }
}
