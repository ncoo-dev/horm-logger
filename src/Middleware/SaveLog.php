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

        $theRequest = Request::fromHttpRequest($request);
        $requestData = DataObfuscator::obfuscate($theRequest->toArray());
        $responseData = DataObfuscator::obfuscate(Response::fromHttpResponse($response, times: $time)->toArray());

        $log = [
            'type' => \NcooDev\HormLogger\Enums\EntryType::byResponseStatut($response),
            'direction' => \NcooDev\HormLogger\Enums\Direction::INCOMING,
            'url' => $request->url(),
            'status_code' => $response->status(),
            'method' => $request->method(),
            'request' => base64_encode(serialize($requestData)),
            'response' => base64_encode(serialize($responseData)),
            'content' => base64_encode(serialize($response->getContent())),
        ];
        (HormLoggerServiceProvider::determineEntryModel())::create($log);

        return $response;
    }
}
