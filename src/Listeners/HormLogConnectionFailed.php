<?php

namespace NcooDev\HormLogger\Listeners;

use Illuminate\Http\Client\Events\ConnectionFailed;
use NcooDev\HormLogger\Enums\Direction;
use NcooDev\HormLogger\Enums\EntryType;
use NcooDev\HormLogger\HormLoggerServiceProvider;

class HormLogConnectionFailed
{
    public function __construct() {}

    public function handle(ConnectionFailed $connectionFailed)
    {
        (HormLoggerServiceProvider::determineEntryModel())::create([
            'type' => EntryType::CONNECTION_FAILED,
            'direction' => Direction::OUTGOING,
            'url' => $connectionFailed->request->url(),
            'status_code' => $connectionFailed->exception->getCode(),
            'method' => $connectionFailed->request->method(),
            'request' => base64_encode(serialize($connectionFailed->request)),
            'response' => null,
            'content' => base64_encode(serialize($connectionFailed->exception->getMessage())),
        ]);
    }
}
