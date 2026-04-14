<?php

namespace App\DTOs;

class OrderData
{
    public function __construct(
        public string $ghlOrderId,
        public string $buyerName,
        public string $buyerEmail,
        public ?string $buyerPhone,
        public string $address1,
        public ?string $address2,
        public string $city,
        public string $state,
        public string $zip,
        public string $country,
        public int $quantity,
        public ?float $amountCharged,
        public ?string $contactId,
        public array $rawPayload
    ) {
    }

    /**
     * Factory method to create DTO from GHL webhook payload.
     */
    public static function fromGhlPayload(array $data): self
    {
        // GHL often wraps everything in a 'payload' key
        $payload = $data['payload'] ?? $data;

        $order    = $payload['order']    ?? $payload;
        $customer = $order['customer']   ?? $payload;
        $shipping = $order['shippingAddress'] ?? $order['shipping'] ?? $payload;
        $items    = $order['items']      ?? $order['line_items'] ?? [];

        // GHL Order ID (check all possible locations)
        $ghlOrderId = $order['id'] 
            ?? $order['order_id']
            ?? $payload['orderId'] 
            ?? $payload['id'] 
            ?? ($items[0]['meta']['order_id'] ?? null);
        
        if (!$ghlOrderId) {
            throw new \InvalidArgumentException('Order ID is missing from payload.');
        }

        // Calculate total quantity
        $quantity = collect($items)->sum('quantity') ?: 1;

        return new self(
            ghlOrderId: (string) $ghlOrderId,
            buyerName: trim(
                ($customer['firstName'] ?? $payload['first_name'] ?? '') . ' ' .
                ($customer['lastName'] ?? $payload['last_name'] ?? '')
            ) ?: 'Unknown Customer',
            buyerEmail: $customer['email'] ?? $payload['email'] ?? 'no-email@example.com',
            buyerPhone: $customer['phone'] ?? $payload['phone'] ?? null,
            address1: $shipping['address1'] ?? $shipping['street1'] ?? $payload['address1'] ?? '',
            address2: $shipping['address2'] ?? $shipping['street2'] ?? null,
            city: $shipping['city'] ?? $payload['city'] ?? '',
            state: $shipping['state'] ?? $payload['state'] ?? '',
            zip: $shipping['zip'] ?? $shipping['postalCode'] ?? $payload['shipping_zip'] ?? $payload['postal_code'] ?? $payload['zip'] ?? '',
            country: $shipping['country'] ?? $payload['country'] ?? 'US',
            quantity: (int) $quantity,
            amountCharged: isset($order['totalAmount']) ? (float) $order['totalAmount'] : (isset($payload['amount']) ? (float) $payload['amount'] : null),
            contactId: $payload['contactId'] ?? null,
            rawPayload: $payload
        );
    }
}
