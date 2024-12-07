<?php

namespace NcooDev\HormLogger\Enums;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

enum EntryType: string
{
    case RESPONSE = 'response';
    case CONNECTION_FAILED = 'connection_failed';
    case REQUEST_FAILED = 'request_failed';

    public static function byResponseStatut(Response|\Illuminate\Http\Client\Response|JsonResponse $response): EntryType
    {
        if ($response->status() === 0) {
            return self::CONNECTION_FAILED;
        }
        if ($response->status() >= 400) {
            return self::REQUEST_FAILED;
        }

        return self::RESPONSE;
    }
}
