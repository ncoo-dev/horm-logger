<?php

namespace NcooDev\HormLogger\Events;

use Illuminate\Http\Client\RequestException;

class RequestExceptionThrown
{
    public function __construct(public RequestException $exception) {}
}
