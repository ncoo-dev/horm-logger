<?php

uses(\NcooDev\HormLogger\Tests\TestCase::class)->in('Feature');

beforeEach(function () {
    // fresh de la db
    $this->artisan('migrate:fresh');
});

function createRequest($method, $uri): \Illuminate\Http\Request
{
    $symfonyRequest = \Symfony\Component\HttpFoundation\Request::create($uri, $method);

    return \Illuminate\Http\Request::createFromBase($symfonyRequest);
}
