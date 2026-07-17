<?php

namespace Tests\Unit;

use App\Services\LuluApiService;
use Tests\TestCase;

class LuluApiServiceTest extends TestCase
{
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
