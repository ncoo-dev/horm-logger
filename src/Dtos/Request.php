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

    public static function fromDB(string $request): self
    {
        $info = unserialize(base64_decode($request));
        if (is_array($info)) {
            return new self(
                headers: $info['headers'],
                method: $info['method'],
                url: $info['url'],
                body: $info['body'],
            );
        }

        // If it's an object, access properties directly
        return new self(
            headers: $info->headers,
            method: $info->method,
            url: $info->url,
            body: $info->body,
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
