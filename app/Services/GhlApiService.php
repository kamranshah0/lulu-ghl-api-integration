<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GhlApiService
{
    private string $apiKey;

    private string $locationId;

    private string $baseUrl = 'https://rest.gohighlevel.com/v1';

    private string $version;

    public function __construct()
    {
        $this->apiKey = (string) config('services.ghl.api_key');
        $this->locationId = (string) config('services.ghl.location_id');
        $this->version = (string) config('services.ghl.api_version', 'legacy');
        if ($this->version !== 'legacy') {
            $this->baseUrl = 'https://services.leadconnectorhq.com';
        }
    }

    /**
     * Update a GHL contact's custom field to reflect Lulu order status.
     *
     * @param  string  $contactId  GHL Contact ID
     * @param  string  $fieldId  GHL Custom Field ID (e.g. 3sv6UEo51C9B...)
     * @param  mixed  $value  The value to set
     */
    public function updateContactCustomField(string $contactId, string $fieldId, $value): bool
    {
        if (empty($this->apiKey) || empty($contactId) || empty($fieldId)) {
            return false;
        }

        $body = $this->version === 'legacy' ? [
            'customField' => [
                $fieldId => $value,
            ],
        ] : ['customFields' => [['id' => $fieldId, 'fieldValue' => $value]]];
        $response = $this->request()->put("{$this->baseUrl}/contacts/".rawurlencode($contactId), $body);

        if (! $response->successful()) {
            Log::warning('GHL: Failed to update custom field.', [
                'contact_id' => $contactId,
                'field_id' => $fieldId,
                'response' => $response->json(),
            ]);
        }

        return $response->successful() && $response->json('succeeded') !== false && $response->json('success') !== false;
    }

    /**
     * Backward compatibility wrapper for the status sync command.
     */
    public function updateContactFulfillmentStatus(
        string $contactId,
        string $luluJobId,
        string $status
    ): bool {
        $statusFieldId = config('services.ghl.custom_field_id_status');
        $jobIdFieldId = config('services.ghl.custom_field_id_job_id');

        $results = [];
        if ($statusFieldId) {
            $results[] = $this->updateContactCustomField($contactId, $statusFieldId, $status);
        }
        if ($jobIdFieldId) {
            $results[] = $this->updateContactCustomField($contactId, $jobIdFieldId, $luluJobId);
        }

        return count($results) > 0 && ! in_array(false, $results, true);
    }

    /**
     * Add a note to a GHL contact's timeline (for order history).
     */
    public function addContactNote(string $contactId, string $noteBody): bool
    {
        if (empty($this->apiKey) || empty($contactId)) {
            return false;
        }

        $response = $this->request()->post("{$this->baseUrl}/contacts/".rawurlencode($contactId).'/notes', [
            'body' => $noteBody,
        ]);

        return $response->successful() && $response->json('success') !== false;
    }

    private function request(): PendingRequest
    {
        $request = Http::connectTimeout(5)->timeout(10)->acceptJson()->withToken($this->apiKey);
        if ($this->version !== 'legacy') {
            $request->withHeaders(['Version' => $this->version]);
        }

        return $request;
    }
}
