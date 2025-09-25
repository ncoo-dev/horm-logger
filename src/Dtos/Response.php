<?php

namespace NcooDev\HormLogger\Dtos;

use Illuminate\Http\JsonResponse;

class Response
{
    public function __construct(
        public array $headers,
        public int $status,
        public mixed $body,
        public ?float $times,
    ) {}

    public static function fromHttpClientResponse(\Illuminate\Http\Response|\Illuminate\Http\Client\Response|JsonResponse $response): self
    {
        $body = $response->body();
        
        // Ensure UTF-8 encoding
        if (is_string($body)) {
            $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
            if (($decoded = json_decode($body, true)) !== null) {
                $body = $decoded;
            }
        }

        return new self(
            headers: self::sanitizeHeaders($response->headers()),
            status: $response->status(),
            body: self::sanitizeData($body),
            times: $response->transferStats?->getTransferTime(),
        );
    }

    public static function fromHttpResponse(\Illuminate\Http\Response|\Illuminate\Http\Client\Response|JsonResponse $response, ?float $times = null): self
    {
        $body = $response->getContent();
        
        // Ensure UTF-8 encoding
        if (is_string($body)) {
            $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
            if (($decoded = json_decode($body, true)) !== null) {
                $body = $decoded;
            }
        }

        return new self(
            headers: self::sanitizeHeaders($response->headers->all()),
            status: $response->status(),
            body: self::sanitizeData($body),
            times: $times,
        );
    }

    public static function fromDB($response): self
    {
        // Handle both JSON string and array
        if (is_string($response)) {
            $info = json_decode($response, true);
        } else {
            $info = $response;
        }

        if (is_null($info)) {
            return new self(
                headers: [],
                status: 0,
                body: null,
                times: null,
            );
        }

        return new self(
            headers: $info['headers'] ?? [],
            status: $info['status'] ?? 0,
            body: $info['body'] ?? null,
            times: $info['times'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'headers' => $this->headers,
            'status' => $this->status,
            'body' => $this->body,
            'times' => $this->times,
        ];
    }

    /**
     * Sanitize data to ensure UTF-8 encoding
     */
    private static function sanitizeData(mixed $data): mixed
    {
        if (is_string($data)) {
            // Remove invalid UTF-8 characters
            $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            // Remove non-printable characters except for newlines and tabs
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $data);
            return $data;
        }
        
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeData'], $data);
        }
        
        return $data;
    }

    /**
     * Sanitize headers to ensure UTF-8 encoding
     */
    private static function sanitizeHeaders(array $headers): array
    {
        return array_map(function ($value) {
            if (is_array($value)) {
                return array_map([self::class, 'sanitizeData'], $value);
            }
            return self::sanitizeData($value);
        }, $headers);
    }
}
