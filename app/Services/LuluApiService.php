<?php

namespace App\Services;

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
    | Authentication — OAuth2 / OpenID Connect
    |--------------------------------------------------------------------------
    */

    /**
     * Get a valid Lulu access token.
     * Cached for the duration of its validity (minus 60s buffer).
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
                Log::error('Lulu: Failed to fetch access token.', [
                    'status'   => $response->status(),
                    'response' => $response->json(),
                ]);
                throw new \RuntimeException('Lulu authentication failed: ' . $response->body());
            }

            $data = $response->json();
            Log::info('Lulu: Access token fetched successfully.');
            return $data['access_token'];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Print Job Creation
    |--------------------------------------------------------------------------
    */

    /**
     * Create a print job for the given order.
     *
     * @param  array $shippingAddress  From Order::getShippingAddressArray()
     * @param  string $ghlOrderId      Used as external_id for traceability
     * @param  int    $quantity
     * @return array  Lulu API response (includes 'id' = lulu_job_id)
     * @throws \RuntimeException on API failure
     */
    public function createPrintJob(
        array $shippingAddress,
        string $ghlOrderId,
        int $quantity = 1
    ): array {
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

        Log::info('Lulu: Creating print job.', [
            'external_id' => $ghlOrderId,
            'environment' => $this->useSandbox ? 'sandbox' : 'production',
        ]);

        $response = Http::withoutVerifying()->withToken($token)
            ->post("{$this->baseUrl}/print-jobs/", $payload);

        if (! $response->successful()) {
            Log::error('Lulu: Print job creation failed.', [
                'status'      => $response->status(),
                'response'    => $response->json(),
                'external_id' => $ghlOrderId,
            ]);
            throw new \RuntimeException(
                'Lulu print job creation failed (HTTP ' . $response->status() . '): ' . $response->body()
            );
        }

        $data = $response->json();
        Log::info('Lulu: Print job created successfully.', [
            'lulu_job_id' => $data['id'] ?? 'unknown',
            'external_id' => $ghlOrderId,
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
                    'page_count'     => 200, // Approximate — update with real page count
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
