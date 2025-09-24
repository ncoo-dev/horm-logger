<?php

use Carbon\Carbon;
use NcooDev\HormLogger\Enums\Direction;
use NcooDev\HormLogger\Enums\EntryType;
use NcooDev\HormLogger\Models\Entry;

use function Spatie\PestPluginTestTime\testTime;

describe('Entry Model', function () {

    describe('Factory and Creation', function () {
        it('can create entries using factory', function () {
            $entry = Entry::factory()->create();

            expect($entry)
                ->toBeInstanceOf(Entry::class)
                ->and($entry->id)->toBeString()
                ->and($entry->direction)->toBeInstanceOf(Direction::class)
                ->and($entry->type)->toBeInstanceOf(EntryType::class)
                ->and($entry->request)->not->toBeNull()
                ->and($entry->response)->not->toBeNull()
                ->and($entry->created_at)->toBeInstanceOf(Carbon::class)
                ->and($entry->updated_at)->toBeInstanceOf(Carbon::class);
        });

        it('can create entries with specific attributes', function () {
            $entry = Entry::factory()->create([
                'direction' => Direction::INCOMING,
                'type' => EntryType::RESPONSE,
            ]);

            expect($entry->direction)->toBe(Direction::INCOMING)
                ->and($entry->type)->toBe(EntryType::RESPONSE);
        });

        it('can create multiple entries in sequence', function () {
            $entries = Entry::factory(3)->sequence(
                ['type' => EntryType::RESPONSE],
                ['type' => EntryType::REQUEST_FAILED],
                ['type' => EntryType::CONNECTION_FAILED],
            )->create();

            expect($entries)->toHaveCount(3)
                ->and($entries[0]->type)->toBe(EntryType::RESPONSE)
                ->and($entries[1]->type)->toBe(EntryType::REQUEST_FAILED)
                ->and($entries[2]->type)->toBe(EntryType::CONNECTION_FAILED);
        });
    });

    describe('Attributes and Casting', function () {
        it('casts direction to enum correctly', function () {
            $entry = Entry::factory()->make(['direction' => 'incoming']);

            expect($entry->direction)
                ->toBeInstanceOf(Direction::class)
                ->and($entry->direction->value)->toBe('incoming');
        });

        it('casts type to enum correctly', function () {
            $entry = Entry::factory()->make(['type' => 'response']);

            expect($entry->type)
                ->toBeInstanceOf(EntryType::class)
                ->and($entry->type->value)->toBe('response');
        });

        it('handles nullable response field', function () {
            $entry = Entry::factory()->make(['response' => null]);

            expect($entry->response)->toBeNull();
        });

        it('stores JSON data in request field', function () {
            $testData = ['method' => 'GET', 'headers' => ['Content-Type' => 'application/json']];

            $entry = Entry::factory()->create(['request' => $testData]);

            expect($entry->request)->toBe($testData);
        });
    });

    describe('Database Interactions', function () {
        it('can be saved and retrieved from database', function () {
            $originalEntry = Entry::factory()->create();

            $retrievedEntry = Entry::find($originalEntry->id);

            expect($retrievedEntry)
                ->not->toBeNull()
                ->and($retrievedEntry->id)->toBe($originalEntry->id);
        });

        it('can be updated', function () {
            $entry = Entry::factory()->create(['type' => EntryType::RESPONSE]);

            $entry->update(['type' => EntryType::REQUEST_FAILED]);

            expect($entry->fresh()->type)->toBe(EntryType::REQUEST_FAILED);
        });

        it('can be deleted', function () {
            $entry = Entry::factory()->create();
            $entryId = $entry->id;

            $entry->delete();

            expect(Entry::find($entryId))->toBeNull();
        });
    });

    describe('Query Scopes and Filtering', function () {
        beforeEach(function () {
            testTime()->freeze('2024-01-01 12:00:00');

            // Create test data
            Entry::factory()->create([
                'direction' => Direction::INCOMING,
                'type' => EntryType::RESPONSE,
                'created_at' => now()->subDays(1),
            ]);

            Entry::factory()->create([
                'direction' => Direction::OUTGOING,
                'type' => EntryType::REQUEST_FAILED,
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'direction' => Direction::OUTGOING,
                'type' => EntryType::CONNECTION_FAILED,
                'created_at' => now()->addHour(),
            ]);
        });

        it('can filter by direction', function () {
            $incomingEntries = Entry::where('direction', Direction::INCOMING)->get();
            $outgoingEntries = Entry::where('direction', Direction::OUTGOING)->get();

            expect($incomingEntries)->toHaveCount(1)
                ->and($outgoingEntries)->toHaveCount(2);
        });

        it('can filter by type', function () {
            $responseEntries = Entry::where('type', EntryType::RESPONSE)->get();
            $failedEntries = Entry::where('type', EntryType::REQUEST_FAILED)->get();

            expect($responseEntries)->toHaveCount(1)
                ->and($failedEntries)->toHaveCount(1);
        });

        it('can filter by date range', function () {
            $entriesFromYesterday = Entry::where('created_at', '>=', now()->subDays(1)->startOfDay())
                ->where('created_at', '<', now()->startOfDay())
                ->get();

            $entriesToday = Entry::where('created_at', '>=', now()->startOfDay())->get();

            expect($entriesFromYesterday)->toHaveCount(1)
                ->and($entriesToday)->toHaveCount(2);
        });

        it('can order by creation date', function () {
            $entries = Entry::orderBy('created_at', 'asc')->get();

            expect($entries->first()->created_at->lessThan($entries->last()->created_at))->toBeTrue();
        });
    });

    describe('Mass Assignment Protection', function () {
        it('allows mass assignment of fillable fields', function () {
            $data = [
                'direction' => Direction::INCOMING,
                'type' => EntryType::RESPONSE,
                'request' => ['test' => 'data'],
                'response' => ['result' => 'success'],
            ];

            $entry = Entry::create($data);

            expect($entry->direction)->toBe(Direction::INCOMING)
                ->and($entry->type)->toBe(EntryType::RESPONSE);
        });
    });

    describe('Model Relationships and Collections', function () {
        it('can work with collections', function () {
            $entries = Entry::factory(5)->create();

            $collection = Entry::all();

            expect($collection)->toHaveCount(5)
                ->and($collection->first())->toBeInstanceOf(Entry::class)
                ->and($collection->pluck('id'))->toHaveCount(5);
        });

        it('can be paginated', function () {
            Entry::factory(50)->create();

            $paginated = Entry::paginate(10);

            expect($paginated->count())->toBe(10)
                ->and($paginated->total())->toBe(50)
                ->and($paginated->lastPage())->toBe(5);
        });
    });

    describe('Validation and Data Integrity', function () {
        it('handles empty and null response gracefully', function () {
            $entryWithNull = Entry::factory()->create(['response' => null]);
            $entryWithEmpty = Entry::factory()->create(['response' => []]);

            expect($entryWithNull->response)->toBeNull();
            expect($entryWithEmpty->response)->toBe([]);
        });
    });

});
