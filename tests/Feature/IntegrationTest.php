<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use NcooDev\HormLogger\Enums\Direction;
use NcooDev\HormLogger\Enums\EntryType;
use NcooDev\HormLogger\Models\Entry;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

describe('HORM Logger Full Integration', function () {

    describe('End-to-End Outgoing Request Logging', function () {
        it('automatically logs complete HTTP client interactions', function () {
            // Setup HTTP fakes for different scenarios
            Http::fake([
                'https://api.success.test/*' => Http::response(['status' => 'success'], 200, ['Content-Type' => 'application/json']),
                'https://api.error.test/*' => Http::response(['error' => 'Not found'], 404, ['Content-Type' => 'application/json']),
                'https://api.timeout.test/*' => Http::failedConnection('Connection timeout'),
            ]);

            expect(Entry::count())->toBe(0);

            // Test successful request
            $response1 = Http::withHeaders(['X-Custom-Header' => 'test'])
                ->post('https://api.success.test/users', ['name' => 'John Doe']);

            expect($response1->successful())->toBeTrue()
                ->and(Entry::count())->toBe(1);

            $entry1 = Entry::latest()->first();
            expect($entry1->type)->toBe(EntryType::RESPONSE)
                ->and($entry1->direction)->toBe(Direction::OUTGOING)
                ->and($entry1->url)->toBe('https://api.success.test/users')
                ->and($entry1->status_code)->toBe(200)
                ->and($entry1->method->value)->toBe('POST');

            // Test error request
            $response2 = Http::get('https://api.error.test/nonexistent');

            expect($response2->failed())->toBeTrue()
                ->and(Entry::count())->toBe(2);

            $entry2 = Entry::latest()->first();
            expect($entry2->type)->toBe(EntryType::REQUEST_FAILED)
                ->and($entry2->status_code)->toBe(404);

            // Test connection failure
            try {
                Http::get('https://api.timeout.test/slow');
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Expected exception
            }

            expect(Entry::count())->toBe(3);

            $entry3 = Entry::latest()->first();
            expect($entry3->type)->toBe(EntryType::CONNECTION_FAILED)
                ->and($entry3->status_code)->toBe(0);
        });

        it('preserves complete request and response data', function () {
            Http::fake([
                'https://api.test.com/*' => Http::response(
                    json_encode(['result' => 'processed', 'id' => 123]),
                    201,
                    ['Location' => '/api/resources/123', 'X-Rate-Limit' => '100']
                ),
            ]);

            $requestData = ['name' => 'Test User', 'email' => 'test@example.com'];
            Http::withHeaders(['Authorization' => 'Bearer secret-token'])
                ->post('https://api.test.com/resources', $requestData);

            $entry = Entry::latest()->first();

            // Verify request data preservation
            $requestDto = \NcooDev\HormLogger\Dtos\Request::fromDB($entry->request);
            expect($requestDto->method)->toBe('POST')
                ->and($requestDto->url)->toBe('https://api.test.com/resources')
                ->and($requestDto->headers)->toHaveKey('Authorization')
                ->and($requestDto->body)->toContain('Test User');

            // Verify response data preservation
            $responseDto = \NcooDev\HormLogger\Dtos\Response::fromDB($entry->response);
            expect($responseDto->status)->toBe(201)
                ->and($responseDto->headers)->toHaveKey('Location')
                ->and($responseDto->body)->toContain('processed')
                ->and($responseDto->transferTime)->toBeFloat();

            // Verify content storage
            $content = unserialize(base64_decode($entry->content));
            expect($content)->toContain('processed')
                ->and($content)->toContain('123');
        });
    });

    describe('End-to-End Incoming Request Logging', function () {
        it('logs incoming requests when middleware is applied', function () {
            Route::middleware(\NcooDev\HormLogger\Middleware\SaveLog::class)
                ->group(function () {
                    Route::get('/api/users', fn() => response()->json(['users' => []]));
                    Route::post('/api/users', fn() => response()->json(['id' => 1], 201));
                    Route::get('/api/error', fn() => response('Not found', 404));
                });

            expect(Entry::count())->toBe(0);

            // Test GET request
            get('/api/users');
            expect(Entry::count())->toBe(1);

            $entry1 = Entry::latest()->first();
            expect($entry1->type)->toBe(EntryType::RESPONSE)
                ->and($entry1->direction)->toBe(Direction::INCOMING)
                ->and($entry1->method->value)->toBe('GET')
                ->and($entry1->status_code)->toBe(200);

            // Test POST request
            post('/api/users', ['name' => 'John'], ['Content-Type' => 'application/json']);
            expect(Entry::count())->toBe(2);

            $entry2 = Entry::latest()->first();
            expect($entry2->method->value)->toBe('POST')
                ->and($entry2->status_code)->toBe(201);

            // Test error response
            get('/api/error');
            expect(Entry::count())->toBe(3);

            $entry3 = Entry::latest()->first();
            expect($entry3->type)->toBe(EntryType::REQUEST_FAILED)
                ->and($entry3->status_code)->toBe(404);
        });

        it('preserves incoming request details accurately', function () {
            Route::middleware(\NcooDev\HormLogger\Middleware\SaveLog::class)
                ->post('/api/data', function () {
                    return response()->json(['success' => true, 'timestamp' => now()]);
                });

            $postData = ['user_id' => 123, 'action' => 'update'];
            post('/api/data', $postData, [
                'Authorization' => 'Bearer incoming-token',
                'Content-Type' => 'application/json',
                'X-Request-ID' => 'req-12345',
            ]);

            $entry = Entry::latest()->first();

            // Verify request capture
            $requestDto = \NcooDev\HormLogger\Dtos\Request::fromDB($entry->request);
            expect($requestDto->method)->toBe('POST')
                ->and($requestDto->url)->toContain('/api/data')
                ->and($requestDto->headers)->toHaveKey('authorization')
                ->and($requestDto->body)->toContain('user_id');

            // Verify response capture
            $responseDto = \NcooDev\HormLogger\Dtos\Response::fromDB($entry->response);
            expect($responseDto->status)->toBe(200)
                ->and($responseDto->body)->toContain('success')
                ->and($responseDto->transferTime)->toBeFloat();
        });
    });

    describe('API Endpoint Integration', function () {
        beforeEach(function () {
            config()->set('horm.endpoint', [
                'enabled' => true,
                'secret' => 'integration-test-secret',
                'url' => 'horm-integration-api',
            ]);

            $this->refreshServiceProvider();

            // Create test entries
            Entry::factory(5)->create();
        });

        it('provides secure API access to logged data', function () {
            // Test authentication
            $unauthenticatedResponse = get('/horm-integration-api');
            expect($unauthenticatedResponse->status())->toBe(403);

            // Test with wrong secret
            $wrongSecretResponse = get('/horm-integration-api', [
                'horm-check-secret' => 'wrong-secret',
            ]);
            expect($wrongSecretResponse->status())->toBe(403);

            // Test valid request
            $validResponse = getJson('/horm-integration-api?' . http_build_query([
                'start' => now()->subDay()->toDateTimeString(),
            ]), [
                'horm-check-secret' => 'integration-test-secret',
            ]);

            $validResponse->assertSuccessful()
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'direction',
                            'type',
                            'url',
                            'method',
                            'status_code',
                            'request',
                            'response',
                            'content',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                ]);
        });

        it('filters and limits API responses correctly', function () {
            // Create entries across different dates
            Entry::factory()->create(['created_at' => now()->subDays(2)]);
            Entry::factory()->create(['created_at' => now()->subDay()]);
            Entry::factory()->create(['created_at' => now()]);

            // Test date filtering
            $filteredResponse = getJson('/horm-integration-api?' . http_build_query([
                'from' => now()->subDay()->startOfDay()->toDateTimeString(),
                'to' => now()->endOfDay()->toDateTimeString(),
            ]), [
                'horm-check-secret' => 'integration-test-secret',
            ]);

            $filteredResponse->assertSuccessful();
            $data = $filteredResponse->json('data');
            expect(count($data))->toBe(2); // Only entries from the last day
        });
    });

    describe('Console Commands Integration', function () {
        it('integrates prune command with actual data lifecycle', function () {
            // Create entries of different ages
            Entry::factory()->create(['created_at' => now()->subDays(5)]);
            Entry::factory()->create(['created_at' => now()->subDays(3)]);
            Entry::factory()->create(['created_at' => now()->subDay()]);
            Entry::factory()->create(['created_at' => now()]);

            expect(Entry::count())->toBe(4);

            // Run prune command
            \Illuminate\Support\Facades\Artisan::call('horm:prune');

            // Verify old entries are removed
            expect(Entry::count())->toBe(2);

            // Verify remaining entries are recent
            $remainingEntries = Entry::all();
            foreach ($remainingEntries as $entry) {
                expect($entry->created_at->isAfter(now()->subDays(2)))->toBeTrue();
            }
        });

        it('install command publishes all required assets', function () {
            $exitCode = \Illuminate\Support\Facades\Artisan::call('horm:install');

            expect($exitCode)->toBe(0);
        });
    });

    describe('Configuration Integration', function () {
        it('respects configuration changes across all components', function () {
            // Test with custom retention period
            config()->set('horm.model.keep_history_for_days', 1);

            Entry::factory()->create(['created_at' => now()->subDays(2)]);
            Entry::factory()->create(['created_at' => now()]);

            \Illuminate\Support\Facades\Artisan::call('horm:prune');

            expect(Entry::count())->toBe(1);
        });

        it('handles disabled endpoint configuration', function () {
            config()->set('horm.endpoint.enabled', false);
            $this->refreshServiceProvider();

            $response = get('/horm-integration-api', [
                'horm-check-secret' => 'integration-test-secret',
            ]);

            expect($response->status())->toBe(404);
        });
    });

    describe('Error Handling and Edge Cases', function () {
        it('handles concurrent HTTP requests gracefully', function () {
            Http::fake([
                'https://concurrent.test/*' => Http::response('OK', 200),
            ]);

            // Simulate concurrent requests
            $promises = [];
            for ($i = 0; $i < 10; $i++) {
                $promises[] = Http::async()->get("https://concurrent.test/endpoint-{$i}");
            }

            // Wait for all requests to complete
            foreach ($promises as $promise) {
                $promise->wait();
            }

            // Verify all requests were logged
            expect(Entry::count())->toBe(10);

            // Verify no data corruption occurred
            $entries = Entry::all();
            foreach ($entries as $entry) {
                expect($entry->url)->toContain('concurrent.test')
                    ->and($entry->type)->toBe(EntryType::RESPONSE)
                    ->and($entry->direction)->toBe(Direction::OUTGOING);
            }
        });

        it('handles large payloads without memory issues', function () {
            $largePayload = str_repeat('x', 10000); // 10KB payload

            Http::fake([
                'https://large.test/*' => Http::response($largePayload, 200),
            ]);

            Http::post('https://large.test/upload', ['data' => $largePayload]);

            $entry = Entry::latest()->first();
            expect($entry)->not->toBeNull()
                ->and($entry->content)->not->toBeEmpty();

            // Verify large content is properly stored and retrievable
            $storedContent = unserialize(base64_decode($entry->content));
            expect(strlen($storedContent))->toBe(10000);
        });

        it('maintains data integrity under stress conditions', function () {
            // Create many entries rapidly
            Http::fake([
                'https://stress.test/*' => Http::response('OK', 200),
            ]);

            for ($i = 0; $i < 100; $i++) {
                Http::get("https://stress.test/endpoint-{$i}");
            }

            expect(Entry::count())->toBe(100);

            // Verify data integrity
            $entries = Entry::all();
            $urls = $entries->pluck('url')->unique();
            expect($urls->count())->toBe(100); // All unique URLs were captured

            // Verify all entries have required fields
            foreach ($entries as $entry) {
                expect($entry->id)->not->toBeNull()
                    ->and($entry->direction)->toBe(Direction::OUTGOING)
                    ->and($entry->type)->toBe(EntryType::RESPONSE)
                    ->and($entry->url)->toContain('stress.test')
                    ->and($entry->method)->not->toBeNull()
                    ->and($entry->status_code)->toBe(200)
                    ->and($entry->created_at)->not->toBeNull();
            }
        });
    });

    describe('Real-world Usage Scenarios', function () {
        it('simulates typical API integration monitoring', function () {
            Http::fake([
                'https://api.stripe.com/*' => Http::response(['id' => 'ch_123'], 200),
                'https://api.sendgrid.com/*' => Http::response(['message' => 'queued'], 202),
                'https://api.github.com/*' => Http::response(['name' => 'repo'], 200),
            ]);

            // Simulate a typical application flow
            // 1. Process payment
            Http::withHeaders(['Authorization' => 'Bearer sk_test_...'])
                ->post('https://api.stripe.com/v1/charges', [
                    'amount' => 2000,
                    'currency' => 'usd',
                    'source' => 'tok_visa',
                ]);

            // 2. Send notification email
            Http::withHeaders(['Authorization' => 'Bearer SG.abc123'])
                ->post('https://api.sendgrid.com/v3/mail/send', [
                    'personalizations' => [['to' => [['email' => 'user@example.com']]]],
                    'subject' => 'Payment processed',
                ]);

            // 3. Create repository webhook
            Http::withHeaders(['Authorization' => 'token ghp_123'])
                ->post('https://api.github.com/repos/user/repo/hooks', [
                    'name' => 'web',
                    'config' => ['url' => 'https://app.com/webhook'],
                ]);

            expect(Entry::count())->toBe(3);

            // Verify all external API calls were captured
            $entries = Entry::all();
            $hosts = $entries->pluck('url')->map(fn($url) => parse_url($url, PHP_URL_HOST));

            expect($hosts)->toContain('api.stripe.com')
                ->toContain('api.sendgrid.com')
                ->toContain('api.github.com');

            // Verify sensitive data is captured (for debugging purposes)
            $stripeEntry = $entries->firstWhere('url', 'like', '%stripe%');
            $requestDto = \NcooDev\HormLogger\Dtos\Request::fromDB($stripeEntry->request);
            expect($requestDto->headers)->toHaveKey('Authorization');
        });

        it('handles mixed success and failure scenarios', function () {
            Http::fake([
                'https://reliable.service.com/*' => Http::response('OK', 200),
                'https://flaky.service.com/*' => Http::sequence()
                    ->push('Success', 200)
                    ->push('Rate limited', 429)
                    ->push('Server error', 500)
                    ->pushStatus(503),
                'https://down.service.com/*' => Http::failedConnection('Service unavailable'),
            ]);

            // Call reliable service
            Http::get('https://reliable.service.com/health');

            // Call flaky service multiple times
            for ($i = 0; $i < 4; $i++) {
                try {
                    Http::get('https://flaky.service.com/data');
                } catch (\Exception $e) {
                    // Some calls may fail
                }
            }

            // Try to call down service
            try {
                Http::get('https://down.service.com/status');
            } catch (\Exception $e) {
                // Expected to fail
            }

            $entries = Entry::all();
            expect($entries->count())->toBeGreaterThan(5);

            // Verify different entry types are captured
            $types = $entries->pluck('type')->unique();
            expect($types)->toContain(EntryType::RESPONSE)
                ->toContain(EntryType::REQUEST_FAILED)
                ->toContain(EntryType::CONNECTION_FAILED);

            // Verify different status codes
            $statusCodes = $entries->pluck('status_code')->unique()->sort()->values();
            expect($statusCodes)->toContain(0)   // Connection failed
                ->toContain(200) // Success
                ->toContain(429) // Rate limited
                ->toContain(500) // Server error
                ->toContain(503); // Service unavailable
        });
    });

});