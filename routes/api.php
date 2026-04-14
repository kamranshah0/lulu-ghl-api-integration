<?php

use App\Http\Controllers\WebhookController;
use App\Http\Middleware\VerifyGhlWebhook;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Forever Wellthy Middleware
|--------------------------------------------------------------------------
|
| Webhook endpoint receives orders from GoHighLevel.
| Protected by VerifyGhlWebhook middleware (shared secret validation).
|
| Webhook URL to configure in GHL:
|   POST https://yourdomain.com/api/webhooks/ghl
|   Header:  X-GHL-Secret: {your_secret}
|
*/

Route::post('/webhooks/ghl', [WebhookController::class, 'handleGhlOrder'])
    ->middleware(VerifyGhlWebhook::class)
    ->name('webhook.ghl');

// Health check endpoint (no auth — for uptime monitoring)
Route::get('/health', function () {
    return response()->json([
        'status'    => 'ok',
        'service'   => 'Forever Wellthy Middleware',
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('health');
