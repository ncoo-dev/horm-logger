<?php

namespace NcooDev\HormLogger\Enums;

enum EntryType: string
{
    case RESPONSE = 'response';
    case CONNECTION_FAILED = 'connection_failed';
    case REQUEST_FAILED = 'request_failed';

}
