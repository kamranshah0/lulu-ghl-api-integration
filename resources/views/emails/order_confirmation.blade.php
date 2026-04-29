<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order Confirmation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #eaeaea;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #2c3e50;
        }
        .details {
            margin-bottom: 20px;
        }
        .details p {
            margin: 5px 0;
        }
        .footer {
            text-align: center;
            font-size: 0.9em;
            color: #777;
            margin-top: 20px;
            border-top: 1px solid #eaeaea;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Thank you for your order!</h1>
        </div>
        
        <p>Hi {{ $order->buyer_name }},</p>
        
        <p>Your order for the <strong>Forever Wellthy Book</strong> has been successfully processed and submitted for printing and fulfillment. We will notify you once it ships!</p>
        
        <div class="details">
            <h3>Order Details</h3>
            <p><strong>Order ID:</strong> #{{ $order->id }}</p>
            <p><strong>Quantity:</strong> {{ $order->quantity }}</p>
            <p><strong>Shipping Address:</strong></p>
            <p>
                {{ $order->shipping_address1 }}<br>
                @if($order->shipping_address2)
                    {{ $order->shipping_address2 }}<br>
                @endif
                {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}<br>
                {{ $order->shipping_country }}
            </p>
        </div>
        
        <p>If you have any questions, feel free to reply to this email.</p>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} Forever Wellthy. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
