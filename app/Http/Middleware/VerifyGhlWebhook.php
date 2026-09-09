<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyGhlWebhook
{
    /**
     * Validate that the incoming request is genuinely from GoHighLevel.
     *
     * GHL sends a custom header or we use a shared secret in query params.
     * You can configure this in GHL workflow → Webhook action → Custom Headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.ghl.webhook_secret');

        // If no secret configured, skip validation (dev mode)
        if (empty($secret)) {
            if (! app()->environment(['local', 'testing'])) {
                Log::error('GHL webhook secret is missing. Requests are disabled.');

                return response()->json(['error' => 'Webhook is not configured.'], 503);
            }
            Log::warning('GHL Webhook: No secret configured. Skipping verification (dev mode).');

            return $next($request);
        }

        // Method 1: Check X-GHL-Secret header (recommended)
        $headerSecret = $request->header('X-GHL-Secret');

        // Method 2: Check query param (fallback)
        $querySecret = $request->query('secret');

        if ((! is_string($headerSecret) || ! hash_equals($secret, $headerSecret)) && (! is_string($querySecret) || ! hash_equals($secret, $querySecret))) {
            Log::warning('GHL Webhook: Invalid secret. Request rejected.', [
                'ip' => $request->ip(),
                'header_secret' => $headerSecret ? 'present_but_wrong' : 'missing',
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
