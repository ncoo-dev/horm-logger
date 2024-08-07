<?php

namespace NcooDev\HormLogger\Events;

use GuzzleHttp\Exception\RequestException;

class RequestExceptionThrown
{
    public function __construct(public RequestException $exception) {}
}
