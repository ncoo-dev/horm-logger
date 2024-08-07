<?php

use Illuminate\Support\Facades\Http;
use NcooDev\HormLogger\Models\Entry;

beforeEach(function () {
    config(['horm.model.entry' => Entry::class]);
});
it('can have good response', function () {
    Http::fake([
        'https://example.com*' => Http::response('error', 400, ['Headers']),
    ]);
    Http::get('https://example.com/1');

    $entry = Entry::first();
    $response = \NcooDev\HormLogger\Dtos\Response::fromDB($entry->response);

    expect($response->headers)->toBe([['Headers']]);
});
