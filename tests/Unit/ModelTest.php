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
                ->and($entry->url)->toBeString()
                ->and($entry->method)->not->toBeNull()
                ->and($entry->status_code)->toBeInt()
                ->and($entry->created_at)->toBeInstanceOf(Carbon::class)
                ->and($entry->updated_at)->toBeInstanceOf(Carbon::class);
        });

        it('can create entries with specific attributes', function () {
            $entry = Entry::factory()->create([
                'direction' => Direction::INCOMING,
                'type' => EntryType::RESPONSE,
                'url' => 'https://example.com/api',
                'status_code' => 200,
            ]);

            expect($entry->direction)->toBe(Direction::INCOMING)
                ->and($entry->type)->toBe(EntryType::RESPONSE)
                ->and($entry->url)->toBe('https://example.com/api')
                ->and($entry->status_code)->toBe(200);
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

        it('stores serialized data in request field', function () {
            $testData = ['method' => 'GET', 'headers' => ['Content-Type' => 'application/json']];
            $serialized = base64_encode(serialize($testData));

            $entry = Entry::factory()->create(['request' => $serialized]);

            expect($entry->request)->toBe($serialized);
            expect(unserialize(base64_decode($entry->request)))->toBe($testData);
        });
    });

    describe('Database Interactions', function () {
        it('can be saved and retrieved from database', function () {
            $originalEntry = Entry::factory()->create([
                'url' => 'https://test.example.com/unique-endpoint',
                'status_code' => 201,
            ]);

            $retrievedEntry = Entry::find($originalEntry->id);

            expect($retrievedEntry)
                ->not->toBeNull()
                ->and($retrievedEntry->url)->toBe('https://test.example.com/unique-endpoint')
                ->and($retrievedEntry->status_code)->toBe(201)
                ->and($retrievedEntry->id)->toBe($originalEntry->id);
        });

        it('can be updated', function () {
            $entry = Entry::factory()->create(['status_code' => 200]);

            $entry->update(['status_code' => 404]);

            expect($entry->fresh()->status_code)->toBe(404);
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
                'url' => 'https://example.com',
                'method' => 'GET',
                'status_code' => 200,
                'request' => base64_encode(serialize(['test' => 'data'])),
                'response' => base64_encode(serialize(['result' => 'success'])),
                'content' => base64_encode(serialize('response content')),
            ];

            $entry = Entry::create($data);

            expect($entry->direction)->toBe(Direction::INCOMING)
                ->and($entry->type)->toBe(EntryType::RESPONSE)
                ->and($entry->url)->toBe('https://example.com')
                ->and($entry->status_code)->toBe(200);
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
        it('handles long URLs correctly', function () {
            $longUrl = 'https://example.com/' . str_repeat('very-long-path-segment/', 100);

            $entry = Entry::factory()->create(['url' => $longUrl]);

            expect($entry->url)->toBe($longUrl)
                ->and(strlen($entry->url))->toBeGreaterThan(1000);
        });

        it('handles various status codes', function () {
            $statusCodes = [200, 201, 400, 401, 404, 500, 503];

            foreach ($statusCodes as $code) {
                $entry = Entry::factory()->create(['status_code' => $code]);
                expect($entry->status_code)->toBe($code);
            }

            expect(Entry::all())->toHaveCount(count($statusCodes));
        });

        it('handles empty and null content gracefully', function () {
            $entryWithNull = Entry::factory()->create(['content' => null]);
            $entryWithEmpty = Entry::factory()->create(['content' => base64_encode(serialize(''))]);

            expect($entryWithNull->content)->toBeNull();
            expect(unserialize(base64_decode($entryWithEmpty->content)))->toBe('');
        });
    });

});