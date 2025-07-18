<?php

namespace NcooDev\HormLogger\Dtos;

use Illuminate\Http\JsonResponse;

class Response
{
    public function __construct(
        public array $headers,
        public int $status,
        public ?string $times,
        public ?string $body = null,
    ) {}

    public static function fromHttpClientResponse(\Illuminate\Http\Response|\Illuminate\Http\Client\Response|JsonResponse $response): self
    {
        return new self(
            headers: $response->headers(),
            status: $response->status(),
            times: $response->transferStats?->getTransferTime(),
            body: $response->body(),
        );
    }

    public static function fromHttpResponse(\Illuminate\Http\Response|\Illuminate\Http\Client\Response|JsonResponse $response, ?string $times = null): self
    {
        return new self(
            headers: $response->headers->all(),
            status: $response->status(),
            times: $times,
            body: $response->getContent(),
        );
    }

    public static function fromDB(string $reponse): self
    {
        $info = unserialize(base64_decode($reponse));

        if (is_array($info)) {
            return new self(
                headers: $info['headers'] ?? [],
                status: $info['status'] ?? 200,
                times: $info['times'] ?? null,
                body: $info['body'] ?? null,
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
            'times' => $this->times,
            'body' => $this->body,
        ];
    }
}
