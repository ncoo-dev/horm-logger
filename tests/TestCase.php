<?php

namespace NcooDev\HormLogger\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NcooDev\HormLogger\HormLoggerServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

// Create a minimal User model for testing if it doesn't exist
if (! class_exists('User')) {
    class User extends \Illuminate\Foundation\Auth\User
    {
        protected $fillable = ['name', 'email', 'password'];
    }
}

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup factories
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'NcooDev\\HormLogger\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Setup database after parent setup to ensure proper configuration
        $this->setUpDatabase();

        // Ensure routes are registered at the beginning
        $this->refreshServiceProvider();
    }

    protected function getPackageProviders($app)
    {
        return [
            HormLoggerServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        // Setup in-memory SQLite database for testing
        config()->set('horm.database.connection', 'sqlite');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set default HORM configuration for testing
        config()->set('horm.database.table_name', 'horm_entries');
        config()->set('horm.model.entry', \NcooDev\HormLogger\Models\Entry::class);
        config()->set('horm.model.keep_history_for_days', 2);
        config()->set('horm.endpoint.enabled', true);
        config()->set('horm.endpoint.secret', 'test-secret-key');
        config()->set('horm.endpoint.url', 'horm-api-endpoint');

        // Set application encryption key
        config()->set('app.key', 'base64:'.base64_encode(
            Encrypter::generateKey(config()['app.cipher'])
        ));
    }

    protected function setUpDatabase(): void
    {
        $this->migrateHormTable();
    }

    protected function migrateHormTable(): void
    {
        $tableName = config('horm.database.table_name', 'horm_entries');
        $connection = config('horm.database.connection') ?: config('database.default');

        // Check if table already exists to avoid "table already exists" error
        if (! \Illuminate\Support\Facades\Schema::connection($connection)->hasTable($tableName)) {
            // Load and execute the migration using the anonymous class
            $migration = require_once __DIR__.'/../database/migrations/create_horm_entries_table.php.stub';
            $migration->up();
        }
    }

    protected function refreshServiceProvider(): void
    {
        // Ensure the service provider is properly registered and booted
        $this->app->register(HormLoggerServiceProvider::class);

        // Force boot the provider
        $provider = $this->app->getProvider(HormLoggerServiceProvider::class);
        if ($provider) {
            $provider->packageRegistered();
            $provider->packageBooted();
        }
    }

    /**
     * Get the default HORM configuration for testing
     */
    protected function getDefaultHormConfig(): array
    {
        return [
            'database' => [
                'connection' => 'sqlite',
                'table_name' => 'horm_entries',
            ],
            'model' => [
                'entry' => \NcooDev\HormLogger\Models\Entry::class,
                'keep_history_for_days' => 2,
            ],
            'endpoint' => [
                'enabled' => true,
                'secret' => 'test-secret',
                'url' => 'horm-test-endpoint',
            ],
        ];
    }
}
