<?php

namespace NcooDev\HormLogger\Dtos;


use Illuminate\Http\Client\Response as SymfonyResponse;

class Response
{
    public function __construct(
        public array $headers,
        public int $status,
        public ?string $times,
    ) {}

    public static function fromHttpClientResponse(SymfonyResponse $response): self
    {
        return new self(
            headers: $response->headers(),
            status: $response->status(),
            times: $response->transferStats?->getTransferTime(),
        );
    }

    public static function fromHttpResponse(SymfonyResponse $response, ?int $times = null): self
    {
        return new self(
            headers: $response->headers->all(),
            status: $response->status(),
            times: $times,
        );

    }

    public static function fromDB(string $reponse): self
    {
        return unserialize(base64_decode($reponse));
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
