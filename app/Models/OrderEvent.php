<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'source',
        'event_type',
        'payload',
        'message',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Human-readable label for event types.
     */
    public function getEventLabelAttribute(): string
    {
        return match ($this->event_type) {
            'webhook_received'        => '📥 Webhook Received from GHL',
            'duplicate_detected'      => '⚠️ Duplicate Webhook Ignored',
            'job_dispatched'          => '🔄 Job Dispatched to Queue',
            'lulu_token_fetched'      => '🔑 Lulu Auth Token Fetched',
            'lulu_job_created'        => '✅ Print Job Created in Lulu',
            'lulu_job_failed'         => '❌ Lulu API Error',
            'lulu_api_call_started'   => '📤 Lulu API Call Started',
            'lulu_cost_calculation_failed' => '⚠️ Lulu Cost Calculation Failed',
            'lulu_status_sync_failed' => '⚠️ Lulu Status Sync Failed',
            'ghl_status_synced'       => '✅ GHL Status Synced',
            'ghl_status_sync_failed'  => '⚠️ GHL Status Sync Failed',
            'status_synced'           => '🔄 Lulu Status Synced',
            'retry_attempted'         => '🔁 Retry Attempt',
            'max_retries_exceeded'    => '💀 Max Retries Exceeded',
            'admin_manual_retry'      => '👤 Admin Triggered Retry',
            'status_changed_to_received'        => '📋 Status → Received',
            'status_changed_to_processing'      => '⚙️ Status → Processing',
            'status_changed_to_submitted_to_lulu' => '📤 Status → Submitted to Lulu',
            'status_changed_to_print_job_created' => '🖨️ Status → Print Job Created',
            'status_changed_to_in_production'   => '🏭 Status → In Production',
            'status_changed_to_shipped'         => '🚚 Status → Shipped',
            'status_changed_to_failed'          => '🔴 Status → Failed',
            default                             => ucwords(str_replace('_', ' ', $this->event_type)),
        };
    }

    /**
     * Badge color for UI display.
     */
    public function getSourceBadgeColorAttribute(): string
    {
        return match ($this->source) {
            'ghl'    => 'blue',
            'lulu'   => 'purple',
            'admin'  => 'orange',
            default  => 'gray',
        };
    }
}
