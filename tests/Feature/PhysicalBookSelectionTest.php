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
