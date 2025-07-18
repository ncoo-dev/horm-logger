<?php

describe('Architecture Tests', function () {

    it('ensures no debugging functions are used in production code')
        ->expect(['dd', 'dump', 'var_dump', 'print_r'])
        ->not->toBeUsed()
        ->ignoring('tests');

    it('ensures models extend Eloquent')
        ->expect('NcooDev\HormLogger\Models')
        ->toExtend('Illuminate\Database\Eloquent\Model');

    it('ensures console commands extend Laravel commands')
        ->expect('NcooDev\HormLogger\Console')
        ->toExtend('Illuminate\Console\Command');

    it('ensures exceptions extend base exception')
        ->expect('NcooDev\HormLogger\Exceptions')
        ->toExtend('Exception');

    it('ensures service provider follows conventions')
        ->expect('NcooDev\HormLogger\HormLoggerServiceProvider')
        ->toExtend('Illuminate\Support\ServiceProvider');

    it('ensures enums are backed enums')
        ->expect('NcooDev\HormLogger\Enums')
        ->toBeEnums();

});
