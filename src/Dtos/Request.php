<?php

namespace NcooDev\HormLogger\Dtos;

class Request
{
    public function __construct(
        public array $headers,
        public string $method,
        public string $url,
        public ?string $body,
    ) {}

    public static function fromHttpClientRequest(\Illuminate\Http\Client\Request $request): self
    {
        return new self(
            headers: $request->headers(),
            method: $request->method(),
            url: $request->url(),
            body: $request->body(),
        );
    }

    public static function fromHttpRequest(\Illuminate\Http\Request $request): self
    {
        return new self(
            headers: $request->headers->all(),
            method: $request->method(),
            url: $request->url(),
            body: $request->getContent(),
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

        return new self(
            headers: $info->headers,
            method: $info->method,
            url: $info->url,
            body: $info->getContent(),
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
