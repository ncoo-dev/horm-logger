<?php

namespace NcooDev\HormLogger\Listeners;

use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Arr;
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

        $requestData = collect(DataObfuscator::obfuscate(Request::fromHttpClientRequest($response->request)->toArray()))->toJson();
        $responseData = collect(DataObfuscator::obfuscate(Response::fromHttpClientResponse($response->response)->toArray()))->toJson();

        (HormLoggerServiceProvider::determineEntryModel())::create([
            'type' => \NcooDev\HormLogger\Enums\EntryType::byResponseStatut($response->response),
            'direction' => \NcooDev\HormLogger\Enums\Direction::OUTGOING,
            'url' => $response->request->url(),
            'status_code' => $response->response->status(),
            'method' => $response->request->method(),
            'request' => $requestData,
            'response' => $responseData,
        ]);
    }
}
