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
    ) {}

    /**
     * Factory method to create DTO from GHL webhook payload.
     */
    public static function fromGhlPayload(array $data): self
    {
        // GHL often wraps everything in a 'payload' key
        $payload = $data['payload'] ?? $data;
        if (! is_array($payload)) {
            throw new \InvalidArgumentException('Payload must be an object.');
        }

        $order = $payload['order'] ?? $payload;
        $customer = $order['customer'] ?? $payload;
        $shipping = $order['shippingAddress']
            ?? $order['shipping_address']
            ?? $order['shipping']
            ?? $payload['shippingAddress']
            ?? $payload['shipping_address']
            ?? $payload['shipping']
            ?? $payload;
        $items = $order['items'] ?? $order['line_items'] ?? [];
        foreach ([$order, $customer, $shipping, $items] as $part) {
            if (! is_array($part)) {
                throw new \InvalidArgumentException('Order, customer, shipping and items must contain structured data.');
            }
        }
        $payment = strtolower(self::firstFilled($order, $payload, ['payment_status', 'paymentStatus']) ?? 'paid');
        if (in_array($payment, ['unpaid', 'pending', 'refunded', 'failed', 'cancelled', 'canceled'], true)) {
            throw new \InvalidArgumentException('Order is not paid and cannot be sent for printing.');
        }

        // GHL Order ID (check all possible locations)
        $ghlOrderId = $order['order_id']
            ?? $payload['orderId']
            ?? $payload['order_id']
            ?? $order['id']
            ?? $payload['id']
            ?? ($items[0]['meta']['order_id'] ?? null);

        if (! is_scalar($ghlOrderId) || trim((string) $ghlOrderId) === '' || strlen((string) $ghlOrderId) > 255) {
            throw new \InvalidArgumentException('Order ID is missing from payload.');
        }

        // Calculate total quantity
        $quantity = 0;
        foreach ($items ?: [['quantity' => $order['quantity'] ?? $order['qty'] ?? 1]] as $item) {
            $qty = is_array($item) ? ($item['quantity'] ?? $item['qty'] ?? 1) : null;
            if (filter_var($qty, FILTER_VALIDATE_INT) === false || (int) $qty < 1) {
                throw new \InvalidArgumentException('Each item quantity must be a positive integer.');
            }
            $quantity += (int) $qty;
        }
        if ($quantity > 10000) {
            throw new \InvalidArgumentException('Order quantity exceeds the supported limit.');
        }
        $amount = self::firstFilled($order, $payload, ['totalAmount', 'total_amount', 'amount']);
        if ($amount !== null && (! is_numeric($amount) || (float) $amount < 0 || (float) $amount > 99999999.99)) {
            throw new \InvalidArgumentException('Order amount must be a non-negative numeric value.');
        }

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
            buyerEmail: self::firstFilled($customer, $payload, ['email', 'buyer_email', 'customer_email']) ?? '',
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
            amountCharged: $amount === null ? null : (float) $amount,
            contactId: self::firstFilled($order, $payload, ['contactId', 'contact_id']) ?? self::firstFilled($customer, [], ['contactId', 'contact_id']),
            rawPayload: $data
        );
    }

    private static function buildBuyerName(array $customer, array $payload): string
    {
        $fullName = self::firstFilled($customer, $payload, ['name', 'full_name', 'buyer_name']);

        if ($fullName) {
            return $fullName;
        }

        return trim(
            (string) self::firstFilled($customer, $payload, ['firstName', 'first_name', 'first']).' '.
            (string) self::firstFilled($customer, $payload, ['lastName', 'last_name', 'last'])
        );
    }

    private static function firstFilled(array $primary, array $fallback, array $keys): ?string
    {
        foreach ($keys as $key) {
            foreach ([$primary[$key] ?? null, $fallback[$key] ?? null] as $value) {
                if (is_scalar($value) && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
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

        return $countries[$value] ?? strtoupper(trim($country));
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

        if (strtoupper($country) === 'CA') {
            return [
                'alberta' => 'AB', 'british columbia' => 'BC', 'manitoba' => 'MB',
                'new brunswick' => 'NB', 'newfoundland and labrador' => 'NL',
                'nova scotia' => 'NS', 'northwest territories' => 'NT', 'nunavut' => 'NU',
                'ontario' => 'ON', 'prince edward island' => 'PE', 'quebec' => 'QC',
                'saskatchewan' => 'SK', 'yukon' => 'YT',
            ][$key] ?? strtoupper($state);
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
