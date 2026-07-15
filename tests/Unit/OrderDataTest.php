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
}
