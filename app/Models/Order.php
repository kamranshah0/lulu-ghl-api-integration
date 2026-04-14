<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'ghl_contact_id',
        'ghl_order_id',
        'payment_status',
        'fulfillment_status',
        'lulu_job_id',
        'lulu_status',
        'book_sku',
        'quantity',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'shipping_address1',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_zip',
        'shipping_country',
        'amount_charged',
        'print_cost_estimate',
        'shipping_cost_estimate',
        'error_message',
        'retry_count',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload'            => 'array',
        'amount_charged'         => 'decimal:2',
        'print_cost_estimate'    => 'decimal:2',
        'shipping_cost_estimate' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('created_at', 'asc');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Log an event for this order (audit trail).
     */
    public function logEvent(
        string $eventType,
        string $source = 'system',
        array $payload = [],
        ?string $message = null
    ): OrderEvent {
        return $this->events()->create([
            'source'     => $source,
            'event_type' => $eventType,
            'payload'    => $payload,
            'message'    => $message,
        ]);
    }

    /**
     * Update fulfillment status and log the transition.
     */
    public function updateFulfillmentStatus(string $status, ?string $message = null): void
    {
        $this->update(['fulfillment_status' => $status]);
        $this->logEvent("status_changed_to_{$status}", 'system', [], $message);
    }

    /**
     * Full shipping address formatted for Lulu API.
     */
    public function getShippingAddressArray(): array
    {
        return [
            'name'         => $this->buyer_name,
            'street1'      => $this->shipping_address1,
            'street2'      => $this->shipping_address2 ?? '',
            'city'         => $this->shipping_city,
            'state_code'   => $this->shipping_state,
            'postcode'     => $this->shipping_zip,
            'country_code' => $this->shipping_country ?? 'US',
            'phone_number' => $this->buyer_phone ?? '',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeFailed($query)
    {
        return $query->where('fulfillment_status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->whereIn('fulfillment_status', ['received', 'processing']);
    }

    public function scopeSubmitted($query)
    {
        return $query->whereIn('fulfillment_status', ['submitted_to_lulu', 'print_job_created', 'in_production']);
    }
}
