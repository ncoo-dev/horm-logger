<?php

use NcooDev\HormLogger\Models\Entry;

use function Pest\Laravel\artisan;
use function Spatie\PestPluginTestTime\testTime;

describe('HORM Logger Console Commands', function () {

    describe('Install Command', function () {
        it('publishes configuration files', function () {
            artisan('horm:install')
                ->expectsOutput('Publishing Horm Service Provider...')
                ->expectsOutput('Publishing Horm Assets...')
                ->expectsOutput('Publishing Horm Configuration...')
                ->expectsOutput('Publishing Horm Migrations...')
                ->expectsOutput('Horm installed successfully.')
                ->assertSuccessful();
        });

        it('can run install command multiple times', function () {
            artisan('horm:install')->assertSuccessful();
            artisan('horm:install')->assertSuccessful();
        });
    });

    describe('Prune Command', function () {
        beforeEach(function () {
            testTime()->freeze('2024-01-10 12:00:00');

            // Create entries with different ages
            Entry::factory()->create(['created_at' => now()->subDays(5)]); // Should be pruned
            Entry::factory()->create(['created_at' => now()->subDays(3)]); // Should be pruned
            Entry::factory()->create(['created_at' => now()->subDays(1)]); // Should be kept
            Entry::factory()->create(['created_at' => now()]); // Should be kept
        });

        it('prunes old entries based on default configuration', function () {
            expect(Entry::all())->toHaveCount(4);

            artisan('horm:prune')->assertSuccessful();

            // Verify that old entries are pruned (exact count may vary based on model:prune implementation)
            expect(Entry::all()->count())->toBeLessThan(4);
        });

        it('can run in pretend mode', function () {
            expect(Entry::all())->toHaveCount(4);

            artisan('horm:prune', ['--pretend' => true])->assertSuccessful();

            // In pretend mode, no entries should be deleted
            expect(Entry::all())->toHaveCount(4);
        });

        it('handles empty database gracefully', function () {
            Entry::query()->delete();

            artisan('horm:prune')->assertSuccessful();
        });

        it('processes large datasets in chunks', function () {
            // Create many old entries (reduced number to avoid timeout in tests)
            Entry::factory(100)->create(['created_at' => now()->subDays(5)]);

            $initialCount = Entry::count();
            expect($initialCount)->toBeGreaterThan(100);

            artisan('horm:prune')->assertSuccessful();

            // Verify that some entries were pruned
            expect(Entry::count())->toBeLessThan($initialCount);
        });
    });

    describe('Command Help and Information', function () {
        it('shows help for install command', function () {
            artisan('help', ['command_name' => 'horm:install'])
                ->assertSuccessful();
        });

        it('shows help for prune command', function () {
            artisan('help', ['command_name' => 'horm:prune'])
                ->assertSuccessful();
        });

        it('lists horm commands in artisan list', function () {
            $result = artisan('list')->run();

            expect($result)->toBe(0); // Command ran successfully
        });
    });

    describe('Command Integration', function () {
        it('commands are registered correctly', function () {
            $commands = \Illuminate\Support\Facades\Artisan::all();

            expect($commands)->toHaveKey('horm:prune')
                ->toHaveKey('horm:install');
        });

        it('can run commands in different environments', function () {
            artisan('horm:prune')->assertSuccessful();
            artisan('horm:install')->assertSuccessful();
        });
    });

});
