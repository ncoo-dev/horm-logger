<?php

namespace NcooDev\HormLogger\Listeners;

use Illuminate\Http\Client\Events\ConnectionFailed;

class HormLogConnectionFailed
{
    public function __construct() {}

    public function handle(ConnectionFailed $connectionFailed)
    {
        // DO SOMETHING
    }
}
