<?php

namespace NcooDev\HormLogger;

enum Direction: string
{
    case INCOMING = 'incoming';
    case OUTGOING = 'outgoing';
}
