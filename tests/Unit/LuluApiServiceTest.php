<?php

namespace Tests\Unit;

use App\Services\LuluApiService;
use Tests\TestCase;

class LuluApiServiceTest extends TestCase
{
    public function test_long_street_is_split_at_a_word_boundary_without_losing_unit_or_existing_line_two(): void
    {
        $address = ['name' => 'Test Buyer', 'street1' => '12345 Example Road Boulevard Unit 5', 'street2' => 'Building B', 'country_code' => 'US'];
        $formatted = LuluApiService::normalizeShippingAddress($address);
        $this->assertSame('12345 Example Road Boulevard', $formatted['street1']);
        $this->assertSame('Unit 5 Building B', $formatted['street2']);
        $this->assertSame($address['street1'].' '.$address['street2'], $formatted['street1'].' '.$formatted['street2']);
        $this->assertSame('12345 Example Road Boulevard Unit 5', $address['street1']);
        $this->assertSame('Test Buyer', $formatted['name']);
        $this->assertSame($formatted, LuluApiService::normalizeShippingAddress($formatted));
    }

    public function test_short_and_exact_thirty_character_lines_are_not_moved(): void
    {
        $address = ['street1' => str_repeat('x', 30), 'street2' => str_repeat('y', 30)];
        $this->assertSame($address, LuluApiService::normalizeShippingAddress($address));
        $this->assertSame([], LuluApiService::normalizeShippingAddress([]));
    }

    public function test_line_limits_count_unicode_characters_not_bytes(): void
    {
        $address = ['street1' => '123 '.str_repeat("\u{00e9}", 25).' Unit 5', 'street2' => ''];
        $formatted = LuluApiService::normalizeShippingAddress($address);
        $this->assertSame(29, mb_strlen($formatted['street1']));
        $this->assertSame('Unit 5', $formatted['street2']);
        $this->assertSame($address['street1'], $formatted['street1'].' '.$formatted['street2']);
    }

    public function test_unrepresentable_addresses_are_rejected_without_truncation(): void
    {
        foreach ([
            ['street1' => str_repeat('x', 31)],
            ['street1' => '12345 Example Road Boulevard Unit 5', 'street2' => str_repeat('y', 30)],
            ['street1' => '123 Example Road', 'street2' => str_repeat('y', 31)],
        ] as $address) {
            try {
                LuluApiService::normalizeShippingAddress($address);
                $this->fail('Expected local address validation failure.');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Review the address before retrying', $e->getMessage());
            }
        }
    }

    public function test_it_extracts_costs_from_flat_response(): void
    {
        $costs = LuluApiService::extractCostBreakdown([
            'costs' => [
                [
                    'print_cost' => 7.25,
                    'shipping_cost' => 4.95,
                ],
            ],
        ]);

        $this->assertSame(7.25, $costs['print_cost']);
        $this->assertSame(4.95, $costs['shipping_cost']);
    }

    public function test_it_extracts_costs_from_nested_money_response(): void
    {
        $costs = LuluApiService::extractCostBreakdown([
            'line_item_costs' => [
                [
                    'print_cost' => [
                        'total_cost_excl_tax' => '8.40',
                    ],
                ],
            ],
            'shipping_cost' => [
                'total_cost_excl_tax' => '5.19',
            ],
        ]);

        $this->assertSame(8.4, $costs['print_cost']);
        $this->assertSame(5.19, $costs['shipping_cost']);
    }
}
