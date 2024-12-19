<?php

namespace NcooDev\HormLogger\Listeners;

use Illuminate\Http\Client\Events\ResponseReceived;
use NcooDev\HormLogger\Dtos\Request;
use NcooDev\HormLogger\Dtos\Response;
use NcooDev\HormLogger\HormLoggerServiceProvider;

class HormLogResponse
{
    public function __construct() {}

    public function handle(ResponseReceived $response)
    {
        (HormLoggerServiceProvider::determineEntryModel())::create([
            'type' => \NcooDev\HormLogger\Enums\EntryType::byResponseStatut($response->response),
            'direction' => \NcooDev\HormLogger\Enums\Direction::OUTGOING,
            'url' => $response->request->url(),
            'status_code' => $response->response->status(),
            'method' => $response->request->method(),
            'request' => base64_encode(serialize(Request::fromHttpClientRequest($response->request)->toArray())),
            'response' => base64_encode(serialize(Response::fromHttpClientResponse($response->response)->toArray())),
            'content' => base64_encode(serialize($response->response->body())),
        ]);
    }
}
