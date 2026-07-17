<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Forever Wellthy Order Is Confirmed</title>
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
            max-width: 640px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #dbe5f1;
            border-radius: 10px;
            overflow: hidden;
        }
        .hero {
            background: #2f75b5;
            color: #ffffff;
            padding: 30px 28px;
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
            font-size: 28px;
            line-height: 1.2;
            color: #ffffff;
        }
        .content {
            padding: 28px;
        }
        h2 {
            margin: 0 0 12px;
            color: #10243e;
            font-size: 20px;
            line-height: 1.3;
        }
        p {
            margin: 0 0 16px;
        }
        .panel {
            margin: 22px 0;
            padding: 18px;
            background: #f8fbff;
            border: 1px solid #dbe5f1;
            border-radius: 8px;
        }
        .row {
            margin: 0 0 8px;
        }
        .label {
            color: #5b6b7c;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .value {
            color: #10243e;
            font-weight: 700;
        }
        .button {
            display: inline-block;
            margin-top: 4px;
            padding: 13px 20px;
            background: #2f75b5;
            color: #ffffff !important;
            border-radius: 6px;
            font-weight: 700;
            text-decoration: none;
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
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="hero">
                <p class="eyebrow">Order Confirmed</p>
                <h1>Your copy of Forever Wellthy is on its way.</h1>
            </div>

            <div class="content">
                <p>Hi {{ $order->buyer_name }},</p>

                <p>Thank you for your purchase. We are getting your Forever Wellthy book ready for printing and shipping.</p>

                <h2>What happens next</h2>
                <p>A confirmation has been created for your order. Once the book moves through fulfillment and shipping, you will receive the next update.</p>

                <div class="panel">
                    <div class="row">
                        <div class="label">Order ID</div>
                        <div class="value">#{{ $order->id }}</div>
                    </div>
                    <div class="row">
                        <div class="label">Quantity</div>
                        <div class="value">{{ $order->quantity }}</div>
                    </div>
                    <div class="row">
                        <div class="label">Shipping Address</div>
                        <div class="value">
                            {{ $order->shipping_address1 }}<br>
                            @if($order->shipping_address2)
                                {{ $order->shipping_address2 }}<br>
                            @endif
                            {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}<br>
                            {{ $order->shipping_country }}
                        </div>
                    </div>
                </div>

                <h2>Keep the momentum going</h2>
                <p>You did not just buy a book. You took a step toward building the capacity to show up stronger in work, life, and health.</p>
                <p>
                    <a class="button" href="https://book.forever-wellthy.com/thanks-page">Continue With Forever Wellthy</a>
                </p>

                <p>If anything in your shipping details looks wrong, reply to this email and we will help.</p>
            </div>

            <div class="footer">
                &copy; {{ date('Y') }} Forever Wellthy. All rights reserved.
            </div>
        </div>
    </div>
</body>
</html>
