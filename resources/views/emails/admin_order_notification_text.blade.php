Forever Wellthy

A book order has been received.

Order ID: #{{ $order->id }}
GHL Order ID: {{ $order->ghl_order_id }}
Fulfillment Job ID: {{ $order->lulu_job_id ?? 'Pending' }}
Status: {{ $order->lulu_status ?? 'Unknown' }}
Quantity: {{ $order->quantity }}

Cost Estimate:
Print: ${{ number_format((float) $order->print_cost_estimate, 2) }}
Shipping: ${{ number_format((float) $order->shipping_cost_estimate, 2) }}

Customer:
Name: {{ $order->buyer_name }}
Email: {{ $order->buyer_email }}
Phone: {{ $order->buyer_phone ?: 'N/A' }}

Shipping Address:
{{ $order->shipping_address1 }}
@if($order->shipping_address2)
{{ $order->shipping_address2 }}
@endif
{{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}
{{ $order->shipping_country }}
