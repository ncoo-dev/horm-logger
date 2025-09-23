<?php

namespace NcooDev\HormLogger\Listeners;

use Illuminate\Http\Client\Events\ConnectionFailed;
use NcooDev\HormLogger\Dtos\Request;
use NcooDev\HormLogger\Enums\Direction;
use NcooDev\HormLogger\Enums\EntryType;
use NcooDev\HormLogger\HormLoggerServiceProvider;
use NcooDev\HormLogger\Support\DataObfuscator;

class HormLogConnectionFailed
{
    public function __construct() {}

    public function handle(ConnectionFailed $connectionFailed)
    {

        $requestData = collect(DataObfuscator::obfuscate(Request::fromHttpClientRequest($connectionFailed->request)->toArray()))->toJson();

        (HormLoggerServiceProvider::determineEntryModel())::create([
            'type' => EntryType::CONNECTION_FAILED,
            'direction' => Direction::OUTGOING,
            'url' => $connectionFailed->request->url(),
            'status_code' => $connectionFailed->exception->getCode(),
            'method' => $connectionFailed->request->method(),
            'request' => $requestData,
            'response' => $connectionFailed->exception->getMessage().'\r\n'.$connectionFailed->exception->getTraceAsString(),
        ]);
    }
}
