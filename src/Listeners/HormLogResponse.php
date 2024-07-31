<?php

namespace NcooDev\HormLogger\Listeners;

use Illuminate\Http\Client\Events\ResponseReceived;

class HormLogResponse
{
    public function __construct()
    {

    }

    public function handle(ResponseReceived $response)
    {
    }
}
