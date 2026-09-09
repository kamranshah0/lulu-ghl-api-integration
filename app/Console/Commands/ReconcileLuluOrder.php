<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\LuluApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ReconcileLuluOrder extends Command
{
    protected $signature = 'lulu:reconcile {order : Local order ID} {job : Existing Lulu job ID}';

    protected $description = 'Verify an existing Lulu job and attach its status/environment locally; never creates a print job';

    public function handle(LuluApiService $lulu): int
    {
        $order = Order::findOrFail($this->argument('order'));
        $lock = Cache::lock('lulu-order-'.$order->id, 300);
        if (! $lock->get()) {
            $this->error('Order is currently processing. Try after the worker completes.');

            return 1;
        }
        try {
            $order->refresh();
            if (($order->lulu_environment && $order->lulu_environment !== $lulu->environment()) ||
                ($order->lulu_job_id && (string) $order->lulu_job_id !== (string) $this->argument('job'))) {
                throw new \RuntimeException('Existing job/environment differs. Refusing to overwrite it.');
            }
            $data = $lulu->getPrintJobStatus($this->argument('job'));
            if ((string) ($data['external_id'] ?? '') !== $order->ghl_order_id ||
                (string) ($data['id'] ?? '') !== (string) $this->argument('job')) {
                throw new \RuntimeException('Lulu job does not match this GHL order.');
            }
            $status = $data['status']['name'] ?? 'UNKNOWN';
            $order->update([
                'lulu_job_id' => $data['id'], 'lulu_environment' => $lulu->environment(),
                'lulu_status' => $status, 'fulfillment_status' => Order::fulfillmentStatusFor($status),
                'error_message' => in_array($status, ['ERROR', 'REJECTED']) ? 'Lulu reports '.$status.'. Inspect job details in the developer portal.' : null,
            ]);
            $order->logEvent('lulu_job_reconciled', 'admin', ['lulu_job_id' => $data['id'], 'environment' => $lulu->environment(), 'status' => $status], 'Verified existing job; no new print order was created.');
            $this->info('Existing Lulu job verified and linked.');

            return 0;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        } finally {
            $lock->release();
        }
    }
}
