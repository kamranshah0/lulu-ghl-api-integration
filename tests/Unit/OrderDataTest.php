<?php

namespace Tests\Unit;

use App\DTOs\OrderData;
use Tests\TestCase;

class OrderDataTest extends TestCase
{
    public function test_it_normalizes_missouri_to_mo_for_lulu(): void
    {
        $order = OrderData::fromGhlPayload([
            'payload' => [
                'order' => [
                    'id' => 'GHL-1001',
                    'customer' => [
                        'firstName' => 'Jane',
                        'lastName' => 'Buyer',
                        'email' => 'jane@example.com',
                    ],
                    'shippingAddress' => [
                        'address1' => '123 Market St',
                        'city' => 'Saint Louis',
                        'state' => 'Missouri',
                        'zip' => '63101',
                        'country' => 'United States',
                    ],
                    'items' => [
                        ['quantity' => 1],
                    ],
                    'totalAmount' => 97,
                ],
                'contactId' => 'contact-123',
            ],
        ]);

        $this->assertSame('MO', $order->state);
        $this->assertSame('US', $order->country);
        $this->assertSame('Saint Louis', $order->city);
        $this->assertSame('contact-123', $order->contactId);
    }

    public function test_it_reads_common_shipping_aliases(): void
    {
        $order = OrderData::fromGhlPayload([
            'order_id' => 'GHL-1002',
            'first_name' => 'Alex',
            'last_name' => 'Rivera',
            'email' => 'alex@example.com',
            'shipping_address' => [
                'street1' => '456 Oak Ave',
                'street2' => 'Suite 2',
                'city' => 'Austin',
                'state_code' => 'tx',
                'postal_code' => '78701',
                'country_code' => 'us',
            ],
            'line_items' => [
                ['qty' => 2],
            ],
        ]);

        $this->assertSame('GHL-1002', $order->ghlOrderId);
        $this->assertSame('Alex Rivera', $order->buyerName);
        $this->assertSame('456 Oak Ave', $order->address1);
        $this->assertSame('Suite 2', $order->address2);
        $this->assertSame('TX', $order->state);
        $this->assertSame('78701', $order->zip);
        $this->assertSame(2, $order->quantity);
    }

    public function test_it_handles_common_missouri_misspelling(): void
    {
        $this->assertSame('MO', OrderData::normalizeState('Missroii', 'US'));
    }

    public function test_blank_nested_value_falls_back_and_preserves_future_fields(): void
    {
        $data = ['payload' => ['order_id' => 'ORDER-1', 'email' => 'buyer@example.com', 'quantity' => 2,
            'order' => ['customer' => ['email' => ''], 'quantity' => 2], 'personalization' => ['name' => 'Future Name']]];
        $order = OrderData::fromGhlPayload($data);
        $this->assertSame('buyer@example.com', $order->buyerEmail);
        $this->assertSame(2, $order->quantity);
        $this->assertSame($data, $order->rawPayload);
    }

    public function test_invalid_quantity_is_not_silently_changed_to_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OrderData::fromGhlPayload(['order_id' => 'ORDER-1', 'items' => [['quantity' => 0]]]);
    }

    public function test_unknown_country_is_not_truncated_into_another_country(): void
    {
        $order = OrderData::fromGhlPayload(['order_id' => 'ORDER-1', 'country' => 'Australia']);
        $this->assertSame('AUSTRALIA', $order->country);
        $this->assertSame('ON', OrderData::normalizeState('Ontario', 'CA'));
        $this->assertSame('MI', OrderData::normalizeState('MI', 'US'));
    }
}
