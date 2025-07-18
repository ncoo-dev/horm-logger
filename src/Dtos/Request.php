<?php

namespace NcooDev\HormLogger\Dtos;

class Request
{
    public function __construct(
        public array $headers,
        public string $method,
        public string $url,
        public ?string $body,
        public ?array $query = null,
    ) {}

    public static function fromHttpClientRequest(\Illuminate\Http\Client\Request $request): self
    {
        $url = parse_url($request->url());
        $query = null;
        if (isset($url['query'])) {
            parse_str($url['query'], $query);
        }

        return new self(
            headers: $request->headers(),
            method: $request->method(),
            url: $request->url(),
            body: $request->body(),
            query: $query,
        );
    }

    public static function fromHttpRequest(\Illuminate\Http\Request $request): self
    {
        return new self(
            headers: $request->headers->all(),
            method: $request->method(),
            url: $request->url(),
            body: $request->getContent(),
            query: $request->query->all(),
        );
    }

    public static function fromDB(string $request): self
    {
        $info = unserialize(base64_decode($request));
        if (is_array($info)) {
            return new self(
                headers: $info['headers'] ?? [],
                method: $info['method'] ?? 'GET',
                url: $info['url'] ?? '',
                body: $info['body'] ?? null,
                query: $info['query'] ?? null,
            );
        }

        // If it's an object, access properties directly
        return new self(
            headers: $info->headers ?? [],
            method: $info->method ?? 'GET',
            url: $info->url ?? '',
            body: $info->body ?? null,
            query: $info->query ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'headers' => $this->headers,
            'method' => $this->method,
            'url' => $this->url,
            'body' => $this->body,
            'query' => $this->query,
        ];
    }
}
