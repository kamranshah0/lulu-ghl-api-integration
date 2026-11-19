<?php

namespace Tests\Feature;

use App\DTOs\OrderData;
use App\Jobs\ProcessLuluPrintJob;
use App\Jobs\SendOrderEmails;
use App\Models\Order;
use App\Services\GhlApiService;
use App\Services\LuluApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class PhysicalBookSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ghl.physical_product_ids' => ['physical-book'],
            'services.ghl.non_print_product_ids' => ['digital-bundle'],
            'services.ghl.webhook_secret' => 'test-secret',
            'services.lulu.use_sandbox' => true,
        ]);
        $this->withHeader('X-GHL-Secret', 'test-secret');
    }

    private function payload(int $books = 1): array
    {
        return ['order_id' => 'CART-1', 'name' => 'Test Buyer', 'email' => 'buyer@example.com',
            'address1' => '123 Example Street', 'city' => 'Columbia', 'state' => 'MO', 'zip' => '65201', 'country' => 'US',
            'amount' => 14.96, 'items' => [
                ['productId' => 'physical-book', 'quantity' => $books],
                ['productId' => 'digital-bundle', 'quantity' => 1],
            ]];
    }

    public function test_bump_is_excluded_and_full_cart_amount_and_payload_are_preserved(): void
    {
        Queue::fake();
        $payload = $this->payload();
        $this->postJson('/api/webhooks/ghl', $payload)->assertStatus(202);
        $order = Order::firstOrFail();
        $this->assertSame(1, $order->quantity);
        $this->assertSame('14.96', $order->amount_charged);
        $this->assertSame($payload, $order->raw_payload);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'event_type' => 'print_items_selected']);
        $this->postJson('/api/webhooks/ghl', $payload)->assertJsonPath('status', 'duplicate_ignored');
        $this->assertDatabaseCount('orders', 1);
        Queue::assertPushed(ProcessLuluPrintJob::class, 1);
    }

    public function test_real_multiple_books_are_counted_in_any_cart_order(): void
    {
        $data = $this->payload(2);
        $data['items'] = array_reverse($data['items']);
        $dto = OrderData::fromGhlPayload($data);
        $this->assertSame(2, $dto->quantity);
        $this->assertSame(1, $dto->excludedItemCount);
        array_shift($data['items']);
        $this->assertSame(2, OrderData::fromGhlPayload($data)->quantity);
    }

    public function test_product_aliases_and_outer_line_items_with_nested_order_are_supported(): void
    {
        $data = ['payload' => ['order' => ['id' => 'CART-1'], 'line_items' => [
            ['product' => ['_id' => 'physical-book'], 'qty' => 3],
            ['product' => 'digital-bundle', 'qty' => 1],
            ['product_id' => 'physical-book', 'quantity' => 2],
        ]]];
        $this->assertSame(5, OrderData::fromGhlPayload($data)->quantity);
    }

    public function test_digital_only_event_is_acknowledged_without_persisting_or_queueing_a_print(): void
    {
        Queue::fake();
        $payload = $this->payload();
        array_shift($payload['items']);
        $this->postJson('/api/webhooks/ghl', $payload)->assertOk()->assertJsonPath('status', 'ignored_non_print_order');
        $this->assertDatabaseCount('orders', 0);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
        // Ignoring a digital event must not consume the real purchase's unique ID.
        $this->postJson('/api/webhooks/ghl', $this->payload())->assertStatus(202);
        Queue::assertPushed(ProcessLuluPrintJob::class, 1);
    }

    public function test_unknown_or_missing_product_identity_is_not_printed(): void
    {
        Queue::fake();
        foreach ([['productId' => 'unknown-upsell', 'quantity' => 1], ['quantity' => 1], ['id' => 'physical-book', 'quantity' => 1]] as $unmapped) {
            $data = $this->payload();
            $data['items'][1] = $unmapped;
            $this->postJson('/api/webhooks/ghl', $data)->assertStatus(422);
        }
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unconfigured_mixed_cart_is_rejected_instead_of_summing_quantities(): void
    {
        config(['services.ghl.physical_product_ids' => [], 'services.ghl.non_print_product_ids' => []]);
        Queue::fake();
        $this->postJson('/api/webhooks/ghl', $this->payload())->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
        Queue::assertNothingPushed();
    }

    public function test_price_and_title_are_never_used_to_classify_an_item(): void
    {
        $data = $this->payload();
        $data['items'] = [['productId' => 'physical-book', 'quantity' => 2, 'name' => 'Digital Bundle', 'amount' => 4.99]];
        $this->assertSame(2, OrderData::fromGhlPayload($data)->quantity);
    }

    public function test_overlapping_product_configuration_is_rejected(): void
    {
        config(['services.ghl.non_print_product_ids' => ['physical-book']]);
        $this->expectExceptionMessage('must not overlap');
        OrderData::fromGhlPayload($this->payload());
    }

    public function test_invalid_physical_quantity_cannot_default_to_one(): void
    {
        $data = $this->payload();
        $data['items'][0]['quantity'] = 0;
        $this->expectException(\InvalidArgumentException::class);
        OrderData::fromGhlPayload($data);
    }

    public function test_no_items_explicit_book_quantity_contract_remains_supported(): void
    {
        $data = $this->payload();
        unset($data['items']);
        $data['quantity'] = 2;
        $this->assertSame(2, OrderData::fromGhlPayload($data)->quantity);
    }

    private function titlePayload(int $books = 1): array
    {
        config([
            'services.ghl.physical_product_titles' => ['Free Book + Domestic Shipping (US)'],
            'services.ghl.non_print_product_titles' => ['Instant Digital Copy'],
        ]);
        $data = $this->payload();
        $data['items'] = [
            ['title' => 'Free Book + Domestic Shipping (US)', 'quantity' => $books],
            ['title' => 'Instant Digital Copy', 'quantity' => 1],
        ];

        return $data;
    }

    public function test_explicit_title_aliases_select_only_book_quantities_without_product_ids(): void
    {
        foreach ([1, 2] as $books) {
            $data = $this->titlePayload($books);
            $data['items'] = array_reverse($data['items']);
            $dto = OrderData::fromGhlPayload($data);
            $this->assertSame($books, $dto->quantity);
            $this->assertSame(1, $dto->excludedItemCount);
            array_shift($data['items']);
            $this->assertSame($books, OrderData::fromGhlPayload($data)->quantity);
        }
    }

    public function test_title_aliases_support_nested_names_case_and_whitespace_only(): void
    {
        $data = $this->titlePayload(2);
        $data['items'] = [
            ['product' => ['name' => "  FREE BOOK + Domestic   Shipping (US)\t"], 'qty' => 2],
            ['name' => 'instant digital copy', 'qty' => 1],
        ];
        $this->assertSame(2, OrderData::fromGhlPayload($data)->quantity);
    }

    public function test_title_aliases_never_override_known_or_unknown_product_ids(): void
    {
        Queue::fake();
        $data = $this->titlePayload();
        $data['items'] = [['productId' => 'physical-book', 'title' => 'Instant Digital Copy', 'quantity' => 2]];
        $this->assertSame(2, OrderData::fromGhlPayload($data)->quantity);
        $data['items'] = [
            ['productId' => 'digital-bundle', 'title' => 'Free Book + Domestic Shipping (US)', 'quantity' => 1],
        ];
        $this->postJson('/api/webhooks/ghl', $data)->assertOk()->assertJsonPath('status', 'ignored_non_print_order');
        $data['items'][0]['productId'] = 'unknown-upsell';
        $this->postJson('/api/webhooks/ghl', $data)->assertStatus(422);
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unknown_titles_do_not_use_substring_price_or_legacy_fallback(): void
    {
        Queue::fake();
        $data = $this->titlePayload();
        config(['services.ghl.physical_product_ids' => [], 'services.ghl.non_print_product_ids' => []]);
        foreach (['test product', 'Free Book + Domestic Shipping (US) extra', 'Free Book', ''] as $title) {
            $data['items'] = [['title' => $title, 'quantity' => 1, 'amount' => 9.97]];
            $this->postJson('/api/webhooks/ghl', $data)->assertStatus(422);
        }
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_normalized_title_alias_overlap_is_rejected(): void
    {
        $data = $this->titlePayload();
        config(['services.ghl.non_print_product_titles' => [' free book + domestic   shipping (us) ']]);
        $this->expectExceptionMessage('product titles must not overlap');
        OrderData::fromGhlPayload($data);
    }

    public function test_title_only_webhooks_preserve_original_cart_and_ignore_electronic_only_events(): void
    {
        Queue::fake();
        $data = $this->titlePayload();
        $digitalOnly = $data;
        array_shift($digitalOnly['items']);
        $this->postJson('/api/webhooks/ghl', $digitalOnly)->assertOk()->assertJsonPath('status', 'ignored_non_print_order');
        $this->assertDatabaseCount('orders', 0);
        $this->postJson('/api/webhooks/ghl', $data)->assertStatus(202);
        $order = Order::firstOrFail();
        $this->assertSame(1, $order->quantity);
        $this->assertSame('14.96', $order->amount_charged);
        $this->assertSame($data, $order->raw_payload);
        $this->postJson('/api/webhooks/ghl', $data)->assertJsonPath('status', 'duplicate_ignored');
        Queue::assertPushed(ProcessLuluPrintJob::class, 1);
        Http::assertNothingSent();
    }

    public function test_title_only_queued_cart_is_corrected_before_cost_and_print_submission(): void
    {
        Bus::fake();
        $order = $this->queuedOrder(['raw_payload' => $this->titlePayload()]);
        $api = Mockery::mock(LuluApiService::class);
        $api->shouldReceive('calculateCost')->once()->with(Mockery::type('array'), 1)->andReturn([]);
        $api->shouldReceive('validatePrintConfiguration')->once();
        $api->shouldReceive('getAccessToken')->once()->andReturn('fake-token');
        $api->shouldReceive('createPrintJob')->once()->with(Mockery::type('array'), 'CART-1', 1)
            ->andReturn(['id' => 'fake-lulu-job', 'status' => ['name' => 'CREATED']]);
        (new ProcessLuluPrintJob($order))->handle($api, Mockery::mock(GhlApiService::class));
        $this->assertSame(1, $order->fresh()->quantity);
        $this->assertNull($order->fresh()->print_cost_estimate);
        $this->assertDatabaseHas('order_events', ['event_type' => 'print_quantity_corrected']);
    }

    public function test_inspector_reports_title_only_selection_without_fulfilling_it(): void
    {
        Bus::fake();
        $order = $this->queuedOrder(['raw_payload' => $this->titlePayload()]);
        $this->artisan('ghl:inspect-cart', ['order' => $order->id])
            ->expectsOutputToContain('Free Book + Domestic Shipping (US)')
            ->expectsOutputToContain('Selected physical quantity: 1; excluded lines: 1.')
            ->doesntExpectOutputToContain('buyer@example.com')
            ->assertSuccessful();
        $this->assertSame(2, $order->fresh()->quantity);
        $this->assertDatabaseCount('order_events', 0);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_inspector_rejects_external_order_ids_before_database_lookup(): void
    {
        $this->artisan('ghl:inspect-cart', ['order' => '6aee-external-purchase-id'])
            ->expectsOutputToContain('positive numeric LOCAL app order ID')
            ->assertFailed();
        Http::assertNothingSent();
    }

    private function queuedOrder(array $extra = []): Order
    {
        return Order::create(array_merge([
            'ghl_order_id' => 'CART-1', 'lulu_environment' => 'sandbox', 'payment_status' => 'paid',
            'fulfillment_status' => 'received', 'quantity' => 2, 'buyer_name' => 'Test Buyer',
            'buyer_email' => 'buyer@example.com', 'shipping_address1' => '123 Example Street',
            'shipping_city' => 'Columbia', 'shipping_state' => 'MO', 'shipping_zip' => '65201', 'shipping_country' => 'US',
            'raw_payload' => $this->payload(), 'print_cost_estimate' => 11.58, 'shipping_cost_estimate' => 6.94,
        ], $extra));
    }

    private function funnelPayload(): array
    {
        $data = $this->payload();
        unset($data['items'], $data['amount']);
        $data['order'] = ['id' => 'CART-1'];
        $data['total_price'] = 14.96;
        $data['line_items'] = [
            ['id' => 'funnel-book', 'title' => 'Free Book + Domestic Shipping (US)', 'quantity' => 1, 'meta' => ['product_id' => 'physical-book', 'price_id' => 'book-price']],
            ['id' => 'funnel-bump', 'title' => 'Instant Digital Copy + Audiobook', 'quantity' => 1, 'meta' => ['product_id' => 'digital-bundle', 'price_id' => 'bump-price']],
        ];

        return $data;
    }

    public function test_real_funnel_metadata_shape_selects_book_and_preserves_full_total(): void
    {
        Queue::fake();
        $data = $this->funnelPayload();
        $dto = OrderData::fromGhlPayload($data);
        $this->assertSame(1, $dto->quantity);
        $this->assertSame(1, $dto->excludedItemCount);
        $this->assertSame(14.96, $dto->amountCharged);
        $this->postJson('/api/webhooks/ghl', $data)->assertStatus(202);
        $order = Order::firstOrFail();
        $this->assertSame('14.96', $order->amount_charged);
        $this->assertSame($data, $order->raw_payload);
        $this->artisan('ghl:inspect-cart', ['order' => $order->id])
            ->expectsOutputToContain('"product_id":"digital-bundle"')
            ->expectsOutputToContain('Selected physical quantity: 1; excluded lines: 1.')
            ->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_unknown_metadata_id_cannot_bypass_selection_through_a_known_title(): void
    {
        $data = $this->funnelPayload();
        config(['services.ghl.physical_product_titles' => ['Free Book + Domestic Shipping (US)']]);
        $data['line_items'][0]['meta']['product_id'] = 'unknown-product';
        $this->expectException(\InvalidArgumentException::class);
        OrderData::fromGhlPayload($data);
    }

    public function test_failed_funnel_order_recovery_splits_address_and_prints_only_one_book(): void
    {
        Bus::fake();
        $street = '12345 Example Road Boulevard Unit 5';
        $order = $this->queuedOrder([
            'raw_payload' => $this->funnelPayload(), 'shipping_address1' => $street,
            'shipping_address2' => 'Building B', 'fulfillment_status' => 'failed',
        ]);
        $expected = LuluApiService::normalizeShippingAddress($order->getShippingAddressArray());
        $api = Mockery::mock(LuluApiService::class);
        $api->shouldReceive('calculateCost')->once()->with($expected, 1)->andReturn(['print_cost' => 5.79, 'shipping_cost' => 5.69]);
        $api->shouldReceive('validatePrintConfiguration')->once();
        $api->shouldReceive('getAccessToken')->once()->andReturn('fake-token');
        $api->shouldReceive('createPrintJob')->once()->with($expected, 'CART-1', 1)
            ->andReturn(['id' => 'fake-recovery-job', 'status' => ['name' => 'CREATED']]);
        (new ProcessLuluPrintJob($order))->handle($api, Mockery::mock(GhlApiService::class));
        $fresh = $order->fresh();
        $this->assertSame(1, $fresh->quantity);
        $this->assertSame($street, $fresh->shipping_address1);
        $this->assertSame('Building B', $fresh->shipping_address2);
        $this->assertSame('fake-recovery-job', $fresh->lulu_job_id);
        $this->assertSame('5.79', $fresh->print_cost_estimate);
        $this->assertSame('5.69', $fresh->shipping_cost_estimate);
        $this->assertFalse($fresh->canRetry());
        $this->assertDatabaseHas('order_events', ['event_type' => 'print_quantity_corrected']);
        Bus::assertDispatched(SendOrderEmails::class, 1);
    }

    public function test_worker_rechecks_old_queued_quantity_before_cost_and_print_calls(): void
    {
        Bus::fake();
        $order = $this->queuedOrder();
        $api = Mockery::mock(LuluApiService::class);
        $api->shouldReceive('calculateCost')->once()->with(Mockery::type('array'), 1)->andReturn(['print_cost' => 5.79, 'shipping_cost' => 5.69]);
        $api->shouldReceive('validatePrintConfiguration')->once();
        $api->shouldReceive('getAccessToken')->once()->andReturn('fake-token');
        $api->shouldReceive('createPrintJob')->once()->with(Mockery::type('array'), 'CART-1', 1)
            ->andReturn(['id' => 'fake-lulu-job', 'status' => ['name' => 'CREATED']]);
        (new ProcessLuluPrintJob($order))->handle($api, Mockery::mock(GhlApiService::class));
        $this->assertSame(1, $order->fresh()->quantity);
        $this->assertSame('5.79', $order->fresh()->print_cost_estimate);
        $this->assertSame('5.69', $order->fresh()->shipping_cost_estimate);
        $this->assertDatabaseHas('order_events', ['event_type' => 'print_quantity_corrected']);
        Bus::assertDispatched(SendOrderEmails::class, 1);
    }

    public function test_existing_or_uncertain_lulu_jobs_are_never_rewritten_or_reprinted(): void
    {
        Bus::fake();
        foreach ([['lulu_job_id' => 'existing-job'], ['submission_started_at' => now()]] as $extra) {
            $order = $this->queuedOrder(array_merge(['ghl_order_id' => 'CART-'.count($extra).'-'.Order::count()], $extra));
            (new ProcessLuluPrintJob($order))->handle(Mockery::mock(LuluApiService::class), Mockery::mock(GhlApiService::class));
            $this->assertSame(2, $order->fresh()->quantity);
        }
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_two_physical_books_use_quantity_two_in_both_cost_and_print_calls(): void
    {
        Bus::fake();
        $order = $this->queuedOrder(['quantity' => 3, 'raw_payload' => $this->payload(2)]);
        $api = Mockery::mock(LuluApiService::class);
        $api->shouldReceive('calculateCost')->once()->with(Mockery::type('array'), 2)->andReturn([]);
        $api->shouldReceive('validatePrintConfiguration')->once();
        $api->shouldReceive('getAccessToken')->once()->andReturn('fake-token');
        $api->shouldReceive('createPrintJob')->once()->with(Mockery::type('array'), 'CART-1', 2)
            ->andReturn(['id' => 'fake-lulu-job', 'status' => ['name' => 'CREATED']]);
        (new ProcessLuluPrintJob($order))->handle($api, Mockery::mock(GhlApiService::class));
        $this->assertSame(2, $order->fresh()->quantity);
        $this->assertNull($order->fresh()->print_cost_estimate);
        $this->assertNull($order->fresh()->shipping_cost_estimate);
    }

    public function test_queued_digital_only_order_is_cancelled_without_printing_or_email(): void
    {
        Bus::fake();
        $data = $this->payload();
        array_shift($data['items']);
        $order = $this->queuedOrder(['raw_payload' => $data]);
        (new ProcessLuluPrintJob($order))->handle(Mockery::mock(LuluApiService::class), Mockery::mock(GhlApiService::class));
        $this->assertSame('cancelled', $order->fresh()->fulfillment_status);
        $this->assertNull($order->fresh()->submission_started_at);
        Bus::assertNothingDispatched();
    }

    public function test_inspection_only_reads_product_fields_and_never_processes_the_order(): void
    {
        Bus::fake();
        $order = $this->queuedOrder();
        $this->artisan('ghl:inspect-cart', ['order' => $order->id])
            ->expectsOutputToContain('physical-book')
            ->expectsOutputToContain('digital-bundle')
            ->expectsOutputToContain('Selected physical quantity: 1; excluded lines: 1.')
            ->doesntExpectOutputToContain('buyer@example.com')
            ->assertSuccessful();
        $this->assertSame(2, $order->fresh()->quantity);
        $this->assertDatabaseCount('order_events', 0);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_inspection_reports_unconfigured_mapping_without_changing_data(): void
    {
        config(['services.ghl.physical_product_ids' => [], 'services.ghl.non_print_product_ids' => []]);
        $order = $this->queuedOrder();
        $this->artisan('ghl:inspect-cart', ['order' => $order->id])->assertFailed();
        $this->assertSame(2, $order->fresh()->quantity);
        $this->assertDatabaseCount('order_events', 0);
        Http::assertNothingSent();
    }

    public function test_products_only_inspection_does_not_require_selector_configuration(): void
    {
        config(['services.ghl.physical_product_ids' => [], 'services.ghl.non_print_product_ids' => []]);
        $order = $this->queuedOrder();
        $this->artisan('ghl:inspect-cart', ['order' => $order->id, '--products-only' => true])
            ->expectsOutputToContain('physical-book')->expectsOutputToContain('digital-bundle')->assertSuccessful();
        $this->assertSame(2, $order->fresh()->quantity);
        Http::assertNothingSent();
    }

    public function test_worker_will_not_print_a_queued_cart_with_unknown_products(): void
    {
        Bus::fake();
        $payload = $this->payload();
        $payload['items'][1]['productId'] = 'unmapped-upsell';
        $order = $this->queuedOrder(['raw_payload' => $payload]);
        try {
            (new ProcessLuluPrintJob($order))->handle(Mockery::mock(LuluApiService::class), Mockery::mock(GhlApiService::class));
            $this->fail('Unknown products must block submission.');
        } catch (\InvalidArgumentException) {
            $this->assertNull($order->fresh()->submission_started_at);
            $this->assertNull($order->fresh()->lulu_job_id);
            $this->assertSame('failed', $order->fresh()->fulfillment_status);
        }
        Bus::assertNothingDispatched();
    }
}
