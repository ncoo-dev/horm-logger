<?php

namespace NcooDev\HormLogger;

class Facade
{
    protected static function getFacadeAccessor()
    {
        return HormLoggerClass::class;
    }
}
