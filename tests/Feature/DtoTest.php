<?php

use Illuminate\Support\Facades\Http;
use NcooDev\HormLogger\Dtos\Request as RequestDto;
use NcooDev\HormLogger\Dtos\Response as ResponseDto;
use NcooDev\HormLogger\Models\Entry;

describe('HORM Logger DTOs', function () {

    beforeEach(function () {
        config(['horm.model.entry' => Entry::class]);
    });

    describe('Request DTO', function () {
        it('creates Request DTO from HTTP client request', function () {
            Http::fake([
                'https://example.com*' => Http::response('success', 200, ['Content-Type' => 'application/json']),
            ]);

            Http::withHeaders(['Authorization' => 'Bearer token'])
                ->post('https://example.com/api?param=value', ['data' => 'test']);

            $recorded = Http::recorded();
            [$httpClientRequest] = $recorded[0];

            $requestDto = RequestDto::fromHttpClientRequest($httpClientRequest);

            expect($requestDto)
                ->toBeInstanceOf(RequestDto::class)
                ->and($requestDto->method)->toBe('POST')
                ->and($requestDto->url)->toBe('https://example.com/api?param=value')
                ->and($requestDto->headers)->toBeArray()
                ->and($requestDto->body)->toBe('{"data":"test"}')
                ->and($requestDto->query)->toBeArray()
                ->and($requestDto->query['param'])->toBe('value');
        });

        it('creates Request DTO from Laravel HTTP request', function () {
            $httpRequest = createRequest('GET', '/api/test', [
                'HTTP_AUTHORIZATION' => 'Bearer token',
                'HTTP_CONTENT_TYPE' => 'application/json',
            ], ['param' => 'value']);

            $requestDto = RequestDto::fromHttpRequest($httpRequest);

            expect($requestDto)
                ->toBeInstanceOf(RequestDto::class)
                ->and($requestDto->method)->toBe('GET')
                ->and($requestDto->url)->toBe('http://localhost/api/test')
                ->and($requestDto->headers)->toBeArray()
                ->and($requestDto->body)->toBeString()
                ->and($requestDto->query)->toBeArray()
                ->and($requestDto->query['param'])->toBe('value');
        });

        it('serializes and deserializes Request DTO correctly', function () {
            $originalRequest = new RequestDto(
                method: 'POST',
                url: 'https://api.example.com/data',
                headers: ['Content-Type' => 'application/json', 'Authorization' => 'Bearer token'],
                body: '{"test": "data"}',
                query: ['param' => 'value']
            );

            $serialized = base64_encode(serialize($originalRequest));
            $deserialized = RequestDto::fromDB($serialized);

            expect($deserialized)
                ->toBeInstanceOf(RequestDto::class)
                ->and($deserialized->method)->toBe($originalRequest->method)
                ->and($deserialized->url)->toBe($originalRequest->url)
                ->and($deserialized->headers)->toBe($originalRequest->headers)
                ->and($deserialized->body)->toBe($originalRequest->body)
                ->and($deserialized->query)->toBe($originalRequest->query);
        });

        it('handles empty request body', function () {
            $httpRequest = createRequest('GET', '/api/test');
            $requestDto = RequestDto::fromHttpRequest($httpRequest);

            expect($requestDto->body)->toBeString();
        });
    });

    describe('Response DTO', function () {
        it('creates Response DTO from HTTP client response', function () {
            Http::fake([
                'https://example.com*' => Http::response(
                    '{"message": "success"}',
                    200,
                    ['Content-Type' => 'application/json', 'X-Custom' => 'header']
                ),
            ]);

            Http::get('https://example.com/api');

            $recorded = Http::recorded();
            [, $httpClientResponse] = $recorded[0];

            $responseDto = ResponseDto::fromHttpClientResponse($httpClientResponse);

            expect($responseDto)
                ->toBeInstanceOf(ResponseDto::class)
                ->and($responseDto->status)->toBe(200)
                ->and($responseDto->headers)->toBeArray()
                ->and($responseDto->times)->when(
                    ! is_null($responseDto->times),
                    fn ($expectation) => $expectation->toBeString()
                )
                ->and($responseDto->body)->toBe('{"message": "success"}');
        });

        it('creates Response DTO from Laravel HTTP response', function () {
            $httpResponse = createResponse('{"data": "test"}', 201, ['Content-Type' => 'application/json']);
            $responseDto = ResponseDto::fromHttpResponse($httpResponse, '0.5');

            expect($responseDto)
                ->toBeInstanceOf(ResponseDto::class)
                ->and($responseDto->status)->toBe(201)
                ->and($responseDto->headers)->toBeArray()
                ->and($responseDto->times)->toBe('0.5')
                ->and($responseDto->body)->toBe('{"data": "test"}');
        });

        it('serializes and deserializes Response DTO correctly', function () {
            $originalResponse = new ResponseDto(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                times: '1.25',
                body: '{"success": true}'
            );

            $serialized = base64_encode(serialize($originalResponse));
            $deserialized = ResponseDto::fromDB($serialized);

            expect($deserialized)
                ->toBeInstanceOf(ResponseDto::class)
                ->and($deserialized->status)->toBe($originalResponse->status)
                ->and($deserialized->headers)->toBe($originalResponse->headers)
                ->and($deserialized->times)->toBe($originalResponse->times)
                ->and($deserialized->body)->toBe($originalResponse->body);
        });

        it('handles different response status codes', function () {
            $testCases = [
                ['status' => 200, 'body' => 'OK'],
                ['status' => 404, 'body' => 'Not Found'],
                ['status' => 500, 'body' => 'Internal Server Error'],
            ];

            foreach ($testCases as $index => $case) {
                Http::fake([
                    "https://example{$index}.com*" => Http::response($case['body'], $case['status']),
                ]);

                $response = Http::get("https://example{$index}.com/test");
                $responseDto = ResponseDto::fromHttpClientResponse($response);

                expect($responseDto->status)->toBe($case['status']);
            }
        });

        it('handles empty response body', function () {
            Http::fake([
                'https://example.com*' => Http::response('', 204),
            ]);

            Http::get('https://example.com/empty');
            $recorded = Http::recorded();
            [, $response] = $recorded[0];

            $responseDto = ResponseDto::fromHttpClientResponse($response);

            expect($responseDto->status)->toBe(204);
        });
    });

    describe('Integration with Entry Model', function () {
        it('stores and retrieves DTOs through Entry model', function () {
            // Create a test entry manually to avoid HTTP client issues
            $entry = Entry::create([
                'type' => \NcooDev\HormLogger\Enums\EntryType::RESPONSE,
                'direction' => \NcooDev\HormLogger\Enums\Direction::OUTGOING,
                'url' => 'https://api.test.com/endpoint',
                'method' => \NcooDev\HormLogger\Enums\Method::GET,
                'status_code' => 200,
                'request' => base64_encode(serialize(new RequestDto(
                    method: 'GET',
                    url: 'https://api.test.com/endpoint',
                    headers: ['Content-Type' => 'application/json'],
                    body: null,
                    query: null
                ))),
                'response' => base64_encode(serialize(new ResponseDto(
                    status: 200,
                    headers: ['Content-Type' => 'application/json'],
                    times: '0.5',
                    body: '{"result": "success"}'
                ))),
                'content' => base64_encode(serialize('{"result": "success"}')),
            ]);

            $requestDto = RequestDto::fromDB($entry->request);
            $responseDto = ResponseDto::fromDB($entry->response);

            expect($requestDto)
                ->toBeInstanceOf(RequestDto::class)
                ->and($requestDto->method)->toBe('GET')
                ->and($requestDto->url)->toBe('https://api.test.com/endpoint');

            expect($responseDto)
                ->toBeInstanceOf(ResponseDto::class)
                ->and($responseDto->status)->toBe(200);
        });
    });

});
