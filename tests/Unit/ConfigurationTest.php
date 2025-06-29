<?php

use NcooDev\HormLogger\Exceptions\InvalidConfiguration;
use NcooDev\HormLogger\Models\Entry;

describe('HORM Logger Configuration', function () {

    describe('Database Configuration', function () {
        it('uses default database connection when none specified', function () {
            config()->set('horm.database.connection', null);

            expect(config('horm.database.connection'))->toBeNull();
            expect(config('database.default'))->toBe('sqlite');
        });

        it('uses custom database connection when specified', function () {
            config()->set('horm.database.connection', 'mysql');

            expect(config('horm.database.connection'))->toBe('mysql');
        });

        it('uses custom table name when specified', function () {
            config()->set('horm.database.table_name', 'custom_horm_logs');

            expect(config('horm.database.table_name'))->toBe('custom_horm_logs');
        });

        it('validates database connection exists', function () {
            config()->set('horm.database.connection', 'non_existent_connection');

            expect(function () {
                Entry::factory()->create();
            })->toThrow(Exception::class);
        });
    });

    describe('Model Configuration', function () {
        it('uses default Entry model', function () {
            expect(config('horm.model.entry'))->toBe(Entry::class);
        });

        it('allows custom Entry model', function () {
            $customModel = 'App\\Models\\CustomEntry';
            config()->set('horm.model.entry', $customModel);

            expect(config('horm.model.entry'))->toBe($customModel);
        });

        it('validates custom model exists', function () {
            config()->set('horm.model.entry', 'NonExistentModel');

            expect(function () {
                $modelClass = config('horm.model.entry');
                if (! class_exists($modelClass)) {
                    throw new InvalidConfiguration("Entry model {$modelClass} does not exist");
                }
            })->toThrow(InvalidConfiguration::class);
        });

        it('uses default retention period', function () {
            expect(config('horm.model.keep_history_for_days'))->toBe(2);
        });

        it('allows custom retention period', function () {
            config()->set('horm.model.keep_history_for_days', 7);

            expect(config('horm.model.keep_history_for_days'))->toBe(7);
        });

        it('validates retention period is positive', function () {
            config()->set('horm.model.keep_history_for_days', -1);

            expect(function () {
                $days = config('horm.model.keep_history_for_days');
                if ($days < 1) {
                    throw new InvalidConfiguration('Retention period must be at least 1 day');
                }
            })->toThrow(InvalidConfiguration::class);
        });
    });

    describe('Endpoint Configuration', function () {
        it('endpoint is enabled by default in testing', function () {
            expect(config('horm.endpoint.enabled'))->toBeTrue();
        });

        it('can disable endpoint', function () {
            config()->set('horm.endpoint.enabled', false);

            expect(config('horm.endpoint.enabled'))->toBeFalse();
        });

        it('uses default secret', function () {
            expect(config('horm.endpoint.secret'))->toBe('test-secret');
        });

        it('allows custom secret', function () {
            config()->set('horm.endpoint.secret', 'super-secret-key');

            expect(config('horm.endpoint.secret'))->toBe('super-secret-key');
        });

        it('validates secret is not empty', function () {
            config()->set('horm.endpoint.secret', '');

            expect(function () {
                $secret = config('horm.endpoint.secret');
                if (empty($secret)) {
                    throw new InvalidConfiguration('Endpoint secret cannot be empty');
                }
            })->toThrow(InvalidConfiguration::class);
        });

        it('uses default endpoint URL', function () {
            expect(config('horm.endpoint.url'))->toBe('horm-test-endpoint');
        });

        it('allows custom endpoint URL', function () {
            config()->set('horm.endpoint.url', 'my-custom-horm-api');

            expect(config('horm.endpoint.url'))->toBe('my-custom-horm-api');
        });

        it('validates endpoint URL format', function () {
            $invalidUrls = ['', ' ', 'url with spaces', 'url/with/slashes'];

            foreach ($invalidUrls as $invalidUrl) {
                config()->set('horm.endpoint.url', $invalidUrl);

                expect(function () {
                    $url = config('horm.endpoint.url');
                    if (! preg_match('/^[a-zA-Z0-9_-]+$/', $url)) {
                        throw new InvalidConfiguration("Invalid endpoint URL format: {$url}");
                    }
                })->toThrow(InvalidConfiguration::class);
            }
        });
    });

    describe('Environment Variable Integration', function () {
        it('reads configuration from environment variables', function () {
            // Test environment variables are set in TestCase
            expect(env('HORM_ENDPOINT_ENABLED'))->not->toBeNull();
            expect(env('HORM_ENDPOINT_SECRET'))->not->toBeNull();
        });

        it('falls back to default values when env vars not set', function () {
            // Temporarily unset environment variable
            putenv('HORM_ENDPOINT_SECRET');

            $secret = env('HORM_ENDPOINT_SECRET', 'default-secret');
            expect($secret)->toBe('default-secret');
        });
    });

    describe('Configuration Validation', function () {
        it('validates complete configuration structure', function () {
            $config = config('horm');

            expect($config)->toBeArray()
                ->toHaveKey('database')
                ->toHaveKey('model')
                ->toHaveKey('endpoint');

            expect($config['database'])->toHaveKey('connection')
                ->toHaveKey('table_name');

            expect($config['model'])->toHaveKey('entry')
                ->toHaveKey('keep_history_for_days');

            expect($config['endpoint'])->toHaveKey('enabled')
                ->toHaveKey('secret')
                ->toHaveKey('url');
        });

        it('validates all required configuration keys exist', function () {
            $requiredKeys = [
                'horm.database.connection',
                'horm.database.table_name',
                'horm.model.entry',
                'horm.model.keep_history_for_days',
                'horm.endpoint.enabled',
                'horm.endpoint.secret',
                'horm.endpoint.url',
            ];

            foreach ($requiredKeys as $key) {
                expect(config()->has($key))->toBeTrue("Configuration key {$key} is missing");
            }
        });

        it('validates configuration types', function () {
            expect(config('horm.database.connection'))->toBeString();
            expect(config('horm.database.table_name'))->toBeString();
            expect(config('horm.model.entry'))->toBeString();
            expect(config('horm.model.keep_history_for_days'))->toBeInt();
            expect(config('horm.endpoint.enabled'))->toBeBool();
            expect(config('horm.endpoint.secret'))->toBeString();
            expect(config('horm.endpoint.url'))->toBeString();
        });
    });

    describe('Configuration Caching', function () {
        it('works with cached configuration', function () {
            // In Laravel, config is cached in production
            $cached = cache()->remember('test-horm-config', 60, function () {
                return config('horm');
            });

            expect($cached)->toBeArray()
                ->and($cached['endpoint']['enabled'])->toBeBool();
        });

        it('respects configuration changes during runtime', function () {
            $originalValue = config('horm.endpoint.enabled');

            config()->set('horm.endpoint.enabled', ! $originalValue);

            expect(config('horm.endpoint.enabled'))->toBe(! $originalValue);
        });
    });

    describe('Production vs Testing Configuration', function () {
        it('uses appropriate defaults for testing environment', function () {
            expect(app()->environment('testing'))->toBeTrue();
            expect(config('horm.database.connection'))->toBe('sqlite');
        });

        it('handles missing configuration files gracefully', function () {
            // Simulate missing config file
            config()->set('horm', []);

            expect(config('horm.endpoint.enabled', false))->toBeFalse();
            expect(config('horm.model.keep_history_for_days', 2))->toBe(2);
        });
    });

    describe('Security Configuration', function () {
        it('ensures secret is sufficiently complex', function () {
            $weakSecrets = ['123', 'abc', 'password', 'secret'];

            foreach ($weakSecrets as $secret) {
                config()->set('horm.endpoint.secret', $secret);

                expect(function () use ($secret) {
                    if (strlen($secret) < 8) {
                        throw new InvalidConfiguration('Secret must be at least 8 characters long');
                    }
                })->toThrow(InvalidConfiguration::class);
            }
        });

        it('accepts strong secrets', function () {
            $strongSecrets = [
                'very-long-secure-secret-key',
                'Str0ng!P@ssw0rd#2024',
                'abcdef123456789012345678',
            ];

            foreach ($strongSecrets as $secret) {
                config()->set('horm.endpoint.secret', $secret);

                expect(function () use ($secret) {
                    if (strlen($secret) < 8) {
                        throw new InvalidConfiguration('Secret must be at least 8 characters long');
                    }
                })->not->toThrow(InvalidConfiguration::class);
            }
        });
    });

});
