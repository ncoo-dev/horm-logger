<?php

use Illuminate\Testing\Fluent\AssertableJson;
use NcooDev\HormLogger\Models\Entry;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Spatie\PestPluginTestTime\testTime;

describe('HORM Logger API Endpoint', function () {

    beforeEach(function () {
        testTime()->freeze('2024-01-01 00:00:00');

        // Create test entries with specific timestamps
        [
            $this->entryOld,
            $this->entryJustBefore,
            $this->entryCurrent,
            $this->entryAfter1,
            $this->entryAfter2,
            $this->entryAfter3,
        ] = Entry::factory(6)->sequence(
            ['created_at' => now()->subMinutes(10)],
            ['created_at' => now()->subSecond()],
            ['created_at' => now()],
            ['created_at' => now()->addSeconds(1)],
            ['created_at' => now()->addMinutes(3)],
            ['created_at' => now()->addMinutes(4)],
        )->create();

        // Configuration is already set in TestCase, just refresh the service provider
        $this->refreshServiceProvider();
    });

    describe('Authentication', function () {
        it('denies access with invalid secret', function ($secret, $expectedStatus) {
            $response = get('/horm-api-endpoint', [
                'horm-check-secret' => $secret,
            ]);

            $response->assertStatus($expectedStatus);
        })->with([
            'wrong secret' => ['wrong-secret', 403],
            'empty secret' => ['', 403],
            'null secret' => [null, 403],
        ]);

        it('allows access with valid secret but requires parameters', function () {
            $response = get('/horm-api-endpoint', [
                'horm-check-secret' => 'test-secret-key',
            ]);

            // Should redirect because 'start' parameter is missing
            $response->assertStatus(302);
        });
    });

    describe('Data Retrieval', function () {
        it('returns entries from specified start time', function () {
            $response = getJson('/horm-api-endpoint?' . http_build_query([
                'start' => now()->toDateTimeString(),
            ]), [
                'horm-check-secret' => 'test-secret-key',
            ]);

            $response->assertSuccessful()
                ->assertJson(function (AssertableJson $json) {
                    $json->has('data', 4) // Should return 4 entries (current and 3 after)
                        ->has('data.0', function (AssertableJson $json) {
                            $json->has('id')
                                ->where('id', $this->entryCurrent->id)
                                ->where('type', $this->entryCurrent->type->value)
                                ->where('direction', $this->entryCurrent->direction->value)
                                ->where('url', $this->entryCurrent->url)
                                ->where('status_code', $this->entryCurrent->status_code)
                                ->where('method', $this->entryCurrent->method->value)
                                ->where('request', $this->entryCurrent->request)
                                ->where('response', $this->entryCurrent->response)
                                ->where('content', $this->entryCurrent->content)
                                ->where('created_at', $this->entryCurrent->created_at->format('Y-m-d H:i:s'))
                                ->where('updated_at', $this->entryCurrent->updated_at->format('Y-m-d H:i:s'));
                        });
                });
        });

        it('returns entries within date range', function () {
            $response = getJson('/horm-api-endpoint?' . http_build_query([
                'from' => now()->subMinutes(5)->toDateTimeString(),
                'to' => now()->addMinutes(2)->toDateTimeString(),
            ]), [
                'horm-check-secret' => 'test-secret-key',
            ]);

            $response->assertSuccessful()
                ->assertJson(function (AssertableJson $json) {
                    $json->has('data', 2); // Should return 2 entries within range
                });
        });

        it('returns empty data when no entries match criteria', function () {
            $response = getJson('/horm-api-endpoint?' . http_build_query([
                'start' => now()->addMinutes(10)->toDateTimeString(),
            ]), [
                'horm-check-secret' => 'test-secret-key',
            ]);

            $response->assertSuccessful()
                ->assertJson(['data' => []]);
        });

        it('limits results to maximum entries per request', function () {
            // Create more entries than the limit
            Entry::factory(1500)->create([
                'created_at' => now()->addMinutes(1),
            ]);

            $response = getJson('/horm-api-endpoint?' . http_build_query([
                'start' => now()->toDateTimeString(),
            ]), [
                'horm-check-secret' => 'test-secret-key',
            ]);

            $response->assertSuccessful()
                ->assertJson(function (AssertableJson $json) {
                    $json->has('data', 1000); // Should be limited to 1000 entries
                });
        });
    });

    describe('Configuration', function () {
        it('responds with 404 when endpoint is disabled', function () {
            config()->set('horm.endpoint.enabled', false);
            $this->refreshServiceProvider();

            $response = get('/horm-api-endpoint', [
                'horm-check-secret' => 'test-secret-key',
            ]);

            $response->assertStatus(404);
        });

        it('uses custom endpoint URL from configuration', function () {
            config()->set('horm.endpoint.url', 'custom-horm-endpoint');
            $this->refreshServiceProvider();

            $response = get('/custom-horm-endpoint', [
                'horm-check-secret' => 'test-secret-key',
            ]);

            // Should not be 404, indicating the custom URL is working
            $response->assertStatus(302); // Missing parameters, but endpoint exists
        });
    });

    describe('Error Handling', function () {
        it('handles invalid date formats gracefully', function () {
            $response = getJson('/horm-api-endpoint?' . http_build_query([
                'start' => 'invalid-date-format',
            ]), [
                'horm-check-secret' => 'test-secret-key',
            ]);

            // Should handle gracefully, either with validation error or empty result
            expect($response->getStatusCode())->toBeIn([200, 302, 422]);
        });
    });

});
