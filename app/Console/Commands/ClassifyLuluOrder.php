<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClassifyLuluOrder extends Command
{
    protected $signature = 'lulu:classify {order : Local historical order ID} {environment : sandbox or production}';

    protected $description = 'Classify a historical unsubmitted order after operator review; does not queue or print it';

    public function handle(): int
    {
        $environment = $this->argument('environment');
        if (! in_array($environment, ['sandbox', 'production'], true)) {
            $this->error('Environment must be sandbox or production.');

            return 1;
        }
        $order = Order::findOrFail($this->argument('order'));
        $lock = Cache::lock('lulu-order-'.$order->id, 300);
        if (! $lock->get()) {
            $this->error('Order is processing.');

            return 1;
        }
        try {
            $order->refresh();
            if ($order->lulu_environment || $order->lulu_job_id || $order->submission_started_at) {
                $this->error('Order already has an environment or submission evidence. Use reconciliation for existing Lulu jobs.');

                return 1;
            }
            $order->update(['lulu_environment' => $environment]);
            $order->logEvent('environment_classified', 'admin', ['environment' => $environment], 'Historical order environment classified by operator. No print job created.');
            $this->info('Environment recorded. Order has not been queued.');

            return 0;
        } finally {
            $lock->release();
        }
    }
}
