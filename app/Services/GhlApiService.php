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
     * This is optional — use it to sync status back into GHL for CRM visibility.
     *
     * @param  string $contactId   GHL Contact ID from webhook payload
     * @param  string $luluJobId   The Lulu job ID to record
     * @param  string $status      Human-readable status string
     */
    public function updateContactFulfillmentStatus(
        string $contactId,
        string $luluJobId,
        string $status
    ): bool {
        if (empty($this->apiKey) || empty($contactId)) {
            Log::warning('GHL: API key or contact ID missing. Skipping GHL update.');
            return false;
        }

        // You need to create a custom field in GHL named "Lulu Job ID" and "Fulfillment Status"
        // and put their custom_field_ids below
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type'  => 'application/json',
        ])->put("{$this->baseUrl}/contacts/{$contactId}", [
            'customField' => [
                // Replace these keys with your actual GHL custom field IDs
                'lulu_job_id'         => $luluJobId,
                'fulfillment_status'  => $status,
            ],
        ]);

        if (! $response->successful()) {
            Log::warning('GHL: Failed to update contact.', [
                'contact_id' => $contactId,
                'status'     => $response->status(),
                'response'   => $response->json(),
            ]);
            return false;
        }

        Log::info('GHL: Contact updated with Lulu job status.', [
            'contact_id'  => $contactId,
            'lulu_job_id' => $luluJobId,
            'status'      => $status,
        ]);

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
