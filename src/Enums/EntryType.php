<?php

namespace NcooDev\HormLogger;

enum EntryType: string
{
    case RESPONSE = 'response';
    case CONNECTION_FAILED = 'connection_failed';

}
