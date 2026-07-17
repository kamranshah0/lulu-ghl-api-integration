<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Lulu Print Job Created</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 640px;
            margin: 20px auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 1px solid #eaeaea;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #2c3e50;
            font-size: 22px;
            margin: 0;
        }
        .details {
            margin-bottom: 20px;
        }
        .details p {
            margin: 6px 0;
        }
        .footer {
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
            <h1>New Lulu Print Job Created</h1>
        </div>

        <div class="details">
            <p><strong>Middleware Order ID:</strong> #{{ $order->id }}</p>
            <p><strong>GHL Order ID:</strong> {{ $order->ghl_order_id }}</p>
            <p><strong>Lulu Job ID:</strong> {{ $order->lulu_job_id ?? 'Pending' }}</p>
            <p><strong>Lulu Status:</strong> {{ $order->lulu_status ?? 'Unknown' }}</p>
            <p><strong>Quantity:</strong> {{ $order->quantity }}</p>
            <p><strong>Print Cost Estimate:</strong> ${{ number_format((float) $order->print_cost_estimate, 2) }}</p>
            <p><strong>Shipping Cost Estimate:</strong> ${{ number_format((float) $order->shipping_cost_estimate, 2) }}</p>
        </div>

        <div class="details">
            <h3>Customer</h3>
            <p><strong>Name:</strong> {{ $order->buyer_name }}</p>
            <p><strong>Email:</strong> {{ $order->buyer_email }}</p>
            <p><strong>Phone:</strong> {{ $order->buyer_phone ?: 'N/A' }}</p>
        </div>

        <div class="details">
            <h3>Shipping Address</h3>
            <p>
                {{ $order->shipping_address1 }}<br>
                @if($order->shipping_address2)
                    {{ $order->shipping_address2 }}<br>
                @endif
                {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}<br>
                {{ $order->shipping_country }}
            </p>
        </div>

        <div class="footer">
            <p>This notification was generated after the order was submitted to Lulu.</p>
        </div>
    </div>
</body>
</html>
