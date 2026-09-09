<?php

namespace Tests\Feature;

use App\Jobs\ProcessLuluPrintJob;
use App\Models\Order;
use App\Models\User;
use App\Services\GhlApiService;
use App\Services\LuluApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class OrderPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['order_id' => 'ORDER-1', 'name' => 'Test Buyer', 'email' => 'buyer@example.com', 'address1' => '123 Test St',
            'city' => 'Austin', 'state' => 'TX', 'zip' => '78701', 'country' => 'US', 'phone' => '+15555550123'];
    }

    private function order(array $extra = []): Order
    {
        return Order::create(array_merge(['ghl_order_id' => 'ORDER-1', 'buyer_name' => 'Test Buyer', 'buyer_email' => 'buyer@example.com',
            'shipping_address1' => '123 Test St', 'shipping_city' => 'Austin', 'shipping_state' => 'TX', 'shipping_zip' => '78701',
            'shipping_country' => 'US', 'quantity' => 1, 'fulfillment_status' => 'received', 'lulu_environment' => 'sandbox'], $extra));
    }

    public function test_webhook_rejects_unauthorized_then_stores_once_and_queues_once(): void
    {
        Queue::fake();
        config(['services.ghl.webhook_secret' => 'test-secret', 'services.lulu.use_sandbox' => false]);
        $this->postJson('/api/webhooks/ghl', $this->payload())->assertUnauthorized();
        $this->withHeader('X-GHL-Secret', 'test-secret')->postJson('/api/webhooks/ghl', $this->payload())->assertStatus(202);
        $this->postJson('/api/webhooks/ghl', $this->payload())->assertOk()->assertJsonPath('status', 'duplicate_ignored');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['lulu_environment' => 'production']);
        Queue::assertPushed(ProcessLuluPrintJob::class, 1);
    }

    public function test_production_webhook_fails_closed_without_secret(): void
    {
        $this->app->instance('env', 'production');
        config(['services.ghl.webhook_secret' => null]);
        $this->postJson('/api/webhooks/ghl', $this->payload())->assertStatus(503);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_queue_failure_rolls_back_intake_so_ghl_can_retry(): void
    {
        config(['services.ghl.webhook_secret' => 'test-secret']);
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Queue unavailable'));
        $this->withHeader('X-GHL-Secret', 'test-secret')->postJson('/api/webhooks/ghl', $this->payload())->assertStatus(500);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_existing_or_uncertain_job_never_submits_again(): void
    {
        $order = $this->order(['lulu_job_id' => '123', 'fulfillment_status' => 'failed']);
        $api = Mockery::mock(LuluApiService::class);
        $api->shouldNotReceive('createPrintJob');
        $ghl = Mockery::mock(GhlApiService::class);
        (new ProcessLuluPrintJob($order))->handle($api, $ghl);
        $this->assertFalse($order->canRetry());
        $order->update(['lulu_job_id' => null, 'submission_started_at' => now()]);
        (new ProcessLuluPrintJob($order))->handle($api, $ghl);
        $this->assertStringContainsString('uncertain', $order->fresh()->error_message);
    }

    public function test_submission_timeout_leaves_durable_marker_and_no_second_post(): void
    {
        $order = $this->order();
        $api = Mockery::mock(LuluApiService::class);
        $api->shouldReceive('calculateCost')->once()->andReturn([]);
        $api->shouldReceive('validatePrintConfiguration')->once();
        $api->shouldReceive('getAccessToken')->once()->andReturn('token');
        $api->shouldReceive('createPrintJob')->once()->andThrow(new ConnectionException('Timeout'));
        $ghl = Mockery::mock(GhlApiService::class);
        try {
            (new ProcessLuluPrintJob($order))->handle($api, $ghl);
            $this->fail('Expected timeout.');
        } catch (ConnectionException) {
            $this->assertNotNull($order->fresh()->submission_started_at);
        }
        (new ProcessLuluPrintJob($order))->handle($api, $ghl);
        $this->assertFalse($order->fresh()->canRetry());
    }

    public function test_environment_mismatch_blocks_submission(): void
    {
        config(['services.lulu.use_sandbox' => false]);
        $order = $this->order(['lulu_environment' => 'sandbox']);
        $this->expectException(\InvalidArgumentException::class);
        (new ProcessLuluPrintJob($order))->handle(Mockery::mock(LuluApiService::class), Mockery::mock(GhlApiService::class));
    }

    public function test_admin_retry_cannot_duplicate_existing_lulu_job(): void
    {
        Queue::fake();
        $order = $this->order(['lulu_job_id' => '123', 'fulfillment_status' => 'failed']);
        $this->actingAs(User::factory()->create())->post('/admin/orders/'.$order->id.'/retry')->assertSessionHas('error');
        Queue::assertNothingPushed();
    }

    public function test_malformed_payload_returns_validation_error(): void
    {
        config(['services.ghl.webhook_secret' => 'test-secret']);
        $this->withHeader('X-GHL-Secret', 'test-secret')->postJson('/api/webhooks/ghl', ['payload' => 'invalid'])->assertStatus(422);
    }

    public function test_unknown_historical_environment_is_not_assumed_live(): void
    {
        config(['services.lulu.use_sandbox' => false]);
        $order = $this->order(['lulu_environment' => null]);
        $this->expectException(\InvalidArgumentException::class);
        (new ProcessLuluPrintJob($order))->handle(Mockery::mock(LuluApiService::class), Mockery::mock(GhlApiService::class));
    }

    public function test_unpaid_order_is_not_queued(): void
    {
        Queue::fake();
        config(['services.ghl.webhook_secret' => 'test-secret']);
        $this->withHeader('X-GHL-Secret', 'test-secret')->postJson('/api/webhooks/ghl', array_merge($this->payload(), ['payment_status' => 'unpaid']))->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_csv_filters_and_escapes_untrusted_values(): void
    {
        $this->order(['buyer_name' => '=1+1']);
        $this->order(['ghl_order_id' => 'ORDER-2', 'buyer_name' => 'Other Buyer']);
        $response = $this->actingAs(User::factory()->create())->get('/admin/orders/export?search=ORDER-1');
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringNotContainsString('ORDER-2', $csv);
        $this->get('/admin/orders?from=invalid')->assertRedirect()->assertSessionHasErrors('from');
    }

    public function test_classification_does_not_queue_or_overwrite_known_environment(): void
    {
        Queue::fake();
        $order = $this->order(['lulu_environment' => null]);
        $this->artisan('lulu:classify', ['order' => $order->id, 'environment' => 'sandbox'])->assertExitCode(0);
        $this->assertSame('sandbox', $order->fresh()->lulu_environment);
        $this->artisan('lulu:classify', ['order' => $order->id, 'environment' => 'production'])->assertExitCode(1);
        Queue::assertNothingPushed();
    }
}
