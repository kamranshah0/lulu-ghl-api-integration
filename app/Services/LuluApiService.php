<?php

namespace App\Services;

use App\Exceptions\LuluApiException;
use Illuminate\Http\Client\Response;
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
        $this->useSandbox = config('services.lulu.use_sandbox', true);
        $this->clientKey = trim((string) config('services.lulu.client_key'));
        $this->clientSecret = trim((string) config('services.lulu.client_secret'));
        $this->baseUrl = $this->useSandbox
            ? config('services.lulu.sandbox_api_base', 'https://api.sandbox.lulu.com')
            : config('services.lulu.api_base', 'https://api.lulu.com');
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function environment(): string
    {
        return $this->useSandbox ? 'sandbox' : 'production';
    }

    public function clearAccessToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    private function tokenCacheKey(): string
    {
        return 'lulu_token_'.$this->environment().'_'.hash('sha256', $this->baseUrl.'|'.$this->clientKey.'|'.$this->clientSecret);
    }

    public function getAccessToken(): string
    {
        $tokenUrl = rtrim($this->baseUrl, '/').'/auth/realms/glasstree/protocol/openid-connect/token';
        if ($this->clientKey === '' || $this->clientSecret === '') {
            throw new LuluApiException('Lulu '.$this->environment().' credentials are missing. Configure LULU_CLIENT_KEY and LULU_CLIENT_SECRET.', 0, $tokenUrl);
        }
        if ($token = Cache::get($this->tokenCacheKey())) {
            return $token;
        }

        $response = Http::connectTimeout(10)->timeout(30)->acceptJson()->asForm()
            ->withBasicAuth($this->clientKey, $this->clientSecret)
            ->post($tokenUrl, ['grant_type' => 'client_credentials']);

        if (! $response->successful()) {
            // Keep only OAuth error fields; never persist credentials or tokens from a response.
            $body = json_encode([
                'error' => $this->redact((string) $response->json('error', 'authentication_failed')),
                'error_description' => $this->redact((string) $response->json('error_description', 'No OAuth error description returned.')),
            ]);
            throw new LuluApiException(
                'Lulu '.$this->environment().' authentication failed (HTTP '.$response->status().'). Verify credentials from the matching Lulu developer portal and restart workers after configuration changes.',
                $response->status(), $tokenUrl, responseBody: $body
            );
        }
        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new LuluApiException('Lulu token response did not contain an access token.', $response->status(), $tokenUrl);
        }
        $ttl = max(1, (int) $response->json('expires_in', 3600) - 60);
        Cache::put($this->tokenCacheKey(), $token, $ttl);

        return $token;
    }

    private function redact(string $message): string
    {
        return str_replace(array_filter([$this->clientKey, $this->clientSecret, base64_encode($this->clientKey.':'.$this->clientSecret)]), '[redacted]', $message);
    }

    private function request(string $method, string $path, array $data = []): Response
    {
        $url = rtrim($this->baseUrl, '/').$path;
        $send = fn () => Http::connectTimeout(10)->timeout(30)->acceptJson()->withToken($this->getAccessToken())
            ->send($method, $url, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        $response = $send();
        // An explicit 401 is safe to retry. Never automatically repeat an ambiguous POST.
        if ($response->status() === 401) {
            $this->clearAccessToken();
            $response = $send();
        }

        return $response;
    }

    /*
    |--------------------------------------------------------------------------
    | Print Job Creation
    |--------------------------------------------------------------------------
    */

    public function createPrintJob(array $shippingAddress, string $ghlOrderId, int $quantity = 1): array
    {
        $this->validatePrintConfiguration();
        $payload = [
            'contact_email' => config('services.lulu.contact_email'),
            'external_id' => $ghlOrderId,
            'line_items' => [
                [
                    'title' => 'Forever Wellthy Book',
                    'printable_normalization' => [
                        'cover' => ['source_url' => config('services.lulu.book_cover_url')],
                        'interior' => ['source_url' => config('services.lulu.book_interior_url')],
                        'pod_package_id' => $this->podPackageId(),
                    ],
                    'quantity' => $quantity,
                ],
            ],
            'shipping_address' => $shippingAddress,
            'shipping_level' => config('services.lulu.shipping_level', 'MAIL'),
        ];

        Log::channel('lulu')->info('Lulu: Submitting print job.', [
            'external_id' => $ghlOrderId,
            'payload' => $payload,
        ]);

        $response = $this->request('POST', '/print-jobs/', $payload);

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
            'job_id' => $data['id'] ?? 'unknown',
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
        $response = $this->request('GET', '/print-jobs/'.rawurlencode($luluJobId).'/');

        if (! $response->successful()) {
            Log::error('Lulu: Failed to fetch print job status.', [
                'lulu_job_id' => $luluJobId,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Failed to fetch Lulu job status: '.$response->body());
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
        if ((int) config('services.lulu.book_page_count') < 1) {
            throw new \InvalidArgumentException('LULU_BOOK_PAGE_COUNT must match the final interior PDF.');
        }
        $payload = [
            'line_items' => [
                [
                    'pod_package_id' => $this->podPackageId(),
                    'quantity' => $quantity,
                    'page_count' => (int) config('services.lulu.book_page_count', 60),
                ],
            ],
            'shipping_address' => $shippingAddress,
            'shipping_option' => config('services.lulu.shipping_level', 'MAIL'),
        ];

        $response = $this->request('POST', '/print-job-cost-calculations/', $payload);

        if (! $response->successful()) {
            Log::warning('Lulu: Cost calculation failed (non-blocking).', [
                'status' => $response->status(),
                'payload' => $payload,
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

        if (! is_array($value)) {
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
        return trim((string) config('services.lulu.pod_package_id'));
    }

    public function validatePrintConfiguration(): void
    {
        foreach (['book_cover_url', 'book_interior_url'] as $key) {
            $url = (string) config('services.lulu.'.$key);
            if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
                throw new \InvalidArgumentException('Lulu '.$key.' must be a downloadable HTTPS PDF URL.');
            }
        }
        if ($this->podPackageId() === '' || ! filter_var(config('services.lulu.contact_email'), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Lulu POD package ID and contact email must be configured.');
        }
    }
}
