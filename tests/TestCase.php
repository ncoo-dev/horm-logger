<?php

namespace NcooDev\HormLogger\Tests;

use Illuminate\Encryption\Encrypter;
use NcooDev\HormLogger\HormLoggerServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            HormLoggerServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('activitylog.database_connection', 'sqlite');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        config()->set('auth.providers.users.model', User::class);
        config()->set('app.key', 'base64:'.base64_encode(
            Encrypter::generateKey(config()['app.cipher'])
        ));
    }

    protected function setUpDatabase()
    {
        $this->migrateHormTable();

        //        $this->seedModels(Article::class, User::class);
    }

    protected function migrateHormTable()
    {
        require_once __DIR__.'/../database/migrations/create_horm_entries_table.php.stub';

        (new \CreateHormEntriesTable)->up();
    }

    public function markTestAsPassed(): void
    {
        $this->assertTrue(true);
    }
}
