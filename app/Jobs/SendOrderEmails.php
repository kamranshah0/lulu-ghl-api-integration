<?php

namespace App\Jobs;

use App\Mail\AdminOrderNotificationMail;
use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class SendOrderEmails implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public Order $order) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        $lock = Cache::lock('order-emails-'.$this->order->id, 90);
        if (! $lock->get()) {
            $this->release(30);

            return;
        }
        try {
            $order = $this->order->fresh();
            if (! $order || ! $order->lulu_job_id || in_array($order->fulfillment_status, ['failed', 'cancelled'])) {
                return;
            }
            $failure = null;
            foreach ([
                'confirmation_email' => [$order->buyer_email, OrderConfirmationMail::class],
                'admin_notification_email' => [config('services.admin.email'), AdminOrderNotificationMail::class],
            ] as $event => [$to, $mail]) {
                if ($order->events()->where('event_type', $event.'_sent')->exists()) {
                    continue;
                }
                try {
                    if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                        throw new \RuntimeException('Notification recipient is missing or invalid.');
                    }
                    Mail::to($to)->send(new $mail($order));
                    $order->logEvent($event.'_sent', 'system', ['to' => $to, 'mailer' => config('mail.default')], 'Email accepted by the configured mail transport.');
                } catch (\Throwable $e) {
                    $failure = $e;
                    $order->logEvent($event.'_failed', 'system', ['to' => $to, 'error' => $e->getMessage()], 'Email failed; notification job will retry independently of printing.');
                }
            }
            if ($failure) {
                throw $failure;
            }
        } finally {
            $lock->release();
        }
    }
}
