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

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            replyTo: [
                new Address(config('mail.from.address'), config('mail.from.name')),
            ],
            subject: "Order #{$this->order->id} received - Forever Wellthy",
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
            messageId: "forever-wellthy-order-{$this->order->id}-customer-{$suffix}@{$domain}",
            text: [
                'Auto-Submitted' => 'auto-generated',
                'Precedence' => 'transactional',
                'X-Auto-Response-Suppress' => 'All',
                'X-Entity-Ref-ID' => "forever-wellthy-order-{$this->order->id}-customer",
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.order_confirmation',
            text: 'emails.order_confirmation_text',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
