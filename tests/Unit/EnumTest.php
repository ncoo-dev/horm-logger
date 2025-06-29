<?php

use NcooDev\HormLogger\Enums\Direction;
use NcooDev\HormLogger\Enums\EntryType;
use NcooDev\HormLogger\Enums\Method;

describe('HORM Logger Enums', function () {

    describe('Direction Enum', function () {
        it('has correct values', function () {
            expect(Direction::INCOMING->value)->toBe('incoming')
                ->and(Direction::OUTGOING->value)->toBe('outgoing');
        });

        it('can be created from string values', function () {
            expect(Direction::from('incoming'))->toBe(Direction::INCOMING)
                ->and(Direction::from('outgoing'))->toBe(Direction::OUTGOING);
        });

        it('provides all available cases', function () {
            $cases = Direction::cases();

            expect($cases)->toHaveCount(2)
                ->and($cases[0])->toBe(Direction::INCOMING)
                ->and($cases[1])->toBe(Direction::OUTGOING);
        });

        it('can be used in match expressions', function () {
            $result = match (Direction::INCOMING) {
                Direction::INCOMING => 'request to app',
                Direction::OUTGOING => 'request from app',
            };

            expect($result)->toBe('request to app');
        });

        it('throws exception for invalid values', function () {
            expect(fn () => Direction::from('invalid'))
                ->toThrow(ValueError::class);
        });

        it('can try from string values safely', function () {
            expect(Direction::tryFrom('incoming'))->toBe(Direction::INCOMING)
                ->and(Direction::tryFrom('outgoing'))->toBe(Direction::OUTGOING)
                ->and(Direction::tryFrom('invalid'))->toBeNull();
        });

        it('is serializable', function () {
            $direction = Direction::INCOMING;
            $serialized = serialize($direction);
            $unserialized = unserialize($serialized);

            expect($unserialized)->toBe(Direction::INCOMING)
                ->and($unserialized->value)->toBe('incoming');
        });

        it('can be converted to string via value', function () {
            expect(Direction::INCOMING->value)->toBe('incoming')
                ->and(Direction::OUTGOING->value)->toBe('outgoing');
        });
    });

    describe('EntryType Enum', function () {
        it('has correct values', function () {
            expect(EntryType::RESPONSE->value)->toBe('response')
                ->and(EntryType::REQUEST_FAILED->value)->toBe('request_failed')
                ->and(EntryType::CONNECTION_FAILED->value)->toBe('connection_failed');
        });

        it('can be created from string values', function () {
            expect(EntryType::from('response'))->toBe(EntryType::RESPONSE)
                ->and(EntryType::from('request_failed'))->toBe(EntryType::REQUEST_FAILED)
                ->and(EntryType::from('connection_failed'))->toBe(EntryType::CONNECTION_FAILED);
        });

        it('provides all available cases', function () {
            $cases = EntryType::cases();

            expect($cases)->toHaveCount(3);

            $values = array_map(fn ($case) => $case->value, $cases);
            expect($values)->toContain('response')
                ->toContain('request_failed')
                ->toContain('connection_failed');
        });

        it('can be used to categorize HTTP status codes', function () {
            $getTypeFromStatus = function (int $statusCode): EntryType {
                return match (true) {
                    $statusCode === 0 => EntryType::CONNECTION_FAILED,
                    $statusCode >= 400 => EntryType::REQUEST_FAILED,
                    default => EntryType::RESPONSE,
                };
            };

            expect($getTypeFromStatus(200))->toBe(EntryType::RESPONSE)
                ->and($getTypeFromStatus(201))->toBe(EntryType::RESPONSE)
                ->and($getTypeFromStatus(404))->toBe(EntryType::REQUEST_FAILED)
                ->and($getTypeFromStatus(500))->toBe(EntryType::REQUEST_FAILED)
                ->and($getTypeFromStatus(0))->toBe(EntryType::CONNECTION_FAILED);
        });

        it('can be used in conditional logic', function () {
            $isSuccess = fn (EntryType $type) => $type === EntryType::RESPONSE;
            $isError = fn (EntryType $type) => in_array($type, [EntryType::REQUEST_FAILED, EntryType::CONNECTION_FAILED]);

            expect($isSuccess(EntryType::RESPONSE))->toBeTrue()
                ->and($isSuccess(EntryType::REQUEST_FAILED))->toBeFalse()
                ->and($isError(EntryType::REQUEST_FAILED))->toBeTrue()
                ->and($isError(EntryType::CONNECTION_FAILED))->toBeTrue()
                ->and($isError(EntryType::RESPONSE))->toBeFalse();
        });

        it('provides descriptive names for logging', function () {
            $getDescription = function (EntryType $type): string {
                return match ($type) {
                    EntryType::RESPONSE => 'Successful HTTP response',
                    EntryType::REQUEST_FAILED => 'HTTP request failed with error status',
                    EntryType::CONNECTION_FAILED => 'Network connection failed',
                };
            };

            expect($getDescription(EntryType::RESPONSE))->toBe('Successful HTTP response')
                ->and($getDescription(EntryType::REQUEST_FAILED))->toBe('HTTP request failed with error status')
                ->and($getDescription(EntryType::CONNECTION_FAILED))->toBe('Network connection failed');
        });

        it('can be compared for equality', function () {
            expect(EntryType::RESPONSE === EntryType::RESPONSE)->toBeTrue()
                ->and(EntryType::RESPONSE === EntryType::REQUEST_FAILED)->toBeFalse()
                ->and(EntryType::RESPONSE !== EntryType::REQUEST_FAILED)->toBeTrue();
        });

        it('is serializable', function () {
            $type = EntryType::REQUEST_FAILED;
            $serialized = serialize($type);
            $unserialized = unserialize($serialized);

            expect($unserialized)->toBe(EntryType::REQUEST_FAILED)
                ->and($unserialized->value)->toBe('request_failed');
        });

        it('can be converted to string via value', function () {
            expect(EntryType::RESPONSE->value)->toBe('response')
                ->and(EntryType::REQUEST_FAILED->value)->toBe('request_failed')
                ->and(EntryType::CONNECTION_FAILED->value)->toBe('connection_failed');
        });
    });

    describe('Method Enum', function () {
        it('has correct values', function () {
            expect(Method::GET->value)->toBe('GET')
                ->and(Method::POST->value)->toBe('POST')
                ->and(Method::PUT->value)->toBe('PUT')
                ->and(Method::PATCH->value)->toBe('PATCH')
                ->and(Method::DELETE->value)->toBe('DELETE')
                ->and(Method::HEAD->value)->toBe('HEAD')
                ->and(Method::OPTIONS->value)->toBe('OPTIONS');
        });

        it('can be created from string values', function () {
            expect(Method::from('GET'))->toBe(Method::GET)
                ->and(Method::from('POST'))->toBe(Method::POST)
                ->and(Method::from('PUT'))->toBe(Method::PUT)
                ->and(Method::from('PATCH'))->toBe(Method::PATCH)
                ->and(Method::from('DELETE'))->toBe(Method::DELETE);
        });

        it('provides all available cases', function () {
            $cases = Method::cases();

            expect($cases)->toHaveCount(7)
                ->and($cases[0])->toBe(Method::GET)
                ->and($cases[1])->toBe(Method::POST);
        });

        it('can be used in conditional logic', function () {
            $isReadOperation = fn (Method $method) => in_array($method, [Method::GET, Method::HEAD, Method::OPTIONS]);
            $isWriteOperation = fn (Method $method) => in_array($method, [Method::POST, Method::PUT, Method::PATCH, Method::DELETE]);

            expect($isReadOperation(Method::GET))->toBeTrue()
                ->and($isReadOperation(Method::POST))->toBeFalse()
                ->and($isWriteOperation(Method::POST))->toBeTrue()
                ->and($isWriteOperation(Method::GET))->toBeFalse();
        });

        it('is serializable', function () {
            $method = Method::POST;
            $serialized = serialize($method);
            $unserialized = unserialize($serialized);

            expect($unserialized)->toBe(Method::POST)
                ->and($unserialized->value)->toBe('POST');
        });
    });

    describe('Enum Integration', function () {
        it('can be used together in data structures', function () {
            $logEntry = [
                'direction' => Direction::OUTGOING,
                'type' => EntryType::RESPONSE,
                'url' => 'https://api.example.com',
                'status' => 200,
            ];

            expect($logEntry['direction'])->toBe(Direction::OUTGOING)
                ->and($logEntry['type'])->toBe(EntryType::RESPONSE);
        });

        it('can be filtered and grouped', function () {
            $entries = [
                ['direction' => Direction::INCOMING, 'type' => EntryType::RESPONSE],
                ['direction' => Direction::OUTGOING, 'type' => EntryType::RESPONSE],
                ['direction' => Direction::OUTGOING, 'type' => EntryType::REQUEST_FAILED],
                ['direction' => Direction::INCOMING, 'type' => EntryType::REQUEST_FAILED],
            ];

            $outgoingEntries = array_filter($entries, fn ($entry) => $entry['direction'] === Direction::OUTGOING);
            $successfulEntries = array_filter($entries, fn ($entry) => $entry['type'] === EntryType::RESPONSE);

            expect(count($outgoingEntries))->toBe(2)
                ->and(count($successfulEntries))->toBe(2);
        });

        it('maintains type safety in arrays', function () {
            $validDirections = [Direction::INCOMING, Direction::OUTGOING];
            $validTypes = [EntryType::RESPONSE, EntryType::REQUEST_FAILED, EntryType::CONNECTION_FAILED];

            foreach ($validDirections as $direction) {
                expect($direction)->toBeInstanceOf(Direction::class);
            }

            foreach ($validTypes as $type) {
                expect($type)->toBeInstanceOf(EntryType::class);
            }
        });
    });

    describe('Database Integration', function () {
        it('can be stored and retrieved from database correctly', function () {
            $entry = \NcooDev\HormLogger\Models\Entry::factory()->create([
                'direction' => Direction::INCOMING,
                'type' => EntryType::REQUEST_FAILED,
            ]);

            $retrievedEntry = \NcooDev\HormLogger\Models\Entry::find($entry->id);

            expect($retrievedEntry->direction)->toBe(Direction::INCOMING)
                ->and($retrievedEntry->type)->toBe(EntryType::REQUEST_FAILED)
                ->and($retrievedEntry->direction)->toBeInstanceOf(Direction::class)
                ->and($retrievedEntry->type)->toBeInstanceOf(EntryType::class);
        });

        it('can be queried using enum values', function () {
            // Clear any existing entries first
            \NcooDev\HormLogger\Models\Entry::query()->delete();

            \NcooDev\HormLogger\Models\Entry::factory()->create(['direction' => Direction::INCOMING]);
            \NcooDev\HormLogger\Models\Entry::factory()->create(['direction' => Direction::OUTGOING]);
            \NcooDev\HormLogger\Models\Entry::factory()->create(['type' => EntryType::RESPONSE]);
            \NcooDev\HormLogger\Models\Entry::factory()->create(['type' => EntryType::REQUEST_FAILED]);

            $incomingCount = \NcooDev\HormLogger\Models\Entry::where('direction', Direction::INCOMING)->count();
            $outgoingCount = \NcooDev\HormLogger\Models\Entry::where('direction', Direction::OUTGOING)->count();
            $responseCount = \NcooDev\HormLogger\Models\Entry::where('type', EntryType::RESPONSE)->count();
            $failedCount = \NcooDev\HormLogger\Models\Entry::where('type', EntryType::REQUEST_FAILED)->count();

            expect($incomingCount)->toBeGreaterThanOrEqual(1)
                ->and($outgoingCount)->toBeGreaterThanOrEqual(1)
                ->and($responseCount)->toBeGreaterThanOrEqual(1)
                ->and($failedCount)->toBeGreaterThanOrEqual(1);
        });
    });

});
