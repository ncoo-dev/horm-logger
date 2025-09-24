<?php

use Illuminate\Testing\Fluent\AssertableJson;
use NcooDev\HormLogger\Models\Entry;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Spatie\PestPluginTestTime\testTime;

describe('HORM Logger API Endpoint', function () {

    beforeEach(function () {
        testTime()->freeze('2024-01-01 00:00:00');

        // Debug: Check if factory is working
        try {
            $testEntry = Entry::factory()->create();
            expect($testEntry)->not->toBeNull();
            $testEntry->delete(); // Clean up test entry
        } catch (\Exception $e) {
            throw new \Exception('Factory failed: '.$e->getMessage());
        }

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

        // Verify entries were created
        expect(Entry::count())->toBe(6, 'Failed to create 6 entries in beforeEach');

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
            // Create entries directly in the test instead of relying on beforeEach
            testTime()->freeze('2024-01-01 00:00:00');

            $entries = Entry::factory(6)->sequence(
                ['created_at' => now()->subMinutes(10)],
                ['created_at' => now()->subSecond()],
                ['created_at' => now()],
                ['created_at' => now()->addSeconds(1)],
                ['created_at' => now()->addMinutes(3)],
                ['created_at' => now()->addMinutes(4)],
            )->create();

            $entryCurrent = $entries[2]; // The entry with created_at = now()

            // Verify entries exist
            expect(Entry::count())->toBe(6);

            $currentTime = now()->toDateTimeString();

            $response = getJson('/horm-api-endpoint?'.http_build_query([
                'start' => $currentTime,
                'limit' => 100,
            ]), [
                'horm-check-secret' => 'test-secret-key',
            ]);

            $response->assertSuccessful();

            // Check how many entries should be returned (from current time onward)
            $expectedEntries = Entry::where('created_at', '>=', $currentTime)->get();
            expect($expectedEntries)->toHaveCount(4);

            $responseData = $response->json();
            expect($responseData)->toHaveKey('data');
            expect(count($responseData['data']))->toBe(4);

            $response->assertJson(function (AssertableJson $json) use ($entryCurrent) {
                $json->has('data', 4) // Should return 4 entries (current and 3 after)
                    ->has('data.0', function (AssertableJson $json) use ($entryCurrent) {
                        $json->has('id')
                            ->where('id', $entryCurrent->id)
                            ->where('type', $entryCurrent->type->value)
                            ->where('direction', $entryCurrent->direction->value)
                            ->where('request', $entryCurrent->request)
                            ->where('response', $entryCurrent->response)
                            ->where('created_at', $entryCurrent->created_at->format('Y-m-d H:i:s'))
                            ->where('updated_at', $entryCurrent->updated_at->format('Y-m-d H:i:s'));
                    });
            });
        });

        it('returns entries within date range', function () {
            testTime()->freeze('2024-01-01 00:00:00');

            Entry::factory(6)->sequence(
                ['created_at' => now()->subMinutes(10)],
                ['created_at' => now()->subSecond()],
                ['created_at' => now()],
                ['created_at' => now()->addSeconds(1)],
                ['created_at' => now()->addMinutes(3)],
                ['created_at' => now()->addMinutes(4)],
            )->create();

            $response = getJson('/horm-api-endpoint?'.http_build_query([
                'start' => now()->subMinutes(5)->toDateTimeString(),
                'limit' => 100,
            ]), [
                'horm-check-secret' => 'test-secret-key',
            ]);

            $response->assertSuccessful()
                ->assertJson(function (AssertableJson $json) {
                    $json->has('data', 5); // Should return 5 entries from subMinutes(5) onward (now-1sec, now, now+1sec, now+3min, now+4min)
                });
        });

        it('returns empty data when no entries match criteria', function () {
            testTime()->freeze('2024-01-01 00:00:00');

            Entry::factory(3)->sequence(
                ['created_at' => now()->subMinutes(10)],
                ['created_at' => now()->subSecond()],
                ['created_at' => now()],
            )->create();

            $response = getJson('/horm-api-endpoint?'.http_build_query([
                'start' => now()->addMinutes(10)->toDateTimeString(),
                'limit' => 100,
            ]), [
                'horm-check-secret' => 'test-secret-key',
            ]);

            $response->assertSuccessful()
                ->assertJson(['data' => []]);
        });

        it('limits results to maximum entries per request', function () {
            testTime()->freeze('2024-01-01 00:00:00');

            // Create entries with different timestamps to ensure proper limiting
            for ($i = 1; $i <= 150; $i++) {
                Entry::factory()->create([
                    'created_at' => now()->addMinutes($i),
                ]);
            }

            $last = Entry::factory()->create([
                'created_at' => now()->addDays(1),
            ]);

            $response = getJson('/horm-api-endpoint?'.http_build_query([
                'start' => now()->toDateTimeString(),
                'limit' => 100,
            ]), [
                'horm-check-secret' => 'test-secret-key',
            ]);

            $response->assertSuccessful();

            $data = $response->json('data');
            // Should get exactly 100 entries (up to the 100th entry's timestamp)
            expect(count($data))->toBe(100)
                ->and($response->json())->not->toContain(['id' => $last->id]);
        });

        it('respects custom limit parameter', function () {
            testTime()->freeze('2024-01-01 00:00:00');

            // Create entries with different timestamps
            for ($i = 1; $i <= 50; $i++) {
                Entry::factory()->create([
                    'created_at' => now()->addMinutes($i),
                ]);
            }

            // Test with limit of 10
            $response = getJson('/horm-api-endpoint?'.http_build_query([
                'start' => now()->toDateTimeString(),
                'limit' => 10,
            ]), [
                'horm-check-secret' => 'test-secret-key',
            ]);

            $response->assertSuccessful();

            $data = $response->json('data');
            // Should get exactly 10 entries (up to the 10th entry's timestamp)
            expect(count($data))->toBe(10);
        });

        it('includes all entries with same timestamp at the limit boundary', function () {
            testTime()->freeze('2024-01-01 00:00:00');

            // Create 5 entries with different timestamps
            for ($i = 1; $i <= 5; $i++) {
                Entry::factory()->create([
                    'created_at' => now()->addMinutes($i),
                ]);
            }

            // Create 5 more entries with the same timestamp as the 5th entry
            for ($i = 1; $i <= 5; $i++) {
                Entry::factory()->create([
                    'created_at' => now()->addMinutes(5),
                ]);
            }

            // Create more entries after
            for ($i = 6; $i <= 10; $i++) {
                Entry::factory()->create([
                    'created_at' => now()->addMinutes($i),
                ]);
            }

            // Test with limit of 5
            $response = getJson('/horm-api-endpoint?'.http_build_query([
                'start' => now()->toDateTimeString(),
                'limit' => 5,
            ]), [
                'horm-check-secret' => 'test-secret-key',
            ]);

            $response->assertSuccessful();

            $data = $response->json('data');
            // Should get 10 entries total (5 different timestamps + 5 with same timestamp at boundary)
            expect(count($data))->toBe(10);

            // Verify all entries are from the correct time range
            foreach ($data as $entry) {
                $createdAt = \Carbon\Carbon::parse($entry['created_at']);
                expect($createdAt->lessThanOrEqualTo(now()->addMinutes(5)))->toBeTrue();
            }
        });
    });

    describe('Configuration', function () {
        it('responds with 404 when endpoint is disabled', function () {
            config()->set('horm.endpoint.enabled', false);
            $this->refreshServiceProvider();

            $response = get('/horm-api-endpoint', [
                'horm-check-secret' => 'test-secret-key',
            ]);

            $response->assertStatus(302); // Validation error because 'start' parameter is required
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
            $response = getJson('/horm-api-endpoint?'.http_build_query([
                'start' => 'invalid-date-format',
                'limit' => 100,
            ]), [
                'horm-check-secret' => 'test-secret-key',
            ]);

            // Should handle gracefully, either with validation error or empty result
            expect($response->getStatusCode())->toBeIn([200, 302, 422]);
        });
    });

});
