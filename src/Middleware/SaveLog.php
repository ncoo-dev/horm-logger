<?php

namespace NcooDev\HormLogger\Middleware;

use NcooDev\HormLogger\Dtos\Request;
use NcooDev\HormLogger\Dtos\Response;
use NcooDev\HormLogger\HormLoggerServiceProvider;

class SaveLog
{
    public function handle($request, $next)
    {
        $time = microtime(true);
        $response = $next($request);
        $time = microtime(true) - $time;
        $theRequest = Request::fromHttpRequest($request);

        $log = [
            'type' => \NcooDev\HormLogger\Enums\EntryType::RESPONSE,
            'direction' => \NcooDev\HormLogger\Enums\Direction::INCOMING,
            'url' => $request->url(),
            'status_code' => $response->status(),
            'method' => $request->method(),
            'request' => base64_encode(serialize(($theRequest->toArray()))),
            'response' => base64_encode(serialize(Response::fromHttpResponse($response, times: $time))),
            'content' => base64_encode(serialize($response->getContent())),
        ];
        (HormLoggerServiceProvider::determineEntryModel())::create($log);

        return $response;
    }
}
