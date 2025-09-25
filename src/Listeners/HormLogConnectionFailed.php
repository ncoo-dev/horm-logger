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

        $requestData = DataObfuscator::obfuscate(Request::fromHttpClientRequest($connectionFailed->request)->toArray());

        // Ensure UTF-8 encoding for exception message and trace
        $exceptionMessage = mb_convert_encoding($connectionFailed->exception->getMessage(), 'UTF-8', 'UTF-8');
        $exceptionTrace = mb_convert_encoding($connectionFailed->exception->getTraceAsString(), 'UTF-8', 'UTF-8');
        $responseData = [
            'error' => $exceptionMessage,
            'trace' => $exceptionTrace,
            'status' => $connectionFailed->exception->getCode(),
        ];

        (HormLoggerServiceProvider::determineEntryModel())::create([
            'type' => EntryType::CONNECTION_FAILED,
            'direction' => Direction::OUTGOING,
            'request' => $requestData,
            'response' => $responseData,
        ]);
    }
}
