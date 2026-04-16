<?php

namespace App\Services;

use App\Exceptions\LuluApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LuluApiService
{
    private string $baseUrl;
    private string $clientKey;
    private string $clientSecret;
    private bool $useSandbox;

    public function __construct()
    {
        $this->useSandbox   = config('services.lulu.use_sandbox', true);
        $this->clientKey    = config('services.lulu.client_key');
        $this->clientSecret = config('services.lulu.client_secret');
        $this->baseUrl      = $this->useSandbox
            ? config('services.lulu.sandbox_api_base', 'https://api.sandbox.lulu.com')
            : config('services.lulu.api_base', 'https://api.lulu.com');
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function getAccessToken(): string
    {
        $cacheKey = 'lulu_access_token_' . ($this->useSandbox ? 'sandbox' : 'production');

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            $tokenUrl = $this->useSandbox
                ? 'https://api.sandbox.lulu.com/auth/realms/glasstree/protocol/openid-connect/token'
                : 'https://api.lulu.com/auth/realms/glasstree/protocol/openid-connect/token';

            $response = Http::withoutVerifying()->asForm()->post($tokenUrl, [
                'client_id'     => $this->clientKey,
                'client_secret' => $this->clientSecret,
                'grant_type'    => 'client_credentials',
            ]);

            if (! $response->successful()) {
                Log::channel('lulu')->error('Lulu: Auth token retrieval failed.', [
                    'status' => $response->status(),
                    'body'   => $response->body()
                ]);
                throw new LuluApiException('Lulu authentication failed', $response->status(), $tokenUrl);
            }

            Log::channel('lulu')->info('Lulu: Access token refreshed.');
            return $response->json()['access_token'];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Print Job Creation
    |--------------------------------------------------------------------------
    */

    public function createPrintJob(array $shippingAddress, string $ghlOrderId, int $quantity = 1): array
    {
        $token = $this->getAccessToken();

        $payload = [
            'contact_email' => config('services.lulu.contact_email'),
            'external_id'   => $ghlOrderId,
            'line_items'    => [
                [
                    'title'          => 'Forever Wellthy Book',
                    'cover'          => config('services.lulu.book_cover_url'),
                    'interior'       => config('services.lulu.book_interior_url'),
                    'pod_package_id' => config('services.lulu.pod_package_id'),
                    'quantity'       => $quantity,
                ],
            ],
            'shipping_address' => $shippingAddress,
            'shipping_level'   => config('services.lulu.shipping_level', 'MAIL'),
        ];

        Log::channel('lulu')->info('Lulu: Submitting print job.', [
            'external_id' => $ghlOrderId,
            'payload'     => $payload
        ]);

        $response = Http::withoutVerifying()->withToken($token)
            ->post("{$this->baseUrl}/print-jobs/", $payload);

        if (! $response->successful()) {
            throw new LuluApiException(
                message: "Lulu print job creation failed (HTTP {$response->status()})",
                statusCode: $response->status(),
                url: "{$this->baseUrl}/print-jobs/",
                payload: $payload,
                responseBody: $response->body()
            );
        }

        $data = $response->json();
        Log::channel('lulu')->info('Lulu: Print job created effectively.', [
            'job_id' => $data['id'] ?? 'unknown'
        ]);

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | Print Job Status
    |--------------------------------------------------------------------------
    */

    /**
     * Retrieve the current status of a Lulu print job.
     */
    public function getPrintJobStatus(string $luluJobId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withoutVerifying()->withToken($token)
            ->get("{$this->baseUrl}/print-jobs/{$luluJobId}/");

        if (! $response->successful()) {
            Log::error('Lulu: Failed to fetch print job status.', [
                'lulu_job_id' => $luluJobId,
                'status'      => $response->status(),
            ]);
            throw new \RuntimeException('Failed to fetch Lulu job status: ' . $response->body());
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | Cost Estimation (Optional — useful for validation)
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate print and shipping cost before actually submitting the job.
     * Lulu also validates the address here.
     */
    public function calculateCost(array $shippingAddress, int $quantity = 1): array
    {
        $token = $this->getAccessToken();

        $payload = [
            'line_items' => [
                [
                    'pod_package_id' => config('services.lulu.pod_package_id'),
                    'quantity'       => $quantity,
                    'cover'          => config('services.lulu.book_cover_url'),
                    'interior'       => config('services.lulu.book_interior_url'),
                ],
            ],
            'shipping_address' => $shippingAddress,
            'shipping_level'   => config('services.lulu.shipping_level', 'MAIL'),
        ];

        $response = Http::withoutVerifying()->withToken($token)
            ->post("{$this->baseUrl}/print-job-cost-calculations/", $payload);

        if (! $response->successful()) {
            Log::warning('Lulu: Cost calculation failed (non-blocking).', [
                'status'   => $response->status(),
                'response' => $response->json(),
            ]);
            return [];
        }

        return $response->json();
    }
}
