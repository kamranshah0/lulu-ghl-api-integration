<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Forever Wellthy Book Order</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f4f7fb;
            color: #1f2933;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
        }
        .wrap {
            width: 100%;
            padding: 28px 12px;
        }
        .card {
            max-width: 680px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #dbe5f1;
            border-radius: 10px;
            overflow: hidden;
        }
        .hero {
            background: #10243e;
            color: #ffffff;
            padding: 28px;
        }
        .eyebrow {
            margin: 0 0 8px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        h1 {
            margin: 0;
            color: #ffffff;
            font-size: 26px;
            line-height: 1.25;
        }
        .content {
            padding: 28px;
        }
        h2 {
            margin: 24px 0 12px;
            color: #10243e;
            font-size: 18px;
        }
        .grid {
            margin: 18px 0 4px;
            border: 1px solid #dbe5f1;
            border-radius: 8px;
            overflow: hidden;
        }
        .row {
            padding: 13px 16px;
            border-bottom: 1px solid #dbe5f1;
        }
        .row:last-child {
            border-bottom: 0;
        }
        .label {
            color: #5b6b7c;
            display: block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .value {
            color: #10243e;
            display: block;
            font-weight: 700;
            margin-top: 2px;
        }
        .muted {
            color: #66788a;
        }
        .footer {
            padding: 18px 28px;
            background: #f8fbff;
            color: #66788a;
            font-size: 12px;
            text-align: center;
        }
        @media (max-width: 540px) {
            .hero,
            .content,
            .footer {
                padding-left: 20px;
                padding-right: 20px;
            }
            h1 {
                font-size: 23px;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="hero">
                <p class="eyebrow">Forever Wellthy</p>
                <h1>A new book order is ready for fulfillment.</h1>
            </div>

            <div class="content">
                <p class="muted">This notification is for the Forever Wellthy team.</p>

                <h2>Order Summary</h2>
                <div class="grid">
                    <div class="row">
                        <span class="label">Order ID</span>
                        <span class="value">#{{ $order->id }}</span>
                    </div>
                    <div class="row">
                        <span class="label">GHL Order ID</span>
                        <span class="value">{{ $order->ghl_order_id }}</span>
                    </div>
                    <div class="row">
                        <span class="label">Fulfillment Job ID</span>
                        <span class="value">{{ $order->lulu_job_id ?? 'Pending' }}</span>
                    </div>
                    <div class="row">
                        <span class="label">Status</span>
                        <span class="value">{{ $order->lulu_status ?? 'Unknown' }}</span>
                    </div>
                    <div class="row">
                        <span class="label">Quantity</span>
                        <span class="value">{{ $order->quantity }}</span>
                    </div>
                </div>

                <h2>Cost Estimate</h2>
                <div class="grid">
                    <div class="row">
                        <span class="label">Print</span>
                        <span class="value">${{ number_format((float) $order->print_cost_estimate, 2) }}</span>
                    </div>
                    <div class="row">
                        <span class="label">Shipping</span>
                        <span class="value">${{ number_format((float) $order->shipping_cost_estimate, 2) }}</span>
                    </div>
                </div>

                <h2>Customer</h2>
                <div class="grid">
                    <div class="row">
                        <span class="label">Name</span>
                        <span class="value">{{ $order->buyer_name }}</span>
                    </div>
                    <div class="row">
                        <span class="label">Email</span>
                        <span class="value">{{ $order->buyer_email }}</span>
                    </div>
                    <div class="row">
                        <span class="label">Phone</span>
                        <span class="value">{{ $order->buyer_phone ?: 'N/A' }}</span>
                    </div>
                    <div class="row">
                        <span class="label">Shipping Address</span>
                        <span class="value">
                            {{ $order->shipping_address1 }}<br>
                            @if($order->shipping_address2)
                                {{ $order->shipping_address2 }}<br>
                            @endif
                            {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}<br>
                            {{ $order->shipping_country }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="footer">
                &copy; {{ date('Y') }} Forever Wellthy. All rights reserved.
            </div>
        </div>
    </div>
</body>
</html>
