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
                    'pod_package_id' => $this->podPackageId(),
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
                    'pod_package_id' => $this->podPackageId(),
                    'quantity'       => $quantity,
                    'cover'          => config('services.lulu.book_cover_url'),
                    'interior'       => config('services.lulu.book_interior_url'),
                    'page_count'     => (int) config('services.lulu.book_page_count', 60),
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
                'payload'  => $payload,
                'response' => $response->json() ?? $response->body(),
            ]);
            throw new LuluApiException(
                message: "Lulu cost calculation failed (HTTP {$response->status()})",
                statusCode: $response->status(),
                url: "{$this->baseUrl}/print-job-cost-calculations/",
                payload: $payload,
                responseBody: $response->body()
            );
        }

        $data = $response->json();
        Log::channel('lulu')->info('Lulu: Cost calculation completed.', [
            'cost_response' => $data,
        ]);

        return $data;
    }

    public static function extractCostBreakdown(array $costResponse): array
    {
        $costs = $costResponse['costs'][0] ?? $costResponse['costs'] ?? $costResponse;
        $firstLineItem = $costResponse['line_item_costs'][0]
            ?? $costResponse['line_items'][0]
            ?? $costs['line_item_costs'][0]
            ?? [];

        $printCost = self::moneyValue($costs['print_cost'] ?? null)
            ?? self::moneyValue($firstLineItem['print_cost'] ?? null)
            ?? self::moneyValue($firstLineItem['total_cost_excl_tax'] ?? null)
            ?? self::moneyValue($firstLineItem['total_cost_incl_tax'] ?? null)
            ?? self::moneyValue($costs['line_item_cost'] ?? null)
            ?? self::moneyValue($costs['total_print_cost'] ?? null);

        $shippingCost = self::moneyValue($costs['shipping_cost'] ?? null)
            ?? self::moneyValue($costResponse['shipping_cost'] ?? null)
            ?? self::moneyValue($costs['shipping'] ?? null)
            ?? self::moneyValue($costResponse['shipping'] ?? null)
            ?? self::moneyValue($costs['total_shipping_cost'] ?? null);

        return [
            'print_cost' => $printCost,
            'shipping_cost' => $shippingCost,
        ];
    }

    private static function moneyValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = preg_replace('/[^0-9.\-]/', '', $value);
            return is_numeric($normalized) ? (float) $normalized : null;
        }

        if (!is_array($value)) {
            return null;
        }

        foreach ([
            'amount',
            'value',
            'total',
            'total_cost_excl_tax',
            'total_cost_incl_tax',
            'cost_excl_tax',
            'cost_incl_tax',
            'excl_tax',
            'incl_tax',
        ] as $key) {
            $money = self::moneyValue($value[$key] ?? null);

            if ($money !== null) {
                return $money;
            }
        }

        return null;
    }

    private function podPackageId(): string
    {
        $configured = (string) config('services.lulu.pod_package_id');
        $normalized = preg_replace('/[^A-Za-z0-9]/', '', $configured) ?: $configured;

        if ($configured !== $normalized) {
            Log::channel('lulu')->info('Lulu: Normalized POD package ID for API payload.', [
                'configured' => $configured,
                'normalized' => $normalized,
            ]);
        }

        return $normalized;
    }
}
