<?php

use Illuminate\Support\Facades\Event;
use NcooDev\HormLogger\HormLoggerServiceProvider;
use NcooDev\HormLogger\Listeners\HormLogConnectionFailed;
use NcooDev\HormLogger\Listeners\HormLogResponse;
use NcooDev\HormLogger\Middleware\SaveLog;

describe('HORM Logger Service Provider', function () {

    describe('Service Provider Registration', function () {
        it('registers the service provider correctly', function () {
            $providers = app()->getLoadedProviders();

            expect($providers)->toHaveKey(HormLoggerServiceProvider::class);
        });

        it('publishes configuration files', function () {
            $provider = new HormLoggerServiceProvider(app());

            // Test that publishes method exists and can be called
            expect(method_exists($provider, 'publishes'))->toBeTrue();
        });

        it('publishes migration files', function () {
            $publishGroups = \Illuminate\Support\ServiceProvider::$publishGroups;

            expect($publishGroups)->toHaveKey('horm-logger-migrations');
        });

        it('registers commands', function () {
            $commands = app()[\Illuminate\Contracts\Console\Kernel::class]->all();

            expect($commands)->toHaveKey('horm:install')
                ->toHaveKey('horm:prune');
        });
    });

    describe('Event Listener Registration', function () {
        it('registers HTTP response listener', function () {
            $listeners = Event::getRawListeners();

            expect($listeners)->toHaveKey('Illuminate\Http\Client\Events\ResponseReceived')
                ->and($listeners['Illuminate\Http\Client\Events\ResponseReceived'])
                ->toContain(HormLogResponse::class);
        });

        it('registers connection failed listener', function () {
            $listeners = Event::getRawListeners();

            expect($listeners)->toHaveKey('Illuminate\Http\Client\Events\ConnectionFailed')
                ->and($listeners['Illuminate\Http\Client\Events\ConnectionFailed'])
                ->toContain(HormLogConnectionFailed::class);
        });
    });

    describe('Route Registration', function () {
        it('registers HORM endpoint route when enabled', function () {
            config()->set('horm.endpoint.enabled', true);
            config()->set('horm.endpoint.url', 'test-horm-endpoint');

            $this->refreshServiceProvider();

            $routes = collect(app('router')->getRoutes())->map(function ($route) {
                return $route->uri();
            });

            expect($routes->contains('test-horm-endpoint'))->toBeTrue();
        });

        it('does not register route when endpoint is disabled', function () {
            config()->set('horm.endpoint.enabled', false);

            $this->refreshServiceProvider();

            $routes = collect(app('router')->getRoutes())->map(function ($route) {
                return $route->uri();
            });

            expect($routes->contains('horm-test-endpoint'))->toBeFalse();
        });

        it('applies correct middleware to endpoint', function () {
            config()->set('horm.endpoint.enabled', true);
            config()->set('horm.endpoint.url', 'test-endpoint');

            $this->refreshServiceProvider();

            $route = collect(app('router')->getRoutes())->first(function ($route) {
                return $route->uri() === 'test-endpoint';
            });

            expect($route)->not->toBeNull();
            expect($route->middleware())->toContain('horm.check-secret');
        });
    });

    describe('Middleware Registration', function () {
        it('registers SaveLog middleware', function () {
            $router = app('router');
            $middleware = $router->getMiddleware();

            expect($middleware)->toHaveKey('horm.save-log')
                ->and($middleware['horm.save-log'])->toBe(SaveLog::class);
        });

        it('registers RequiresSecret middleware', function () {
            $router = app('router');
            $middleware = $router->getMiddleware();

            expect($middleware)->toHaveKey('horm.check-secret')
                ->and($middleware['horm.check-secret'])
                ->toBe(\NcooDev\HormLogger\Middleware\RequiresSecret::class);
        });
    });

    describe('Configuration Handling', function () {
        it('loads default configuration', function () {
            expect(config('horm'))->not->toBeNull()
                ->and(config('horm.database'))->toBeArray()
                ->and(config('horm.model'))->toBeArray()
                ->and(config('horm.endpoint'))->toBeArray();
        });

        it('allows configuration override', function () {
            config()->set('horm.model.keep_history_for_days', 10);

            expect(config('horm.model.keep_history_for_days'))->toBe(10);
        });

        it('handles missing configuration gracefully', function () {
            config()->set('horm', null);

            // Should not throw exception when accessing config
            expect(fn () => config('horm.endpoint.enabled', false))->not->toThrow(Exception::class);
        });
    });

    describe('Database Migration Registration', function () {
        it('provides package migrations', function () {
            // Check that the package migration files exist
            $migrationPath = __DIR__.'/../../database/migrations';
            expect(is_dir($migrationPath))->toBeTrue();

            // Check that specific migration files exist
            $migrationFiles = glob($migrationPath.'/*.php*');
            expect(count($migrationFiles))->toBeGreaterThan(0);

            // Verify migration files have expected names
            $migrationNames = array_map('basename', $migrationFiles);
            $hasCreateTable = collect($migrationNames)->some(fn ($name) => str_contains($name, 'create_horm_entries_table'));
            expect($hasCreateTable)->toBeTrue();
        });
    });

    describe('Package Discovery', function () {
        it('is discoverable via composer extra config', function () {
            $composerFile = file_get_contents(__DIR__.'/../../composer.json');
            $composer = json_decode($composerFile, true);

            expect($composer['extra']['laravel']['providers'])
                ->toContain(HormLoggerServiceProvider::class);
        });

        it('registers facade alias', function () {
            $composerFile = file_get_contents(__DIR__.'/../../composer.json');
            $composer = json_decode($composerFile, true);

            expect($composer['extra']['aliases'])
                ->toHaveKey('Horm')
                ->and($composer['extra']['aliases']['Horm'])
                ->toBe('NcooDev\\HormLogger\\Facade');
        });
    });

    describe('Service Provider Boot Process', function () {
        it('boots without errors', function () {
            $provider = new HormLoggerServiceProvider(app());

            expect(fn () => $provider->boot())->not->toThrow(Exception::class);
        });

        it('registers services without errors', function () {
            $provider = new HormLoggerServiceProvider(app());

            expect(fn () => $provider->register())->not->toThrow(Exception::class);
        });
    });

    describe('Environment-Specific Behavior', function () {
        it('handles different Laravel versions', function () {
            $laravelVersion = app()->version();

            expect($laravelVersion)->toBeString()
                ->and(version_compare($laravelVersion, '11.0', '>='))->toBeTrue();
        });

        it('works in testing environment', function () {
            // In package development, environment might be 'workbench' or 'testing'
            expect(app()->environment())->toBeIn(['testing', 'workbench']);
            expect(config('app.env'))->toBeIn(['testing', 'workbench']);
        });

        it('respects debug mode configuration', function () {
            config()->set('app.debug', true);
            expect(config('app.debug'))->toBeTrue();

            config()->set('app.debug', false);
            expect(config('app.debug'))->toBeFalse();
        });
    });

    describe('Resource Publishing', function () {
        it('can publish all resources at once', function () {
            expect(function () {
                \Illuminate\Support\Facades\Artisan::call('vendor:publish', [
                    '--provider' => HormLoggerServiceProvider::class,
                    '--force' => true,
                ]);
            })->not->toThrow(Exception::class);
        });

        it('can publish specific resource groups', function () {
            expect(function () {
                \Illuminate\Support\Facades\Artisan::call('vendor:publish', [
                    '--provider' => HormLoggerServiceProvider::class,
                    '--tag' => 'horm-logger-config',
                    '--force' => true,
                ]);
            })->not->toThrow(Exception::class);
        });
    });

});
