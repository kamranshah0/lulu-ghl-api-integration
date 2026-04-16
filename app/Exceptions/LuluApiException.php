<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class LuluApiException extends Exception
{
    protected array $payload;
    protected ?string $responseBody;
    protected int $statusCode;
    protected string $url;

    public function __construct(
        string $message,
        int $statusCode,
        string $url,
        array $payload = [],
        ?string $responseBody = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        
        $this->statusCode = $statusCode;
        $this->url = $url;
        $this->payload = $payload;
        $this->responseBody = $responseBody;

        $this->logToLuluChannel();
    }

    /**
     * Log the detailed error to the dedicated lulu channel.
     */
    protected function logToLuluChannel(): void
    {
        Log::channel('lulu')->error('Lulu API Exception', [
            'message'     => $this->getMessage(),
            'status_code' => $this->statusCode,
            'url'         => $this->url,
            'payload'     => $this->payload,
            'response'    => $this->isJson($this->responseBody) 
                             ? json_decode($this->responseBody, true) 
                             : $this->responseBody,
        ]);
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    protected function isJson($string): bool
    {
        if (!is_string($string)) return false;
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
