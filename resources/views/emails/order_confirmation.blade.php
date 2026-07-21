<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order received</title>
</head>
<body style="margin:0; padding:0; background:#ffffff; color:#222222; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.5;">
    <div style="max-width:600px; margin:0 auto; padding:24px;">
        <p style="margin:0 0 16px;">Forever Wellthy</p>

        <p style="margin:0 0 16px;">Hi {{ $order->buyer_name }},</p>

        <p style="margin:0 0 16px;">Your Forever Wellthy book order has been received and is being prepared for fulfillment.</p>

        <p style="margin:0 0 16px;">Order ID: #{{ $order->id }}<br>
        Quantity: {{ $order->quantity }}</p>

        <p style="margin:0 0 6px;">Shipping address:</p>
        <p style="margin:0 0 16px;">
            {{ $order->shipping_address1 }}<br>
            @if($order->shipping_address2)
                {{ $order->shipping_address2 }}<br>
            @endif
            {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}<br>
            {{ $order->shipping_country }}
        </p>

        <p style="margin:0 0 16px;">You will receive another update when shipping information is available.</p>

        <p style="margin:0;">Thank you,<br>Forever Wellthy</p>
    </div>
</body>
</html>
