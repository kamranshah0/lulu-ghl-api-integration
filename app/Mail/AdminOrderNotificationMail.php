<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminOrderNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Lulu Print Job Created - Order #{$this->order->id}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_order_notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
