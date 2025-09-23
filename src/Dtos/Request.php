<?php

namespace NcooDev\HormLogger\Dtos;

class Request
{
    public function __construct(
        public array $headers,
        public string $method,
        public string $url,
        public mixed $body,
    ) {}

    public static function fromHttpClientRequest(\Illuminate\Http\Client\Request $request): self
    {
        $body = $request->body();
        if (is_string($body) && ($decoded = json_decode($body, true)) !== null) {
            $body = $decoded;
        }

        return new self(
            headers: $request->headers(),
            method: $request->method(),
            url: $request->url(),
            body: $body,
        );
    }

    public static function fromHttpRequest(\Illuminate\Http\Request $request): self
    {
        $body = $request->all();
        if (empty($body)) {
            $body = $request->getContent();
            if (is_string($body) && ($decoded = json_decode($body, true)) !== null) {
                $body = $decoded;
            }
        }

        return new self(
            headers: $request->headers->all(),
            method: $request->method(),
            url: $request->url(),
            body: $body,
        );
    }

    public static function fromDB($request): self
    {
        // Handle both JSON string and array
        if (is_string($request)) {
            $info = json_decode($request, true);
        } else {
            $info = $request;
        }

        if (is_null($info)) {
            return new self(
                headers: [],
                method: '',
                url: '',
                body: null,
            );
        }

        return new self(
            headers: $info['headers'] ?? [],
            method: $info['method'] ?? '',
            url: $info['url'] ?? '',
            body: $info['body'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'headers' => $this->headers,
            'method' => $this->method,
            'url' => $this->url,
            'body' => $this->body,
        ];
    }
}
