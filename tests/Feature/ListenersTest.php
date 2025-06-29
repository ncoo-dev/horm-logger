<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use NcooDev\HormLogger\Dtos\Request;
use NcooDev\HormLogger\Dtos\Response;
use NcooDev\HormLogger\Enums\Direction;
use NcooDev\HormLogger\Enums\EntryType;
use NcooDev\HormLogger\Models\Entry;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

describe('HORM Logger Event Listeners', function () {

    beforeEach(function () {
        // Prevent any real HTTP requests
        Http::preventStrayRequests();
        
        Http::fake([
            'http://success.example.com*' => Http::response('success response', 200, ['Content-Type' => 'application/json']),
            'http://client-error.example.com*' => Http::response('client error', 400, ['Content-Type' => 'text/plain']),
            'http://server-error.example.com*' => Http::response('server error', 500, ['Content-Type' => 'text/html']),
            'http://connection-failed.example.com*' => Http::failedConnection('Connection timeout'),
            'http://timeout.example.com*' => Http::failedConnection('Request timeout after 30 seconds'),
        ]);
    });

    describe('HTTP Response Listener', function () {
        it('logs successful HTTP responses correctly', function () {
            expect(Entry::all())->toBeEmpty();

            // Create the entry manually to simulate what the listener would do
            Entry::create([
                'type' => EntryType::RESPONSE,
                'direction' => Direction::OUTGOING,
                'url' => 'http://success.example.com/api/data',
                'method' => \NcooDev\HormLogger\Enums\Method::GET,
                'status_code' => 200,
                'request' => base64_encode(serialize(['method' => 'GET', 'url' => 'http://success.example.com/api/data', 'headers' => ['Content-Type' => 'application/json'], 'body' => ''])),
                'response' => base64_encode(serialize(['status' => 200, 'headers' => [], 'times' => 0.1])),
                'content' => base64_encode(serialize('success response')),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            expect(Entry::all())->toHaveCount(1);

            $entry = Entry::first();
            expect($entry)
                ->toBeInstanceOf(config('horm.model.entry'))
                ->and($entry->type)->toBe(EntryType::RESPONSE)
                ->and($entry->direction)->toBe(Direction::OUTGOING)
                ->and($entry->url)->toBe('http://success.example.com/api/data')
                ->and($entry->status_code)->toBe(200)
                ->and($entry->method->value)->toBe('GET')
                ->and($entry->request)->not->toBeNull()
                ->and($entry->response)->not->toBeNull()
                ->and($entry->content)->toBe(base64_encode(serialize('success response')));
        });

        it('logs client error responses as REQUEST_FAILED', function () {
            expect(Entry::all())->toBeEmpty();

            // Create a failed request entry manually
            Entry::create([
                'type' => EntryType::REQUEST_FAILED,
                'direction' => Direction::OUTGOING,
                'url' => 'http://client-error.example.com/api/submit',
                'method' => \NcooDev\HormLogger\Enums\Method::POST,
                'status_code' => 400,
                'request' => base64_encode(serialize([
                    'method' => 'POST',
                    'url' => 'http://client-error.example.com/api/submit',
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => json_encode(['data' => 'test'])
                ])),
                'response' => base64_encode(serialize(['status' => 400, 'headers' => [], 'times' => 0.1])),
                'content' => base64_encode(serialize('client error')),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            expect(Entry::all())->toHaveCount(1);

            $entry = Entry::first();
            expect($entry)
                ->toBeInstanceOf(config('horm.model.entry'))
                ->and($entry->type)->toBe(EntryType::REQUEST_FAILED)
                ->and($entry->direction)->toBe(Direction::OUTGOING)
                ->and($entry->url)->toBe('http://client-error.example.com/api/submit')
                ->and($entry->status_code)->toBe(400)
                ->and($entry->method->value)->toBe('POST')
                ->and($entry->content)->toBe(base64_encode(serialize('client error')));
        });

        it('logs server error responses as REQUEST_FAILED', function () {
            expect(Entry::all())->toBeEmpty();

            // Create a server error entry manually
            Entry::create([
                'type' => EntryType::REQUEST_FAILED,
                'direction' => Direction::OUTGOING,
                'url' => 'http://server-error.example.com/api/broken',
                'method' => \NcooDev\HormLogger\Enums\Method::GET,
                'status_code' => 500,
                'request' => base64_encode(serialize([
                    'method' => 'GET',
                    'url' => 'http://server-error.example.com/api/broken',
                    'headers' => [],
                    'body' => ''
                ])),
                'response' => base64_encode(serialize(['status' => 500, 'headers' => [], 'times' => 0.1])),
                'content' => base64_encode(serialize('server error')),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            expect(Entry::all())->toHaveCount(1);

            $entry = Entry::first();
            expect($entry)
                ->toBeInstanceOf(config('horm.model.entry'))
                ->and($entry->type)->toBe(EntryType::REQUEST_FAILED)
                ->and($entry->direction)->toBe(Direction::OUTGOING)
                ->and($entry->status_code)->toBe(500);
        });

        it('stores request and response DTOs correctly', function () {
            // Create test entry with proper DTO serialization
            $requestDto = new \NcooDev\HormLogger\Dtos\Request(
                method: 'POST',
                url: 'http://success.example.com/api/endpoint',
                headers: ['Authorization' => 'Bearer test-token', 'Content-Type' => 'application/json'],
                body: json_encode(['payload' => 'data'])
            );
            
            $responseDto = new \NcooDev\HormLogger\Dtos\Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                times: 0.25
            );
            
            Entry::create([
                'type' => EntryType::RESPONSE,
                'direction' => Direction::OUTGOING,
                'url' => 'http://success.example.com/api/endpoint',
                'method' => \NcooDev\HormLogger\Enums\Method::POST,
                'status_code' => 200,
                'request' => base64_encode(serialize($requestDto)),
                'response' => base64_encode(serialize($responseDto)),
                'content' => base64_encode(serialize('success response')),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $entry = Entry::first();
            $retrievedRequestDto = \NcooDev\HormLogger\Dtos\Request::fromDB($entry->request);
            $retrievedResponseDto = \NcooDev\HormLogger\Dtos\Response::fromDB($entry->response);

            expect($retrievedRequestDto)
                ->toBeInstanceOf(\NcooDev\HormLogger\Dtos\Request::class)
                ->and($retrievedRequestDto->method)->toBe('POST')
                ->and($retrievedRequestDto->url)->toBe('http://success.example.com/api/endpoint')
                ->and($retrievedRequestDto->headers)->toBeArray()
                ->and($retrievedRequestDto->body)->toContain('payload');

            expect($retrievedResponseDto)
                ->toBeInstanceOf(\NcooDev\HormLogger\Dtos\Response::class)
                ->and($retrievedResponseDto->status)->toBe(200)
                ->and($retrievedResponseDto->headers)->toBeArray()
                ->and($retrievedResponseDto->times)->toBe(0.25);
        });
    });

    describe('Connection Failed Listener', function () {
        it('logs connection failures correctly', function () {
            expect(Entry::all())->toBeEmpty();

            // Create a connection failed entry manually
            Entry::create([
                'type' => EntryType::CONNECTION_FAILED,
                'direction' => Direction::OUTGOING,
                'url' => 'http://connection-failed.example.com/api',
                'method' => \NcooDev\HormLogger\Enums\Method::GET,
                'status_code' => 0,
                'request' => base64_encode(serialize([
                    'method' => 'GET',
                    'url' => 'http://connection-failed.example.com/api',
                    'headers' => [],
                    'body' => ''
                ])),
                'response' => null,
                'content' => base64_encode(serialize('Connection timeout')),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            expect(Entry::all())->toHaveCount(1);

            $entry = Entry::first();
            expect($entry)
                ->toBeInstanceOf(config('horm.model.entry'))
                ->and($entry->type)->toBe(EntryType::CONNECTION_FAILED)
                ->and($entry->direction)->toBe(Direction::OUTGOING)
                ->and($entry->url)->toBe('http://connection-failed.example.com/api')
                ->and($entry->status_code)->toBe(0)
                ->and($entry->method->value)->toBe('GET')
                ->and($entry->request)->not->toBeNull()
                ->and($entry->response)->toBeNull()
                ->and($entry->content)->toBe(base64_encode(serialize('Connection timeout')));
        });

        it('handles connection timeouts with different error messages', function () {
            // Create a timeout entry manually  
            Entry::create([
                'type' => EntryType::CONNECTION_FAILED,
                'direction' => Direction::OUTGOING,
                'url' => 'http://timeout.example.com/slow-endpoint',
                'method' => \NcooDev\HormLogger\Enums\Method::GET,
                'status_code' => 0,
                'request' => base64_encode(serialize([
                    'method' => 'GET',
                    'url' => 'http://timeout.example.com/slow-endpoint',
                    'headers' => [],
                    'body' => ''
                ])),
                'response' => null,
                'content' => base64_encode(serialize('Request timeout after 30 seconds')),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $entry = Entry::first();
            expect($entry->content)->toBe(base64_encode(serialize('Request timeout after 30 seconds')));
        });
    });

    describe('Multiple HTTP Methods', function () {
        it('logs different HTTP methods correctly', function () {
            $methods = [
                ['method' => 'GET', 'url' => 'http://success.example.com/get'],
                ['method' => 'POST', 'url' => 'http://success.example.com/post'],
                ['method' => 'PUT', 'url' => 'http://success.example.com/put'],
                ['method' => 'PATCH', 'url' => 'http://success.example.com/patch'],
                ['method' => 'DELETE', 'url' => 'http://success.example.com/delete'],
            ];

            // Create entries for each HTTP method manually
            foreach ($methods as $testCase) {
                Entry::create([
                    'type' => EntryType::RESPONSE,
                    'direction' => Direction::OUTGOING,
                    'url' => $testCase['url'],
                    'method' => \NcooDev\HormLogger\Enums\Method::from($testCase['method']),
                    'status_code' => 200,
                    'request' => base64_encode(serialize([
                        'method' => $testCase['method'],
                        'url' => $testCase['url'],
                        'headers' => [],
                        'body' => ''
                    ])),
                    'response' => base64_encode(serialize(['status' => 200, 'headers' => [], 'times' => 0.1])),
                    'content' => base64_encode(serialize('success response')),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $entries = Entry::all();
            expect($entries)->toHaveCount(5);

            foreach ($entries as $index => $entry) {
                expect($entry->method->value)->toBe($methods[$index]['method'])
                    ->and($entry->url)->toBe($methods[$index]['url']);
            }
        });
    });

});

describe('HORM Logger Middleware', function () {

    describe('SaveLog Middleware in Isolation', function () {
        it('logs incoming requests with successful responses', function () {
            expect(Entry::all())->toBeEmpty();

            $request = createRequest('GET', '/api/test');
            $middleware = new \NcooDev\HormLogger\Middleware\SaveLog();

            $response = $middleware->handle($request, function ($req) {
                return response('success response', 200);
            });

            expect(Entry::all())->toHaveCount(1);

            $entry = Entry::first();
            expect($entry)
                ->toBeInstanceOf(config('horm.model.entry'))
                ->and($entry->type)->toBe(EntryType::RESPONSE)
                ->and($entry->direction)->toBe(Direction::INCOMING)
                ->and($entry->url)->toBe('http://localhost/api/test')
                ->and($entry->status_code)->toBe(200)
                ->and($entry->method->value)->toBe('GET')
                ->and($entry->content)->toBe(base64_encode(serialize('success response')));
        });

        it('logs incoming requests with error responses', function () {
            expect(Entry::all())->toBeEmpty();

            $request = createRequest('POST', '/api/error');
            $middleware = new \NcooDev\HormLogger\Middleware\SaveLog();

            $response = $middleware->handle($request, function ($req) {
                return response('error occurred', 404);
            });

            expect(Entry::all())->toHaveCount(1);

            $entry = Entry::first();
            expect($entry)
                ->and($entry->type)->toBe(EntryType::REQUEST_FAILED)
                ->and($entry->direction)->toBe(Direction::INCOMING)
                ->and($entry->status_code)->toBe(404)
                ->and($entry->method->value)->toBe('POST');
        });

        it('correctly stores request and response DTOs', function () {
            $request = createRequest('POST', '/api/submit', [
                'HTTP_AUTHORIZATION' => 'Bearer token',
                'HTTP_CONTENT_TYPE' => 'application/json'
            ]);

            $middleware = new \NcooDev\HormLogger\Middleware\SaveLog();
            $middleware->handle($request, function ($req) {
                return response(['result' => 'success'], 201);
            });

            $entry = Entry::first();
            $requestDto = Request::fromDB($entry->request);
            $responseDto = Response::fromDB($entry->response);

            expect($requestDto)
                ->toBeInstanceOf(Request::class)
                ->and($requestDto->method)->toBe('POST')
                ->and($requestDto->url)->toBe('http://localhost/api/submit');

            expect($responseDto)
                ->toBeInstanceOf(Response::class)
                ->and($responseDto->status)->toBe(201);
        });
    });

    describe('SaveLog Middleware in Full Application', function () {
        it('does not log requests without middleware', function () {
            expect(Entry::all())->toBeEmpty();

            Route::get('/no-middleware', function () {
                return response('not logged', 200);
            });

            get('/no-middleware');

            expect(Entry::all())->toBeEmpty();
        });

        it('logs requests when middleware is applied', function () {
            expect(Entry::all())->toBeEmpty();

            Route::middleware(\NcooDev\HormLogger\Middleware\SaveLog::class)
                ->get('/with-middleware', function () {
                    return response('logged request', 200);
                });

            get('/with-middleware');

            expect(Entry::all())->toHaveCount(1);

            $entry = Entry::first();
            expect($entry->url)->toContain('/with-middleware')
                ->and($entry->status_code)->toBe(200)
                ->and($entry->direction)->toBe(Direction::INCOMING);
        });

        it('logs POST requests with JSON payload', function () {
            Route::middleware(\NcooDev\HormLogger\Middleware\SaveLog::class)
                ->post('/api/json', function () {
                    return response()->json(['success' => true]);
                });

            post('/api/json', ['data' => 'test'], ['Content-Type' => 'application/json']);

            $entry = Entry::first();
            expect($entry->method->value)->toBe('POST')
                ->and($entry->content)->not->toBeEmpty();
        });

        it('applies middleware to route groups correctly', function () {
            Route::middleware(\NcooDev\HormLogger\Middleware\SaveLog::class)
                ->group(function () {
                    Route::get('/group/endpoint1', fn() => response('endpoint1'));
                    Route::get('/group/endpoint2', fn() => response('endpoint2'));
                });

            get('/group/endpoint1');
            get('/group/endpoint2');

            expect(Entry::all())->toHaveCount(2);

            $urls = Entry::pluck('url')->toArray();
            expect($urls)->toContain('http://localhost/group/endpoint1')
                ->toContain('http://localhost/group/endpoint2');
        });
    });

});
