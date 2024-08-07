<?php

namespace NcooDev\HormLogger\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;
use NcooDev\HormLogger\Models\Entry;

class InvalidConfiguration extends Exception
{
    public static function modelIsNotValid(string $className): self
    {
        return new static("The given model class `{$className}` does not implement `".Entry::class.'` or it does not extend `'.Model::class.'`');
    }
}
