<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessLuluPrintJob;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WebhookController extends Controller
{
    /**
     * Handle incoming order webhook from GoHighLevel.
     *
     * POST /api/webhooks/ghl
     *
     * Expected payload:
     * {
     *   "type": "order.completed",
     *   "order": {
     *     "id": "GHL_ORDER_ID",
     *     "customer": { firstName, lastName, email, phone },
     *     "shippingAddress": { address1, city, state, zip, country },
     *     "items": [{ quantity, ... }],
     *     "totalAmount": 97.00
     *   },
     *   "contactId": "GHL_CONTACT_ID"
     * }
     */
    public function handleGhlOrder(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('GHL Webhook: Received payload.', [
            'ip' => $request->ip(),
            'type' => $payload['type'] ?? 'unknown',
            'payload' => $payload,
        ]);

        try {
            // ── Step 1: Normalize payload using DTO ───────────────────────
            $orderData = \App\DTOs\OrderData::fromGhlPayload($payload);
            $ghlOrderId = $orderData->ghlOrderId;

            // ── Step 2: Idempotency Check (ignore duplicates) ─────────────
            $existing = Order::where('ghl_order_id', $ghlOrderId)->first();

            if ($existing) {
                Log::info("GHL Webhook: Duplicate order '{$ghlOrderId}'. Ignoring.");
                $existing->logEvent('duplicate_detected', 'ghl', $payload, 'Duplicate webhook received and ignored.');
                return response()->json(['status' => 'duplicate_ignored', 'order_id' => $existing->id]);
            }

            // ── Step 3: Save order to database ───────────────────────────
            $order = Order::create([
                'ghl_contact_id' => $orderData->contactId,
                'ghl_order_id' => $orderData->ghlOrderId,
                'payment_status' => 'paid',
                'fulfillment_status' => 'received',
                'book_sku' => config('services.lulu.pod_package_id'),
                'quantity' => $orderData->quantity,
                'buyer_name' => $orderData->buyerName,
                'buyer_email' => $orderData->buyerEmail,
                'buyer_phone' => $orderData->buyerPhone,
                'shipping_address1' => $orderData->address1,
                'shipping_address2' => $orderData->address2,
                'shipping_city' => $orderData->city,
                'shipping_state' => $orderData->state,
                'shipping_zip' => $orderData->zip,
                'shipping_country' => $orderData->country,
                'amount_charged' => $orderData->amountCharged,
                'raw_payload' => $orderData->rawPayload,
            ]);

            $order->logEvent('webhook_received', 'ghl', $payload, 'Order received and stored from GHL.');

            // ── Step 4: Dispatch async job ────────────────────────────────
            ProcessLuluPrintJob::dispatch($order);
            $order->logEvent('job_dispatched', 'system', [], 'Print job dispatched to queue.');

            Log::info("GHL Webhook: Order #{$order->id} stored and job dispatched.");

            return response()->json([
                'status' => 'queued',
                'order_id' => $order->id,
            ], 202);

        } catch (\InvalidArgumentException $e) {
            Log::warning('GHL Webhook: Validation error.', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('GHL Webhook: Unexpected error.', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'An internal error occurred.'], 500);
        }
    }
}
