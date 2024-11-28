<?php

namespace NcooDev\HormLogger\Listeners;

use NcooDev\HormLogger\Enums\Direction;
use NcooDev\HormLogger\Enums\EntryType;
use NcooDev\HormLogger\Events\RequestExceptionThrown;
use NcooDev\HormLogger\HormLoggerServiceProvider;

class HormLogRequestException
{
    public function __construct() {}

    public function handle(RequestExceptionThrown $exceptionThrown)
    {

//        $request = $exceptionThrown->exception->getRequest();
//        //        dd($request);
//        (HormLoggerServiceProvider::determineEntryModel())::create([
//            'type' => EntryType::REQUEST_FAILED,
//            'direction' => Direction::OUTGOING,
//            'url' => $request->getUri()->getScheme().'://'.$request->getUri()->getHost().$request->getUri()->getPath(),
//            'status_code' => $exceptionThrown->exception->getCode(),
//            'method' => $request->getMethod(),
//            'request' => base64_encode(serialize($request)),
//            'response' => null,
//            'content' => base64_encode(serialize($exceptionThrown->exception->getMessage())),
//        ]);
    }
}
