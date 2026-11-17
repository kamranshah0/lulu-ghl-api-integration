<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'services.ghl.physical_product_ids' => [], 'services.ghl.non_print_product_ids' => [],
            'services.ghl.physical_product_titles' => [], 'services.ghl.non_print_product_titles' => [],
        ]);
    }
    //
}
