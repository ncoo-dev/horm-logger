<?php

namespace NcooDev\HormLogger\Listeners;

use Illuminate\Http\Client\Events\ResponseReceived;
use NcooDev\HormLogger\Dtos\Request;
use NcooDev\HormLogger\Dtos\Response;
use NcooDev\HormLogger\HormLoggerServiceProvider;
use NcooDev\HormLogger\Support\DataObfuscator;

class HormLogResponse
{
    public function __construct() {}

    public function handle(ResponseReceived $response)
    {
        if (! config('horm.enabled', true)) {
            return;
        }

        if (DataObfuscator::shouldExcludeOutgoingUrl($response->request->url())) {
            return;
        }

        $requestData = DataObfuscator::obfuscate(Request::fromHttpClientRequest($response->request)->toArray());
        $responseData = DataObfuscator::obfuscate(Response::fromHttpClientResponse($response->response)->toArray());

        (HormLoggerServiceProvider::determineEntryModel())::create([
            'type' => \NcooDev\HormLogger\Enums\EntryType::byResponseStatut($response->response),
            'direction' => \NcooDev\HormLogger\Enums\Direction::OUTGOING,
            'request' => $requestData,
            'response' => $responseData,
        ]);
    }
}
