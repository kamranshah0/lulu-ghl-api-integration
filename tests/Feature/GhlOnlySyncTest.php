<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\LuluApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class GhlOnlySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.lulu.use_sandbox' => false,
            'services.ghl.api_key' => 'test-token',
            'services.ghl.api_version' => 'v3',
            'services.ghl.custom_field_id_status' => 'status-field',
            'services.ghl.custom_field_id_job_id' => 'job-field',
        ]);
        Bus::fake();
        Mail::fake();
        // Any Lulu method call is unexpected in this recovery mode.
        $this->app->instance(LuluApiService::class, Mockery::mock(LuluApiService::class));
    }

    private function order(string $reference, array $extra = []): Order
    {
        return Order::create(array_merge([
            'ghl_order_id' => $reference,
            'ghl_contact_id' => 'contact-'.$reference,
            'lulu_job_id' => 'job-'.$reference,
            'lulu_environment' => 'production',
            'lulu_status' => 'DELIVERED',
            'fulfillment_status' => 'print_job_created',
            'payment_status' => 'paid',
            'buyer_name' => 'Test Buyer',
            'buyer_email' => 'buyer@example.com',
            'quantity' => 1,
        ], $extra));
    }

    public function test_retries_all_pending_statuses_without_lulu_or_app_emails_and_skips_successes(): void
    {
        Http::fake(['https://services.leadconnectorhq.com/*' => Http::response(['succeeded' => true])]);
        $delivered = $this->order('delivered');
        $printing = $this->order('printing', ['lulu_status' => 'IN_PRODUCTION', 'ghl_synced_status' => 'CREATED']);

        $this->artisan('lulu:sync-status', ['--ghl-only' => true])->assertSuccessful();

        Http::assertSentCount(4);
        Http::assertSent(fn ($r) => $r->method() === 'PUT'
            && $r->hasHeader('Version', 'v3')
            && $r['customFields'] === [['id' => 'status-field', 'fieldValue' => 'DELIVERED']]);
        $this->assertSame('DELIVERED', $delivered->fresh()->ghl_synced_status);
        $this->assertSame('IN_PRODUCTION', $printing->fresh()->ghl_synced_status);
        $this->assertSame('print_job_created', $delivered->fresh()->fulfillment_status);
        $this->assertSame('paid', $delivered->fresh()->payment_status);
        $this->assertSame('job-delivered', $delivered->fresh()->lulu_job_id);
        $this->assertNull($delivered->fresh()->print_cost_estimate);
        $this->assertSame(1, $delivered->events()->where('event_type', 'ghl_status_synced')->count());

        $this->artisan('lulu:sync-status', ['--ghl-only' => true])->assertSuccessful();
        Http::assertSentCount(4);
        Bus::assertNothingDispatched();
        Mail::assertNothingSent();
    }

    public function test_skips_other_environments_missing_references_and_already_synced_orders(): void
    {
        $this->order('sandbox', ['lulu_environment' => 'sandbox']);
        $this->order('unknown', ['lulu_environment' => null]);
        $this->order('no-job', ['lulu_job_id' => null]);
        $this->order('empty-job', ['lulu_job_id' => '']);
        $this->order('no-contact', ['ghl_contact_id' => null]);
        $this->order('empty-contact', ['ghl_contact_id' => '']);
        $this->order('synced', ['ghl_synced_status' => 'DELIVERED']);

        $this->artisan('lulu:sync-status', ['--ghl-only' => true])->assertSuccessful();
        Http::assertNothingSent();
        Bus::assertNothingDispatched();
        Mail::assertNothingSent();
        $this->assertDatabaseCount('order_events', 0);
    }

    public function test_a_failed_update_is_logged_and_does_not_stop_other_orders(): void
    {
        Http::fake([
            'https://services.leadconnectorhq.com/contacts/contact-failed' => Http::response([], 422),
            'https://services.leadconnectorhq.com/*' => Http::response(['succeeded' => true]),
        ]);
        $failed = $this->order('failed');
        $successful = $this->order('successful');

        $this->artisan('lulu:sync-status', ['--ghl-only' => true])->assertExitCode(1);
        Http::assertSentCount(3);
        $this->assertNull($failed->fresh()->ghl_synced_status);
        $this->assertSame('DELIVERED', $successful->fresh()->ghl_synced_status);
        $event = $failed->events()->where('event_type', 'ghl_status_sync_failed')->firstOrFail();
        $this->assertStringContainsString('HTTP 422', $event->payload['error']);
        $this->assertSame('job-failed', $failed->fresh()->lulu_job_id);
        Bus::assertNothingDispatched();
        Mail::assertNothingSent();
    }

    public function test_missing_configuration_fails_without_sending_any_requests(): void
    {
        config(['services.ghl.custom_field_id_status' => null, 'services.ghl.custom_field_id_job_id' => null]);
        $order = $this->order('missing-config');
        $this->artisan('lulu:sync-status', ['--ghl-only' => true])->assertExitCode(1);
        Http::assertNothingSent();
        $this->assertNull($order->fresh()->ghl_synced_status);
        $event = $order->events()->where('event_type', 'ghl_status_sync_failed')->firstOrFail();
        $this->assertStringContainsString('GHL_CUSTOM_FIELD_ID_STATUS', $event->payload['error']);
        Bus::assertNothingDispatched();
    }

    public function test_missing_or_unknown_saved_status_is_not_sent_to_ghl(): void
    {
        $this->order('missing-status', ['lulu_status' => null]);
        $this->order('unknown-status', ['lulu_status' => 'UNKNOWN']);
        $this->artisan('lulu:sync-status', ['--ghl-only' => true])->assertExitCode(1);
        Http::assertNothingSent();
        $this->assertDatabaseCount('order_events', 2);
        Bus::assertNothingDispatched();
    }
}
