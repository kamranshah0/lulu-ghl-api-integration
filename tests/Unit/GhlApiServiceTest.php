<?php

namespace Tests\Unit;

use App\Services\GhlApiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GhlApiServiceTest extends TestCase
{
    public function test_modern_contact_api_uses_version_header_and_custom_fields_array(): void
    {
        config(['services.ghl.api_version' => 'v3', 'services.ghl.api_key' => 'test-token']);
        Http::fake(['https://services.leadconnectorhq.com/*' => Http::response(['succeeded' => true])]);
        $this->assertTrue((new GhlApiService)->updateContactCustomField('contact', 'field', 'SHIPPED'));
        Http::assertSent(fn ($r) => $r->hasHeader('Version', 'v3') && $r['customFields'] === [['id' => 'field', 'fieldValue' => 'SHIPPED']]);
    }

    public function test_failed_custom_field_is_not_reported_as_success(): void
    {
        config(['services.ghl.api_key' => 'test-token', 'services.ghl.custom_field_id_status' => 'status-field', 'services.ghl.custom_field_id_job_id' => 'job-field']);
        Http::fake(['*' => Http::response(['message' => 'private response must not be echoed'], 401)]);
        try {
            (new GhlApiService)->updateContactFulfillmentStatus('contact', '123', 'CREATED');
            $this->fail('Expected authentication diagnostic.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('HTTP 401', $e->getMessage());
            $this->assertStringNotContainsString('private response', $e->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_missing_field_configuration_is_not_reported_as_success(): void
    {
        config(['services.ghl.api_key' => 'test-token', 'services.ghl.custom_field_id_status' => null, 'services.ghl.custom_field_id_job_id' => null]);
        try {
            (new GhlApiService)->updateContactFulfillmentStatus('contact', '123', 'CREATED');
            $this->fail('Expected missing configuration diagnostic.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('GHL_CUSTOM_FIELD_ID_STATUS', $e->getMessage());
            $this->assertStringContainsString('GHL_CUSTOM_FIELD_ID_JOB_ID', $e->getMessage());
            $this->assertStringNotContainsString('test-token', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_one_configured_field_is_not_treated_as_a_complete_sync(): void
    {
        config(['services.ghl.api_key' => 'test-token', 'services.ghl.custom_field_id_status' => 'status-field', 'services.ghl.custom_field_id_job_id' => ' ']);
        try {
            (new GhlApiService)->updateContactFulfillmentStatus('contact', '123', 'CREATED');
            $this->fail('Expected missing job field diagnostic.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('GHL_CUSTOM_FIELD_ID_JOB_ID', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_both_values_cannot_overwrite_the_same_field(): void
    {
        config(['services.ghl.api_key' => 'test-token', 'services.ghl.custom_field_id_status' => 'same', 'services.ghl.custom_field_id_job_id' => 'same']);
        $this->expectExceptionMessage('must use different custom field IDs');
        (new GhlApiService)->updateContactFulfillmentStatus('contact', '123', 'CREATED');
    }

    public function test_http_success_with_unsuccessful_body_is_rejected(): void
    {
        config(['services.ghl.api_key' => 'test-token']);
        Http::fake(['*' => Http::response(['succeeded' => false])]);
        $this->expectExceptionMessage('reported the custom field update as unsuccessful');
        (new GhlApiService)->updateContactCustomField('contact', 'field', 'SHIPPED');
    }

    public function test_connection_error_does_not_expose_request_details(): void
    {
        config(['services.ghl.api_key' => 'test-token']);
        Http::fake(fn () => throw new ConnectionException('private request details'));
        $this->expectExceptionMessage('GHL connection failed or timed out.');
        (new GhlApiService)->updateContactCustomField('contact', 'field', 'SHIPPED');
    }
}
