<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GhlApiService
{
    private string $apiKey;
    private string $locationId;
    private string $baseUrl = 'https://rest.gohighlevel.com/v1';

    public function __construct()
    {
        $this->apiKey     = config('services.ghl.api_key');
        $this->locationId = config('services.ghl.location_id');
    }

    /**
     * Update a GHL contact's custom field to reflect Lulu order status.
     *
     * @param  string $contactId   GHL Contact ID
     * @param  string $fieldId     GHL Custom Field ID (e.g. 3sv6UEo51C9B...)
     * @param  mixed  $value       The value to set
     */
    public function updateContactCustomField(string $contactId, string $fieldId, $value): bool
    {
        if (empty($this->apiKey) || empty($contactId) || empty($fieldId)) {
            return false;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type'  => 'application/json',
        ])->put("{$this->baseUrl}/contacts/{$contactId}", [
            'customField' => [
                $fieldId => $value,
            ],
        ]);

        if (! $response->successful()) {
            Log::warning('GHL: Failed to update custom field.', [
                'contact_id' => $contactId,
                'field_id'   => $fieldId,
                'response'   => $response->json(),
            ]);
        }

        return $response->successful();
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
        $jobIdFieldId  = config('services.ghl.custom_field_id_job_id');

        if ($statusFieldId) {
            $this->updateContactCustomField($contactId, $statusFieldId, $status);
        }

        if ($jobIdFieldId) {
            $this->updateContactCustomField($contactId, $jobIdFieldId, $luluJobId);
        }

        return true;
    }

    /**
     * Add a note to a GHL contact's timeline (for order history).
     */
    public function addContactNote(string $contactId, string $noteBody): bool
    {
        if (empty($this->apiKey) || empty($contactId)) {
            return false;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type'  => 'application/json',
        ])->post("{$this->baseUrl}/contacts/{$contactId}/notes", [
            'body' => $noteBody,
        ]);

        return $response->successful();
    }
}
