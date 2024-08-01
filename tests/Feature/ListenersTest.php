<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Support\Facades\Http;

it('a connection failed listener can be call', function () {
    Http::fake([
        '*' => Http::response('error', 400, ['Headers']),
    ]);

    \Illuminate\Support\Facades\Http::get('https://example.com');
    $recorded = Http::recorded();
    [$request,$response] = $recorded[0];

    $connectionFailed = new ConnectionFailed($request, new ConnectionException('Foo', $response->getStatusCode()));

    (new \NcooDev\HormLogger\Listeners\HormLogConnectionFailed)->handle($connectionFailed);

    $this->assertTrue(true);
});
