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
        $shipping = $order['shippingAddress']
            ?? $order['shipping_address']
            ?? $order['shipping']
            ?? $payload['shippingAddress']
            ?? $payload['shipping_address']
            ?? $payload['shipping']
            ?? $payload;
        $items    = $order['items']      ?? $order['line_items'] ?? [];

        // GHL Order ID (check all possible locations)
        $ghlOrderId = $order['id'] 
            ?? $order['order_id']
            ?? $payload['orderId'] 
            ?? $payload['order_id']
            ?? $payload['id'] 
            ?? ($items[0]['meta']['order_id'] ?? null);
        
        if (!$ghlOrderId) {
            throw new \InvalidArgumentException('Order ID is missing from payload.');
        }

        // Calculate total quantity
        $quantity = collect($items)->sum(fn ($item) => (int) ($item['quantity'] ?? $item['qty'] ?? 0)) ?: 1;

        // Normalize Country (Must be 2-char code)
        $country = self::normalizeCountry(self::firstFilled($shipping, $payload, [
            'country_code',
            'countryCode',
            'shipping_country',
            'country',
        ]) ?? 'US');

        // Normalize State (Must be 2-char code)
        $state = self::normalizeState(self::firstFilled($shipping, $payload, [
            'state_code',
            'stateCode',
            'province_code',
            'provinceCode',
            'shipping_state',
            'state',
            'province',
            'region',
        ]), $country);

        return new self(
            ghlOrderId: (string) $ghlOrderId,
            buyerName: self::buildBuyerName($customer, $payload),
            buyerEmail: self::firstFilled($customer, $payload, ['email', 'buyer_email', 'customer_email']) ?? 'no-email@example.com',
            buyerPhone: self::firstFilled($customer, $payload, ['phone', 'phone_number', 'buyer_phone']),
            address1: self::firstFilled($shipping, $payload, ['address1', 'street1', 'line1', 'address', 'shipping_address1']) ?? '',
            address2: self::firstFilled($shipping, $payload, ['address2', 'street2', 'line2', 'shipping_address2']),
            city: self::firstFilled($shipping, $payload, ['city', 'shipping_city']) ?? '',
            state: $state,
            zip: self::firstFilled($shipping, $payload, [
                'zip',
                'postalCode',
                'postal_code',
                'postcode',
                'shipping_zip',
            ]) ?? '',
            country: $country,
            quantity: (int) $quantity,
            amountCharged: isset($order['totalAmount']) ? (float) $order['totalAmount'] : (isset($payload['amount']) ? (float) $payload['amount'] : null),
            contactId: $payload['contactId'] ?? $payload['contact_id'] ?? $customer['contactId'] ?? null,
            rawPayload: $payload
        );
    }

    private static function buildBuyerName(array $customer, array $payload): string
    {
        $fullName = self::firstFilled($customer, $payload, ['name', 'full_name', 'buyer_name']);

        if ($fullName) {
            return $fullName;
        }

        return trim(
            (string) self::firstFilled($customer, $payload, ['firstName', 'first_name', 'first']) . ' ' .
            (string) self::firstFilled($customer, $payload, ['lastName', 'last_name', 'last'])
        ) ?: 'Unknown Customer';
    }

    private static function firstFilled(array $primary, array $fallback, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $primary[$key] ?? $fallback[$key] ?? null;

            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private static function normalizeCountry(string $country): string
    {
        $value = strtolower(trim($country));

        $countries = [
            'usa' => 'US',
            'u.s.' => 'US',
            'u.s.a.' => 'US',
            'united states' => 'US',
            'united states of america' => 'US',
            'canada' => 'CA',
            'united kingdom' => 'GB',
            'great britain' => 'GB',
        ];

        return $countries[$value] ?? strtoupper(substr(trim($country), 0, 2));
    }

    public static function normalizeState(?string $state, string $country = 'US'): string
    {
        $state = trim((string) $state);

        if ($state === '') {
            return '';
        }

        if (strlen($state) === 2) {
            return strtoupper($state);
        }

        $key = strtolower(str_replace(['.', '_'], ['', ' '], $state));
        $key = preg_replace('/\s+/', ' ', $key);

        if (strtoupper($country) === 'US') {
            return self::usStateMap()[$key] ?? strtoupper($state);
        }

        return strtoupper($state);
    }

    private static function usStateMap(): array
    {
        return [
            'alabama' => 'AL',
            'alaska' => 'AK',
            'arizona' => 'AZ',
            'arkansas' => 'AR',
            'california' => 'CA',
            'colorado' => 'CO',
            'connecticut' => 'CT',
            'delaware' => 'DE',
            'district of columbia' => 'DC',
            'florida' => 'FL',
            'georgia' => 'GA',
            'hawaii' => 'HI',
            'idaho' => 'ID',
            'illinois' => 'IL',
            'indiana' => 'IN',
            'iowa' => 'IA',
            'kansas' => 'KS',
            'kentucky' => 'KY',
            'louisiana' => 'LA',
            'maine' => 'ME',
            'maryland' => 'MD',
            'massachusetts' => 'MA',
            'michigan' => 'MI',
            'minnesota' => 'MN',
            'mississippi' => 'MS',
            'missouri' => 'MO',
            'missori' => 'MO',
            'missroii' => 'MO',
            'missourii' => 'MO',
            'montana' => 'MT',
            'nebraska' => 'NE',
            'nevada' => 'NV',
            'new hampshire' => 'NH',
            'new jersey' => 'NJ',
            'new mexico' => 'NM',
            'new york' => 'NY',
            'north carolina' => 'NC',
            'north dakota' => 'ND',
            'ohio' => 'OH',
            'oklahoma' => 'OK',
            'oregon' => 'OR',
            'pennsylvania' => 'PA',
            'rhode island' => 'RI',
            'south carolina' => 'SC',
            'south dakota' => 'SD',
            'tennessee' => 'TN',
            'texas' => 'TX',
            'utah' => 'UT',
            'vermont' => 'VT',
            'virginia' => 'VA',
            'washington' => 'WA',
            'west virginia' => 'WV',
            'wisconsin' => 'WI',
            'wyoming' => 'WY',
        ];
    }
}
