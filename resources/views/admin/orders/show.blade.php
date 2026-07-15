@extends('layouts.admin')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem;">
    <div style="display: flex; align-items: center; gap: 1.5rem;">
        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline" style="padding: 0.625rem; border-radius: 0.75rem;">
            <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
        </a>
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.25rem;">Order #{{ $order->ghl_order_id }}</h1>
            <p style="color: var(--text-muted); font-weight: 500;">
                Internal ID: <span style="font-family: monospace; font-weight: 700;">{{ $order->id }}</span> • 
                Received {{ $order->created_at->format('M d, Y \a\t H:i') }}
            </p>
        </div>
    </div>
    <div style="display: flex; gap: 1rem; align-items: center;">
        @if($order->fulfillment_status == 'failed')
        <form action="{{ route('admin.orders.retry', $order) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary" style="padding: 0.625rem 1.5rem;">Retry Fulfillment</button>
        </form>
        @endif
        <div class="badge 
            @if($order->fulfillment_status == 'print_job_created') badge-success 
            @elseif($order->fulfillment_status == 'failed') badge-danger 
            @elseif($order->fulfillment_status == 'received' || $order->fulfillment_status == 'processing') badge-warning
            @else badge-info @endif" style="font-size: 0.8125rem; padding: 0.5rem 1.25rem; border-radius: 0.625rem;">
            {{ str_replace('_', ' ', $order->fulfillment_status) }}
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 3rem;">
    <!-- Customer & Shipping Info -->
    <div class="card" style="padding: 2.5rem;">
        <h3 style="font-size: 1.25rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;">
            <i data-lucide="user" style="width: 24px; height: 24px; color: var(--primary);"></i> Customer Information
        </h3>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <div>
                <label style="display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Full Name</label>
                <p style="font-weight: 700; color: var(--text-main); font-size: 1rem;">{{ $order->buyer_name }}</p>
            </div>
            <div>
                <label style="display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Email Address</label>
                <p style="font-weight: 600; color: var(--primary);">{{ $order->buyer_email }}</p>
            </div>
            <div>
                <label style="display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Phone Number</label>
                <p style="font-weight: 600;">{{ $order->buyer_phone ?? 'N/A' }}</p>
            </div>
            <div>
                <label style="display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Amount Paid</label>
                <p style="font-weight: 800; color: var(--text-main); font-size: 1.125rem;">${{ number_format($order->amount_charged, 2) }}</p>
            </div>
        </div>

        <div style="margin: 2.5rem 0; border-top: 1.5px dashed var(--border);"></div>

        <h3 style="font-size: 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i data-lucide="map-pin" style="width: 24px; height: 24px; color: var(--primary);"></i> Shipping Destination
        </h3>
        <div style="background: #fafafa; padding: 1.5rem; border-radius: 1rem; border: 1px solid var(--border);">
            <p style="font-weight: 600; line-height: 1.8; color: var(--text-main); font-size: 0.9375rem;">
                {{ $order->shipping_address1 }}<br>
                @if($order->shipping_address2) {{ $order->shipping_address2 }}<br> @endif
                {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}<br>
                <span style="font-weight: 800; letter-spacing: 0.02em;">{{ strtoupper($order->shipping_country) }}</span>
            </p>
        </div>
    </div>

    <!-- Lulu Fulfillment Info -->
    <div class="card" style="padding: 2.5rem;">
        <h3 style="font-size: 1.25rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;">
            <i data-lucide="printer" style="width: 24px; height: 24px; color: var(--primary);"></i> Lulu Fulfillment Details
        </h3>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <div>
                <label style="display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Lulu Job ID</label>
                @if($order->lulu_job_id)
                    <code style="background: var(--sidebar-bg); color: #fff; padding: 0.4rem 0.75rem; border-radius: 0.5rem; font-family: monospace; font-weight: 700; font-size: 0.8125rem;">{{ $order->lulu_job_id }}</code>
                @else
                    <p style="color: var(--text-muted); font-style: italic;">Not yet created</p>
                @endif
            </div>
            <div>
                <label style="display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">API Status</label>
                <p style="font-weight: 700; color: var(--text-main);">{{ strtoupper($order->lulu_status ?? 'N/A') }}</p>
            </div>
            <div>
                <label style="display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Product SKU</label>
                <p style="font-size: 0.8125rem; font-family: monospace; font-weight: 600; color: var(--text-muted);">{{ $order->book_sku }}</p>
            </div>
            <div>
                <label style="display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Batch Quantity</label>
                <p style="font-weight: 800; font-size: 1.125rem;">{{ $order->quantity }} x Unit</p>
            </div>
            <div>
                <label style="display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Print Cost Est.</label>
                <p style="font-weight: 700; color: var(--text-main);">${{ number_format((float) $order->print_cost_estimate, 2) }}</p>
            </div>
            <div>
                <label style="display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Shipping Cost Est.</label>
                <p style="font-weight: 700; color: var(--text-main);">${{ number_format((float) $order->shipping_cost_estimate, 2) }}</p>
            </div>
        </div>

        <details style="margin-top: 2rem; border: 1px solid var(--border); border-radius: 0.75rem; overflow: hidden;">
            <summary style="font-size: 0.75rem; color: var(--text-muted); cursor: pointer; font-weight: 800; padding: 0.75rem 1rem; background: #fafafa; display: flex; align-items: center; gap: 0.5rem; user-select: none;">
                <i data-lucide="chevron-right" style="width: 14px; height: 14px;"></i> Lulu Shipping Payload
            </summary>
            <div style="padding: 1rem; background: #1e293b; color: #e2e8f0; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; overflow-x: auto;">
                <pre style="line-height: 1.6;">{{ json_encode($order->getShippingAddressArray(), JSON_PRETTY_PRINT) }}</pre>
            </div>
        </details>

        @if($order->error_message)
        <div style="margin-top: 2.5rem; padding: 1.5rem; background: #fff1f2; border: 1px solid #fda4af; border-radius: 1rem; display: flex; gap: 1rem; align-items: flex-start;">
            <div style="color: var(--danger);"><i data-lucide="alert-octagon" style="width: 24px; height: 24px;"></i></div>
            <div>
                <label style="display: block; font-size: 0.7rem; font-weight: 800; color: #9f1239; margin-bottom: 0.35rem; text-transform: uppercase;">Pipeline Error</label>
                <p style="font-size: 0.875rem; color: #9f1239; font-weight: 600; line-height: 1.5;">{{ $order->error_message }}</p>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Event Timeline -->
<div class="card" style="padding: 2.5rem;">
    <h3 style="font-size: 1.25rem; margin-bottom: 2.5rem; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 0.75rem;">
        <i data-lucide="history" style="width: 24px; height: 24px; color: var(--primary);"></i> Audit Log & Activity Timeline
    </h3>
    
    <div style="position: relative; padding-left: 2.5rem; margin-left: 0.75rem;">
        <!-- Vertical Line -->
        <div style="position: absolute; left: 0; top: 0; bottom: 0; width: 2.5px; background: var(--border); border-radius: 2px;"></div>
        
        @foreach($order->events as $event)
        <div style="margin-bottom: 2.5rem; position: relative;">
            <!-- Dot -->
            <div style="position: absolute; left: -2.85rem; top: 0.35rem; width: 14px; height: 14px; border-radius: 100%; background: #fff; border: 3px solid var(--primary); box-shadow: 0 0 0 4px #fff;"></div>
            
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.875rem;">
                    <span style="font-weight: 800; font-size: 1rem; color: var(--text-main);">{{ $event->event_label }}</span>
                    <span class="badge" style="background: 
                        @if($event->source == 'ghl') #dbeafe; color: #1e40af; 
                        @elseif($event->source == 'lulu') #f3e8ff; color: #6b21a8; 
                        @elseif($event->source == 'admin') #ffedd5; color: #9a3412; 
                        @else #f1f5f9; color: #475569; @endif
                        font-size: 0.65rem; border-radius: 0.375rem; padding: 0.25rem 0.625rem;">
                        {{ strtoupper($event->source) }}
                    </span>
                </div>
                <span style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted);">{{ $event->created_at->format('M d, Y • H:i:s') }}</span>
            </div>
            
            @if($event->message)
            <p style="font-size: 0.9375rem; color: var(--text-muted); margin-top: 0.25rem; max-width: 800px; line-height: 1.6;">{{ $event->message }}</p>
            @endif
            
            @if(!empty($event->payload))
            <details style="margin-top: 1rem; border: 1px solid var(--border); border-radius: 0.75rem; overflow: hidden;">
                <summary style="font-size: 0.75rem; color: var(--text-muted); cursor: pointer; font-weight: 800; padding: 0.75rem 1rem; background: #fafafa; display: flex; align-items: center; gap: 0.5rem; user-select: none;">
                    <i data-lucide="chevron-right" style="width: 14px; height: 14px;"></i> Object Payload
                </summary>
                <div style="padding: 1rem; background: #1e293b; color: #e2e8f0; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; overflow-x: auto;">
                    <pre style="line-height: 1.6;">{{ json_encode($event->payload, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </details>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endsection
