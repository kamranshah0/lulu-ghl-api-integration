<?php

namespace Tests\Feature;

use App\Jobs\ProcessLuluPrintJob;
use App\Mail\AdminOrderNotificationMail;
use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Services\GhlApiService;
use App\Services\LuluApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class ProcessLuluPrintJobMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_order_email_to_buyer_and_admin(): void
    {
        $this->assertOrderNotifications(false);
    }

    public function test_ghl_status_failure_does_not_block_note_or_buyer_and_admin_email(): void
    {
        $this->assertOrderNotifications(true);
    }

    private function assertOrderNotifications(bool $ghlFails): void
    {
        Mail::fake();
        config(['services.admin.email' => 'admin@example.com']);

        $order = Order::create([
            'ghl_order_id' => 'GHL-EMAIL-1001',
            'ghl_contact_id' => 'test-contact',
            'lulu_environment' => 'sandbox',
            'payment_status' => 'paid',
            'fulfillment_status' => 'received',
            'book_sku' => '0600X0900.BW.STD.PB.060UC444.GXX',
            'quantity' => 1,
            'buyer_name' => 'Jane Buyer',
            'buyer_email' => 'buyer@example.com',
            'buyer_phone' => '+15555550123',
            'shipping_address1' => '123 Market St',
            'shipping_city' => 'Saint Louis',
            'shipping_state' => 'MO',
            'shipping_zip' => '63101',
            'shipping_country' => 'US',
            'amount_charged' => 97,
        ]);

        $luluApi = Mockery::mock(LuluApiService::class);
        $luluApi->shouldReceive('getAccessToken')->once()->andReturn('test-token');
        $luluApi->shouldReceive('validatePrintConfiguration')->once();
        $luluApi->shouldReceive('calculateCost')->once()->andReturn([
            'line_item_costs' => [
                [
                    'print_cost' => [
                        'total_cost_excl_tax' => '3.49',
                    ],
                ],
            ],
            'shipping_cost' => [
                'total_cost_excl_tax' => '5.69',
            ],
        ]);
        $luluApi->shouldReceive('createPrintJob')->once()->andReturn([
            'id' => 'LULU-123',
            'status' => [
                'name' => 'CREATED',
            ],
        ]);

        $ghlApi = Mockery::mock(GhlApiService::class);
        if ($ghlFails) {
            $ghlApi->shouldReceive('updateContactFulfillmentStatus')->once()->andThrow(new \RuntimeException('GHL sync configuration missing: GHL_CUSTOM_FIELD_ID_STATUS.'));
        } else {
            $ghlApi->shouldReceive('updateContactFulfillmentStatus')->once()->andReturn(true);
        }
        $ghlApi->shouldReceive('addContactNote')->once()->andReturn(true);

        (new ProcessLuluPrintJob($order))->handle($luluApi, $ghlApi);

        Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) {
            return $mail->hasTo('buyer@example.com');
        });

        Mail::assertSent(AdminOrderNotificationMail::class, function (AdminOrderNotificationMail $mail) {
            return $mail->hasTo('admin@example.com');
        });

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'confirmation_email_sent',
        ]);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => 'admin_notification_email_sent',
        ]);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'event_type' => 'ghl_note_added']);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'event_type' => $ghlFails ? 'ghl_status_sync_failed' : 'ghl_status_synced']);
        $this->assertSame('LULU-123', $order->fresh()->lulu_job_id);
        $this->assertSame('print_job_created', $order->fresh()->fulfillment_status);
    }
}
