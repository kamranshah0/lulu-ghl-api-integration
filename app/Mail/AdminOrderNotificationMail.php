<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class AdminOrderNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            replyTo: [
                new Address(config('mail.from.address'), config('mail.from.name')),
            ],
            subject: "Team notification: order #{$this->order->id} received",
            using: [
                function (Email $message): void {
                    $message->sender(config('mail.from.address'));
                    $message->returnPath(config('mail.from.address'));
                },
            ],
        );
    }

    public function headers(): Headers
    {
        $domain = config('mail.mailers.smtp.local_domain') ?: 'app.forever-wellthy.com';
        $suffix = str_replace('.', '', uniqid('', true));

        return new Headers(
            messageId: "forever-wellthy-order-{$this->order->id}-admin-{$suffix}@{$domain}",
            text: [
                'Auto-Submitted' => 'auto-generated',
                'Precedence' => 'transactional',
                'X-Auto-Response-Suppress' => 'All',
                'X-Entity-Ref-ID' => "forever-wellthy-order-{$this->order->id}-admin",
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_order_notification',
            text: 'emails.admin_order_notification_text',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
