Forever Wellthy

Your order has been received.

Hi {{ $order->buyer_name }},

Thank you for your order. We have received your Forever Wellthy book order and it is being prepared for fulfillment.

You will receive another update when shipping information is available.

Order ID: #{{ $order->id }}
Quantity: {{ $order->quantity }}

Shipping Address:
{{ $order->shipping_address1 }}
@if($order->shipping_address2)
{{ $order->shipping_address2 }}
@endif
{{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}
{{ $order->shipping_country }}

If anything in your shipping details needs to be corrected, please reply to this email.

Thank you,
Forever Wellthy
