<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use NcooDev\HormLogger\Dtos\Request;
use NcooDev\HormLogger\Dtos\Response;
use NcooDev\HormLogger\Models\Entry;

use function Pest\Laravel\get;


describe('listeners', function () {

    beforeEach(function () {
        Http::fake([
            'https://bad.com*' => Http::response('error', 400, ['Headers']),
            'https://good.com*' => Http::response('success', 200, ['Headers']),
            'https://error-failed.com' => Http::failedConnection('Impossible de se connecter'),
            'https://error-failed2.com' => Http::response('error', 500, ['Headers']),

            ]);
    });

    it('a connection failed can be call', function () {
        \Pest\Laravel\withoutExceptionHandling([ConnectionException::class]);
        expect(Entry::all())->toBeEmpty();
        try {
            Http::get('https://error-failed.com');
        }
        catch (ConnectionException){

        } finally {


            $recorded = Http::recorded();
            [$request, $response] = $recorded[0];
//        $connectionFailed = new ConnectionFailed($request, new ConnectionException('Foo'));
//        (new \NcooDev\HormLogger\Listeners\HormLogConnectionFailed)->handle($connectionFailed);

            expect(Entry::all())->not->toBeEmpty();
            $entry = Entry::first();
            expect($entry)
                ->toBeInstanceOf(config('horm.model.entry'))
                ->toMatchArray([
                    'type' => \NcooDev\HormLogger\Enums\EntryType::CONNECTION_FAILED->value,
                    'direction' => \NcooDev\HormLogger\Enums\Direction::OUTGOING->value,
                    'url' => 'https://error-failed.com',
                    'status_code' => 0,
                    'method' => 'GET',
                    'request' => base64_encode(serialize($request)),
                    'response' => null,
                    'content' => base64_encode(serialize('Impossible de se connecter')),
                ])
                ->and($entry->type)->toBe(\NcooDev\HormLogger\Enums\EntryType::CONNECTION_FAILED)
                ->and($entry->direction)->toBe(\NcooDev\HormLogger\Enums\Direction::OUTGOING)
                ->and($entry->url)->toBe('https://error-failed.com')
                ->and($entry->status_code)->toBe(0)
                ->and($entry->method)->toBe('GET')
                ->and($entry->request)->toBe(base64_encode(serialize($request)))
                ->and($entry->response)->toBeNull()
                ->and($entry->content)->toBe(base64_encode(serialize('Impossible de se connecter')));
        }
    });

    it('a bad response listener can be call', function () {

        expect(Entry::all())->toBeEmpty();
        Http::get('https://bad.com/1');
        $recorded = Http::recorded();
        [$request,$response] = $recorded[0];

        expect(Entry::all())->not->toBeEmpty();

        $entry = Entry::first();
        expect($entry)
            ->toBeInstanceOf(config('horm.model.entry'))
            ->toMatchArray([
                'type' => \NcooDev\HormLogger\Enums\EntryType::RESPONSE->value,
                'direction' => \NcooDev\HormLogger\Enums\Direction::OUTGOING->value,
                'url' => 'https://bad.com/1',
                'status_code' => 400,
                'method' => 'GET',
                'request' => base64_encode(serialize($request)),
                'response' => base64_encode(serialize(Response::fromHttpClientResponse($response))),
                'content' => base64_encode(serialize('error')),
            ])
            ->and($entry->type)->toBe(\NcooDev\HormLogger\Enums\EntryType::RESPONSE)
            ->and($entry->direction)->toBe(\NcooDev\HormLogger\Enums\Direction::OUTGOING)
            ->and($entry->url)->toBe('https://bad.com/1')
            ->and($entry->status_code)->toBe(400)
            ->and($entry->method)->toBe('GET')
            ->and($entry->request)->toBe(base64_encode(serialize($request)))
            ->and($entry->response)->toBe(base64_encode(serialize(Response::fromHttpClientResponse($response))))
            ->and($entry->content)->toBe(base64_encode(serialize('error')));

    });

    it('a good response listener can be call', function () {
        expect(Entry::all())->toBeEmpty();
        Http::get('https://good.com/1');
        $recorded = Http::recorded();
        [$request,$response] = $recorded[0];

        expect(Entry::all())->not->toBeEmpty();

        $entry = Entry::first();
        expect($entry)
            ->toBeInstanceOf(config('horm.model.entry'))
            ->toMatchArray([
                'type' => \NcooDev\HormLogger\Enums\EntryType::RESPONSE->value,
                'direction' => \NcooDev\HormLogger\Enums\Direction::OUTGOING->value,
                'url' => 'https://good.com/1',
                'status_code' => 200,
                'method' => 'GET',
                'request' => base64_encode(serialize($request)),
                'response' => base64_encode(serialize(Response::fromHttpClientResponse($response))),
                'content' => base64_encode(serialize('success')),
            ])
            ->and($entry->type)->toBe(\NcooDev\HormLogger\Enums\EntryType::RESPONSE)
            ->and($entry->direction)->toBe(\NcooDev\HormLogger\Enums\Direction::OUTGOING)
            ->and($entry->url)->toBe('https://good.com/1')
            ->and($entry->status_code)->toBe(200)
            ->and($entry->method)->toBe('GET')
            ->and($entry->request)->toBe(base64_encode(serialize($request)))
            ->and($entry->response)->toBe(base64_encode(serialize(Response::fromHttpClientResponse($response))))
            ->and($entry->content)->toBe(base64_encode(serialize('success')));

    });

});

test('the middleware in isolation', function () {
    expect(Entry::all())->toBeEmpty();
    $generatedRequest = createRequest('GET', '/test');
    (new \NcooDev\HormLogger\Middleware\SaveLog)->handle($generatedRequest, function ($request) {
        return response('test', 404);
    });
    expect(Entry::all())->not->toBeEmpty();
    $entry = Entry::first();
    expect($entry)->toBeInstanceOf(config('horm.model.entry'))
        ->and($entry->type)->toBe(\NcooDev\HormLogger\Enums\EntryType::RESPONSE)
        ->and($entry->direction)->toBe(\NcooDev\HormLogger\Enums\Direction::INCOMING)
        ->and($entry->url)->toBe('http://localhost/test')
        ->and($entry->status_code)->toBe(404)
        ->and($entry->method)->toBe('GET')
        ->and(Request::fromDB($entry->request))->toEqual(Request::fromHttpRequest($generatedRequest))
        ->and(Response::fromDB($entry->response))->toEqual(Response::fromHttpResponse(response('test', 404), 0))
        ->and($entry->content)->toBe(base64_encode(serialize('test')));

});

test('the middleware in full app', function () {
    expect(Entry::all())->toBeEmpty();

    \Illuminate\Support\Facades\Route::get('/testmiddleware', function () {
        return response('test', 200);
    });

    $response = get('/testmiddleware');
    expect(Entry::all())->toBeEmpty();

    \Illuminate\Support\Facades\Route::middleware(\NcooDev\HormLogger\Middleware\SaveLog::class)->get('/testmiddleware', function () {
        return response('test', 200);
    });
        $response = get('/testmiddleware');
        expect(Entry::all())->not->toBeEmpty();
});
