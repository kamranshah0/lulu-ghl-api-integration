<?php

namespace Tests\Feature;

use App\Jobs\SendOrderEmails;
use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\User;
use App\Services\GhlApiService;
use App\Services\LuluApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class StatusAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $extra = []): Order
    {
        return Order::create(array_merge(['ghl_order_id' => 'ORDER-1', 'lulu_job_id' => '123', 'lulu_environment' => 'sandbox',
            'lulu_status' => 'CREATED', 'fulfillment_status' => 'print_job_created', 'buyer_name' => 'Test Buyer',
            'buyer_email' => 'buyer@example.com', 'quantity' => 1, 'print_cost_estimate' => 5, 'shipping_cost_estimate' => 4], $extra));
    }

    public function test_failed_admin_email_does_not_resend_successful_buyer_email(): void
    {
        $order = $this->order();
        config(['services.admin.email' => null]);
        Mail::fake();
        try {
            (new SendOrderEmails($order))->handle();
            $this->fail('Expected missing admin email error.');
        } catch (\RuntimeException) {
            Mail::assertSent(OrderConfirmationMail::class, 1);
        }
        config(['services.admin.email' => 'admin@example.com']);
        (new SendOrderEmails($order))->handle();
        Mail::assertSentCount(2);
        (new SendOrderEmails($order))->handle();
        Mail::assertSentCount(2);
        $this->assertSame('print_job_created', $order->fresh()->fulfillment_status);
    }

    public function test_sync_repairs_status_even_when_lulu_status_string_has_not_changed(): void
    {
        config(['services.lulu.use_sandbox' => true, 'services.admin.email' => 'admin@example.com']);
        Mail::fake();
        $order = $this->order(['lulu_status' => 'SHIPPED', 'ghl_contact_id' => 'contact-1']);
        $api = Mockery::mock(LuluApiService::class);
        $api->shouldReceive('getPrintJobStatus')->once()->with('123')->andReturn(['status' => ['name' => 'SHIPPED']]);
        $this->app->instance(LuluApiService::class, $api);
        $ghl = Mockery::mock(GhlApiService::class);
        $ghl->shouldReceive('updateContactFulfillmentStatus')->once()->andReturn(true);
        $this->app->instance(GhlApiService::class, $ghl);
        $this->artisan('lulu:sync-status')->assertExitCode(0);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'fulfillment_status' => 'shipped', 'ghl_synced_status' => 'SHIPPED']);
    }

    public function test_terminal_order_with_unsynced_ghl_status_is_retried(): void
    {
        config(['services.lulu.use_sandbox' => true, 'services.admin.email' => 'admin@example.com']);
        Mail::fake();
        $order = $this->order(['lulu_status' => 'SHIPPED', 'fulfillment_status' => 'shipped', 'ghl_contact_id' => 'contact-1']);
        $api = Mockery::mock(LuluApiService::class);
        $api->shouldReceive('getPrintJobStatus')->twice()->andReturn(['status' => ['name' => 'SHIPPED']]);
        $this->app->instance(LuluApiService::class, $api);
        $ghl = Mockery::mock(GhlApiService::class);
        $ghl->shouldReceive('updateContactFulfillmentStatus')->twice()->andReturn(false, true);
        $this->app->instance(GhlApiService::class, $ghl);
        $this->artisan('lulu:sync-status')->assertExitCode(1);
        $this->assertNull($order->fresh()->ghl_synced_status);
        $this->artisan('lulu:sync-status')->assertExitCode(0);
        $this->assertSame('SHIPPED', $order->fresh()->ghl_synced_status);
    }

    public function test_sync_does_not_query_other_or_unverified_environments(): void
    {
        config(['services.lulu.use_sandbox' => false]);
        $order = $this->order();
        $api = Mockery::mock(LuluApiService::class);
        $api->shouldNotReceive('getPrintJobStatus');
        $this->app->instance(LuluApiService::class, $api);
        $this->artisan('lulu:sync-status')->assertExitCode(0);
        $order->update(['lulu_environment' => null]);
        $this->artisan('lulu:sync-status')->assertExitCode(0);
    }

    public function test_missing_ghl_fields_are_explained_without_blocking_email_recovery(): void
    {
        config(['services.lulu.use_sandbox' => true, 'services.admin.email' => 'admin@example.com',
            'services.ghl.api_key' => 'test-token', 'services.ghl.custom_field_id_status' => null,
            'services.ghl.custom_field_id_job_id' => null]);
        Mail::fake();
        $order = $this->order(['ghl_contact_id' => 'contact-1', 'lulu_status' => 'IN_PRODUCTION', 'fulfillment_status' => 'in_production']);
        $api = Mockery::mock(LuluApiService::class);
        $api->shouldReceive('getPrintJobStatus')->twice()->andReturn(['status' => ['name' => 'IN_PRODUCTION']]);
        $api->shouldNotReceive('createPrintJob');
        $this->app->instance(LuluApiService::class, $api);
        $this->artisan('lulu:sync-status')->assertExitCode(1);
        $event = $order->events()->where('event_type', 'ghl_status_sync_failed')->firstOrFail();
        $this->assertStringContainsString('GHL_CUSTOM_FIELD_ID_STATUS', $event->payload['error']);
        $this->assertStringContainsString('GHL_CUSTOM_FIELD_ID_JOB_ID', $event->payload['error']);
        $this->assertNull($order->fresh()->ghl_synced_status);
        Mail::assertSentCount(2);
        $this->artisan('lulu:sync-status')->assertExitCode(1);
        Mail::assertSentCount(2);
        $this->assertSame('123', $order->fresh()->lulu_job_id);
    }

    public function test_reconciliation_verifies_external_order_id_before_linking(): void
    {
        $order = $this->order(['lulu_job_id' => null, 'lulu_environment' => null, 'submission_started_at' => now()]);
        $api = Mockery::mock(LuluApiService::class);
        $api->shouldReceive('environment')->andReturn('sandbox');
        $api->shouldReceive('getPrintJobStatus')->twice()->andReturn(
            ['id' => 123, 'external_id' => 'OTHER', 'status' => ['name' => 'CREATED']],
            ['id' => 123, 'external_id' => 'ORDER-1', 'status' => ['name' => 'CREATED']]
        );
        $this->app->instance(LuluApiService::class, $api);
        $this->artisan('lulu:reconcile', ['order' => $order->id, 'job' => '123'])->assertExitCode(1);
        $this->assertNull($order->fresh()->lulu_job_id);
        $this->artisan('lulu:reconcile', ['order' => $order->id, 'job' => '123'])->assertExitCode(0);
        $this->assertSame('123', $order->fresh()->lulu_job_id);
    }

    public function test_admin_sees_unavailable_costs_and_no_unsafe_retry_button(): void
    {
        $order = $this->order(['fulfillment_status' => 'failed', 'print_cost_estimate' => null, 'shipping_cost_estimate' => null]);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/orders/'.$order->id)->assertOk()->assertSee('Unavailable')->assertDontSee('Retry Fulfillment');
        $this->get('/admin')->assertOk();
        $this->get('/admin/orders')->assertOk();
        $this->get('/admin/orders/failed')->assertOk();
        $this->get('/admin/settings')->assertOk();
    }
}
