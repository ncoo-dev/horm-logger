<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use NcooDev\HormLogger\Dtos\Response;
use NcooDev\HormLogger\Models\Entry;

describe('connection exception', function () {
    beforeEach(function () {
        Event::fake();
        Http::fake([
            'https:://bad.com*' => Http::response('error', 500, ['Headers']),
        ]);
    });

    it('test exceptions', function () {
        expect(Entry::where('url', 'https:://error.com')->get())->toHaveCount(0);

        Http::get('https:://bad.com');

        $recorded = Http::recorded();
        [$request,$response] = $recorded[0];
        $connectionFailed = new ConnectionFailed($request, new ConnectionException('Foo'));
        (new \NcooDev\HormLogger\Listeners\HormLogConnectionFailed)->handle($connectionFailed);

        expect(Entry::all())->not->toBeEmpty();
        $entry = Entry::first();

        expect($entry)
            ->toBeInstanceOf(config('horm.model.entry'))
            ->toMatchArray([
                'type' => \NcooDev\HormLogger\Enums\EntryType::CONNECTION_FAILED->value,
                'direction' => \NcooDev\HormLogger\Enums\Direction::OUTGOING->value,
                'url' => 'https:://bad.com',
                'status_code' => 0,
                'method' => 'GET',
                'request' => base64_encode(serialize($request)),
                'response' => null,
                'content' => base64_encode(serialize('Foo')),
            ])
            ->and($entry->type)->toBe(\NcooDev\HormLogger\Enums\EntryType::CONNECTION_FAILED)
            ->and($entry->direction)->toBe(\NcooDev\HormLogger\Enums\Direction::OUTGOING)
            ->and($entry->url)->toBe('https:://bad.com')
            ->and($entry->status_code)->toBe(0)
            ->and($entry->method)->toBe('GET')
            ->and($entry->request)->toBe(base64_encode(serialize($request)))
            ->and($entry->response)->toBeNull()
            ->and($entry->content)->toBe(base64_encode(serialize('Foo')));

    });
});
describe('listeners', function () {

    beforeEach(function () {
        Http::fake([
            'https:://bad.com*' => Http::response('error', 400, ['Headers']),
            'https:://good.com*' => Http::response('success', 200, ['Headers']),
        ]);
    });

    it('a connection failed listener can be call', function () {

        expect(Entry::all())->toBeEmpty();
        Http::get('https:://bad.com/1');
        $recorded = Http::recorded();
        [$request,$response] = $recorded[0];

        expect(Entry::all())->not->toBeEmpty();

        $entry = Entry::first();
        expect($entry)
            ->toBeInstanceOf(config('horm.model.entry'))
            ->toMatchArray([
                'type' => \NcooDev\HormLogger\Enums\EntryType::RESPONSE->value,
                'direction' => \NcooDev\HormLogger\Enums\Direction::OUTGOING->value,
                'url' => 'https:://bad.com/1',
                'status_code' => 400,
                'method' => 'GET',
                'request' => base64_encode(serialize($request)),
                'response' => base64_encode(serialize(Response::fromHttpResponse($response))),
                'content' => base64_encode(serialize('error')),
            ])
            ->and($entry->type)->toBe(\NcooDev\HormLogger\Enums\EntryType::RESPONSE)
            ->and($entry->direction)->toBe(\NcooDev\HormLogger\Enums\Direction::OUTGOING)
            ->and($entry->url)->toBe('https:://bad.com/1')
            ->and($entry->status_code)->toBe(400)
            ->and($entry->method)->toBe('GET')
            ->and($entry->request)->toBe(base64_encode(serialize($request)))
            ->and($entry->response)->toBe(base64_encode(serialize(Response::fromHttpResponse($response))))
            ->and($entry->content)->toBe(base64_encode(serialize('error')));

    });

    it('a bas response listener can be call', function () {
        expect(Entry::all())->toBeEmpty();
        Http::get('https:://good.com/1');
        $recorded = Http::recorded();
        [$request,$response] = $recorded[0];

        expect(Entry::all())->not->toBeEmpty();

        $entry = Entry::first();
        expect($entry)
            ->toBeInstanceOf(config('horm.model.entry'))
            ->toMatchArray([
                'type' => \NcooDev\HormLogger\Enums\EntryType::RESPONSE->value,
                'direction' => \NcooDev\HormLogger\Enums\Direction::OUTGOING->value,
                'url' => 'https:://good.com/1',
                'status_code' => 200,
                'method' => 'GET',
                'request' => base64_encode(serialize($request)),
                'response' => base64_encode(serialize(Response::fromHttpResponse($response))),
                'content' => base64_encode(serialize('success')),
            ])
            ->and($entry->type)->toBe(\NcooDev\HormLogger\Enums\EntryType::RESPONSE)
            ->and($entry->direction)->toBe(\NcooDev\HormLogger\Enums\Direction::OUTGOING)
            ->and($entry->url)->toBe('https:://good.com/1')
            ->and($entry->status_code)->toBe(200)
            ->and($entry->method)->toBe('GET')
            ->and($entry->request)->toBe(base64_encode(serialize($request)))
            ->and($entry->response)->toBe(base64_encode(serialize(Response::fromHttpResponse($response))))
            ->and($entry->content)->toBe(base64_encode(serialize('success')));

    });

});
