<?php

namespace NcooDev\HormLogger\Middleware;

use NcooDev\HormLogger\Dtos\Request;
use NcooDev\HormLogger\Dtos\Response;
use NcooDev\HormLogger\HormLoggerServiceProvider;
use NcooDev\HormLogger\Support\DataObfuscator;

class SaveLog
{
    public function handle($request, $next)
    {
        $time = microtime(true);

        $response = $next($request);
        $time = microtime(true) - $time;

        if (! config('horm.enabled', true)) {
            return $response;
        }

        if (DataObfuscator::shouldExcludeIncomingUrl($request->url())) {
            return $response;
        }

        $requestData = collect(DataObfuscator::obfuscate(Request::fromHttpRequest($request)->toArray()))->toJson();
        $responseData = collect(DataObfuscator::obfuscate(Response::fromHttpResponse($response, times: $time)->toArray()))->toJson();

        $log = [
            'type' => \NcooDev\HormLogger\Enums\EntryType::byResponseStatut($response),
            'direction' => \NcooDev\HormLogger\Enums\Direction::INCOMING,
            'url' => $request->url(),
            'status_code' => $response->status(),
            'method' => $request->method(),
            'request' => $requestData,
            'response' => $responseData,
        ];
        (HormLoggerServiceProvider::determineEntryModel())::create($log);

        return $response;
    }
}
