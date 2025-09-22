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
        if (is_string($body) && ($decoded = json_decode($body, true)) !== null) {
            $body = $decoded;
        }

        return new self(
            headers: $response->headers(),
            status: $response->status(),
            body: $body,
            times: $response->transferStats?->getTransferTime(),
        );
    }

    public static function fromHttpResponse(\Illuminate\Http\Response|\Illuminate\Http\Client\Response|JsonResponse $response, ?float $times = null): self
    {
        $body = $response->getContent();
        if (is_string($body) && ($decoded = json_decode($body, true)) !== null) {
            $body = $decoded;
        }

        return new self(
            headers: $response->headers->all(),
            status: $response->status(),
            body: $body,
            times: $times,
        );
    }

    public static function fromDB(string $reponse): self
    {
        $info = unserialize(base64_decode($reponse));

        if (is_array($info)) {
            return new self(
                headers: $info['headers'],
                status: $info['status'],
                body: $info['body'] ?? null,
                times: $info['times'] ?? null,
            );
        }

        // If it's already an object instance, return it
        return $info;
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
}
