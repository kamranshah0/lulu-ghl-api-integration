<?php

namespace Tests\Unit;

use App\Exceptions\LuluApiException;
use App\Services\LuluApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LuluAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.lulu.use_sandbox' => false,
            'services.lulu.client_key' => 'test-key', 'services.lulu.client_secret' => 'test-secret',
            'services.lulu.api_base' => 'https://api.lulu.com',
            'services.lulu.pod_package_id' => '0600X0900.BW.STD.PB.060UC444.GXX',
            'services.lulu.book_cover_url' => 'https://example.com/cover.pdf',
            'services.lulu.book_interior_url' => 'https://example.com/interior.pdf',
            'services.lulu.contact_email' => 'admin@example.com',
            'services.lulu.book_page_count' => 60,
            'services.lulu.shipping_level' => 'MAIL',
        ]);
        Cache::flush();
    }

    public function test_basic_auth_and_token_expiry(): void
    {
        Http::fake(['*/token' => Http::sequence()->push(['access_token' => 'first', 'expires_in' => 120])->push(['access_token' => 'second', 'expires_in' => 120])]);
        $api = new LuluApiService;
        $this->assertSame('first', $api->getAccessToken());
        $this->assertSame('first', $api->getAccessToken());
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Basic '.base64_encode('test-key:test-secret')) && $r['grant_type'] === 'client_credentials' && ! isset($r['client_secret']));
        $this->travel(61)->seconds();
        $this->assertSame('second', $api->getAccessToken());
    }

    public function test_rotating_credentials_does_not_reuse_old_token(): void
    {
        Http::fake(['*/token' => Http::sequence()->push(['access_token' => 'first'])->push(['access_token' => 'second'])]);
        $this->assertSame('first', (new LuluApiService)->getAccessToken());
        config(['services.lulu.client_secret' => 'rotated-secret']);
        $this->assertSame('second', (new LuluApiService)->getAccessToken());
        Http::assertSentCount(2);
    }

    public function test_auth_error_preserves_oauth_reason_without_secrets(): void
    {
        Http::fake(['*/token' => Http::response(['error' => 'invalid_client', 'error_description' => 'Invalid test-secret'], 401)]);
        try {
            (new LuluApiService)->getAccessToken();
            $this->fail('Expected authentication failure.');
        } catch (LuluApiException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertStringContainsString('production', $e->getMessage());
            $this->assertStringContainsString('invalid_client', $e->getResponseBody());
            $this->assertStringNotContainsString('test-secret', $e->getResponseBody());
        }
    }

    public function test_missing_credentials_and_malformed_token_fail_clearly(): void
    {
        config(['services.lulu.client_key' => null]);
        try {
            (new LuluApiService)->getAccessToken();
            $this->fail('Expected missing credentials failure.');
        } catch (LuluApiException $e) {
            $this->assertStringContainsString('credentials are missing', $e->getMessage());
        }
        Http::assertNothingSent();
        config(['services.lulu.client_key' => 'test-key']);
        Http::fake(['*/token' => Http::response(['expires_in' => 3600])]);
        $this->expectException(LuluApiException::class);
        (new LuluApiService)->getAccessToken();
    }

    public function test_print_payload_and_cost_payload_match_the_documented_contract(): void
    {
        Http::fake([
            '*/token' => Http::response(['access_token' => 'test-token']),
            '*/print-jobs/' => Http::response(['id' => 123]),
            '*/print-job-cost-calculations/' => Http::response(['shipping_cost' => 5]),
        ]);
        $api = new LuluApiService;
        $api->createPrintJob([], 'ORDER-1', 2);
        $api->calculateCost([], 2);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/print-jobs/') && $r['line_items'][0]['printable_normalization'] === [
            'cover' => ['source_url' => 'https://example.com/cover.pdf'],
            'interior' => ['source_url' => 'https://example.com/interior.pdf'],
            'pod_package_id' => '0600X0900.BW.STD.PB.060UC444.GXX',
        ] && $r['shipping_level'] === 'MAIL');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/print-job-cost-calculations/') && $r['shipping_option'] === 'MAIL' && ! isset($r['shipping_level']) && $r['line_items'][0]['page_count'] === 60);
    }

    public function test_401_refreshes_once_but_500_never_repeats_creation(): void
    {
        Http::fake([
            '*/token' => Http::sequence()->push(['access_token' => 'old'])->push(['access_token' => 'new']),
            '*/print-jobs/123/' => Http::sequence()->push([], 401)->push(['id' => 123]),
            '*/print-jobs/' => Http::response([], 500),
        ]);
        $api = new LuluApiService;
        $this->assertSame(123, $api->getPrintJobStatus('123')['id']);
        try {
            $api->createPrintJob([], 'ORDER-1');
            $this->fail('Expected failure.');
        } catch (LuluApiException $e) {
            $this->assertSame(500, $e->getStatusCode());
        }
        Http::assertSentCount(5);
    }
}
