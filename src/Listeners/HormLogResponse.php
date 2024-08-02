<?php

namespace NcooDev\HormLogger\Listeners;

use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Log;

class HormLogResponse
{
    public function __construct() {}

    public function handle(ResponseReceived $response)
    {
//        Log::info('Response received', [
//            'url' => $response->request->url(),
//            'status' => $response->response->status(),
//            'body' => $response->response->body(),
//        ]);
        Log::info('serializé : ' . base64_encode(serialize($response->response)));
    }
}
