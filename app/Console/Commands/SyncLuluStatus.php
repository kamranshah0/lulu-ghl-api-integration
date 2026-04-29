<?php

namespace App\Console\Commands;

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
    protected $signature = 'lulu:sync-status';

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
        $this->info('🔄 Starting Lulu status synchronization...');

        // Query orders that are submitted but not finalized (Shipped/Rejected)
        $orders = Order::whereIn('fulfillment_status', [
            'submitted_to_lulu',
            'print_job_created',
            'in_production'
        ])->whereNotNull('lulu_job_id')->get();

        if ($orders->isEmpty()) {
            $this->info('✅ No active orders to sync.');
            return 0;
        }

        $this->info("📦 Found {$orders->count()} orders to check.");

        foreach ($orders as $order) {
            try {
                $statusData = $this->luluApi->getPrintJobStatus($order->lulu_job_id);
                $newStatus = $statusData['status']['name'] ?? 'UNKNOWN';

                if ($newStatus !== $order->lulu_status) {
                    $this->updateOrderStatus($order, $newStatus, $statusData);
                }
            } catch (\Exception $e) {
                $this->error("❌ Failed to sync order #{$order->id}: {$e->getMessage()}");
            }
        }

        $this->info('🏁 Sync complete.');
        return 0;
    }

    /**
     * Update the order in local DB and GHL.
     */
    protected function updateOrderStatus(Order $order, string $luluStatus, array $fullData): void
    {
        $this->info("⬆️ Updating #{$order->id}: {$order->lulu_status} -> {$luluStatus}");

        $oldStatus = $order->lulu_status;
        $order->lulu_status = $luluStatus;

        // Map Lulu status to our internal fulfillment_status
        // Possible Lulu statuses: CREATED, REJECTED, IN_PRODUCTION, SHIPPED, CANCELED
        switch ($luluStatus) {
            case 'SHIPPED':
                $order->fulfillment_status = 'shipped';
                break;
            case 'REJECTED':
                $order->fulfillment_status = 'failed';
                $order->error_message = $this->extractErrorMessage($fullData);
                break;
            case 'IN_PRODUCTION':
                $order->fulfillment_status = 'in_production';
                break;
            case 'CANCELED':
                $order->fulfillment_status = 'cancelled';
                break;
        }

        $order->save();

        // Log the change
        $order->logEvent('status_synced', 'lulu_api', [
            'old_lulu_status' => $oldStatus,
            'new_lulu_status' => $luluStatus,
            'full_response'   => $fullData
        ], "Status synced from Lulu: {$luluStatus}");

        // Sync to GHL
        if ($order->ghl_contact_id) {
            $this->ghlApi->updateContactFulfillmentStatus(
                $order->ghl_contact_id,
                $order->lulu_job_id,
                $luluStatus
            );
        }
    }

    /**
     * Try to find a human-readable error if rejected.
     */
    protected function extractErrorMessage(array $data): ?string
    {
        $rejection = $data['status']['rejection_reason'] ?? null;
        if ($rejection) {
            return $rejection;
        }

        // Check line items for errors
        foreach ($data['line_items'] ?? [] as $item) {
            if (!empty($item['printable_normalization']['errors'])) {
                return json_encode($item['printable_normalization']['errors']);
            }
        }

        return 'Order rejected by Lulu (Check dashboard for details)';
    }
}
