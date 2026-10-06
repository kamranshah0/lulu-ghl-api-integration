<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

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
        if (trim($this->apiKey) === '' || trim($contactId) === '' || trim($fieldId) === '') {
            throw new \RuntimeException('GHL update requires an API key, contact ID and custom field ID.');
        }

        $body = $this->version === 'legacy' ? [
            'customField' => [
                $fieldId => $value,
            ],
        ] : ['customFields' => [['id' => $fieldId, 'fieldValue' => $value]]];
        try {
            $response = $this->request()->put("{$this->baseUrl}/contacts/".rawurlencode($contactId), $body);
        } catch (ConnectionException) {
            throw new \RuntimeException('GHL connection failed or timed out. Check network connectivity and retry status sync; do not resubmit the Lulu order.');
        }

        if (! $response->successful()) {
            $hint = match ($response->status()) {
                401 => 'Check the GHL token and matching API version.',
                403 => 'Check token permissions and access to the contact sub-account.',
                404 => 'Check the API version, contact and custom field belong to the intended sub-account.',
                400, 422 => 'Check custom field IDs, field types and allowed status values.',
                429 => 'GHL rate limit reached. A later status sync can retry.',
                default => 'Check GHL service availability and retry status sync.',
            };
            throw new \RuntimeException('GHL custom field update failed (HTTP '.$response->status().'). '.$hint);
        }

        if ($response->json('succeeded') === false || $response->json('success') === false) {
            throw new \RuntimeException('GHL returned HTTP '.$response->status().' but reported the custom field update as unsuccessful. Check field configuration in GHL.');
        }

        return true;
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

        $missing = [];
        foreach (['GHL_API_KEY' => $this->apiKey, 'GHL_CUSTOM_FIELD_ID_STATUS' => $statusFieldId, 'GHL_CUSTOM_FIELD_ID_JOB_ID' => $jobIdFieldId] as $name => $value) {
            if (trim((string) $value) === '') {
                $missing[] = $name;
            }
        }
        if ($missing) {
            throw new \RuntimeException('GHL sync configuration missing: '.implode(', ', $missing).'. Configure the deployment environment, refresh config and restart workers.');
        }
        if ($statusFieldId === $jobIdFieldId) {
            throw new \RuntimeException('GHL status and Lulu job ID must use different custom field IDs.');
        }

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
