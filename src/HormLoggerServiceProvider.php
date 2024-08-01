<?php

namespace NcooDev\HormLogger;

use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use NcooDev\HormLogger\Listeners\HormLogResponse;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class HormLoggerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('horm-logger')
            ->hasConfigFile('horm');
        //            ->hasMigration()
        //            ->hasCommand()
    }

    public function packageBooted(): void
    {
        Event::listen(
            ResponseReceived::class,
            HormLogResponse::class,
        );
    }
}
