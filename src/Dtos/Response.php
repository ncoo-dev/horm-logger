<?php

namespace NcooDev\HormLogger\Dtos;

use Illuminate\Http\JsonResponse;

class Response
{
    public function __construct(
        public array $headers,
        public int $status,
        public ?float $times,
    ) {}

    public static function fromHttpClientResponse(\Illuminate\Http\Response|\Illuminate\Http\Client\Response|JsonResponse $response): self
    {
        return new self(
            headers: $response->headers(),
            status: $response->status(),
            times: $response->transferStats?->getTransferTime(),
        );
    }

    public static function fromHttpResponse(\Illuminate\Http\Response|\Illuminate\Http\Client\Response|JsonResponse $response, ?float $times = null): self
    {
        return new self(
            headers: $response->headers->all(),
            status: $response->status(),
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
                times: $info['times'],
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
        ];
    }
}
