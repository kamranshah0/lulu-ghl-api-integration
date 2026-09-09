<?php

namespace Tests\Unit;

use App\Services\GhlApiService;
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
        Http::fake(['*' => Http::sequence()->push([], 401)->push([], 200)]);
        $this->assertFalse((new GhlApiService)->updateContactFulfillmentStatus('contact', '123', 'CREATED'));
        Http::assertSentCount(2);
    }

    public function test_missing_field_configuration_is_not_reported_as_success(): void
    {
        config(['services.ghl.custom_field_id_status' => null, 'services.ghl.custom_field_id_job_id' => null]);
        $this->assertFalse((new GhlApiService)->updateContactFulfillmentStatus('contact', '123', 'CREATED'));
        Http::assertNothingSent();
    }
}
