<?php

namespace NcooDev\HormLogger;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use NcooDev\HormLogger\Events\RequestExceptionThrown;
use NcooDev\HormLogger\Exceptions\InvalidConfiguration;
use NcooDev\HormLogger\Http\Controllers\EntryController;
use NcooDev\HormLogger\Listeners\HormLogConnectionFailed;
use NcooDev\HormLogger\Listeners\HormLogRequestException;
use NcooDev\HormLogger\Listeners\HormLogResponse;
use NcooDev\HormLogger\Middleware\RequiresSecret;
use NcooDev\HormLogger\Models\Entry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class HormLoggerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('horm-logger')
            ->hasConfigFile('horm')

            ->hasMigrations(['create_horm_entries_table'])
                    ->hasCommand(\NcooDev\HormLogger\Console\InstallCommand::class)
        ;
    }

    public function packageBooted(): void
    {
        Event::listen(
            ResponseReceived::class,
            HormLogResponse::class,
        );
        Event::listen(
            ConnectionFailed::class,
            HormLogConnectionFailed::class,
        );

        $this->registerHormEndpoint();

    }

    public static function determineEntryModel(): string
    {
        $activityModel = config('horm.model.entry') ?? Entry::class;

        if (! is_a($activityModel, Entry::class, true)
            || ! is_a($activityModel, Model::class, true)) {
            throw InvalidConfiguration::modelIsNotValid($activityModel);
        }

        return $activityModel;
    }

    public static function getActivityModelInstance(): Entry
    {
        $activityModelClassName = self::determineEntryModel();

        return new $activityModelClassName;
    }

    protected function registerHormEndpoint(): self
    {
        if (! config('horm.horm_endpoint.enabled')) {
            return $this;
        }

        if (! config('horm.horm_endpoint.secret')) {
            return $this;
        }

        if (! config('horm.horm_endpoint.url')) {
            return $this;
        }

        Route::get(config('horm.horm_endpoint.url'), EntryController::class)
            ->middleware(RequiresSecret::class);

        return $this;
    }
}
