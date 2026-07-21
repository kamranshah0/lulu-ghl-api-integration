<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order notification</title>
</head>
<body style="margin:0; padding:0; background:#ffffff; color:#222222; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.5;">
    <div style="max-width:620px; margin:0 auto; padding:24px;">
        <p style="margin:0 0 16px;">Forever Wellthy team notification</p>

        <p style="margin:0 0 16px;">A book order has been received.</p>

        <p style="margin:0 0 16px;">
            Order ID: #{{ $order->id }}<br>
            GHL Order ID: {{ $order->ghl_order_id }}<br>
            Fulfillment Job ID: {{ $order->lulu_job_id ?? 'Pending' }}<br>
            Status: {{ $order->lulu_status ?? 'Unknown' }}<br>
            Quantity: {{ $order->quantity }}
        </p>

        <p style="margin:0 0 16px;">
            Print cost estimate: ${{ number_format((float) $order->print_cost_estimate, 2) }}<br>
            Shipping cost estimate: ${{ number_format((float) $order->shipping_cost_estimate, 2) }}
        </p>

        <p style="margin:0 0 16px;">
            Customer: {{ $order->buyer_name }}<br>
            Email: {{ $order->buyer_email }}<br>
            Phone: {{ $order->buyer_phone ?: 'N/A' }}
        </p>

        <p style="margin:0 0 6px;">Shipping address:</p>
        <p style="margin:0;">
            {{ $order->shipping_address1 }}<br>
            @if($order->shipping_address2)
                {{ $order->shipping_address2 }}<br>
            @endif
            {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}<br>
            {{ $order->shipping_country }}
        </p>
    </div>
</body>
</html>
