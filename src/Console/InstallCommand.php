<?php

namespace NcooDev\HormLogger\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'horm:install')]
class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'horm:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install all of the Horm resources';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->comment('Publishing Horm Service Provider...');
        $this->callSilent('vendor:publish', ['--tag' => 'horm-logger-provider']);

        $this->comment('Publishing Horm Assets...');
        $this->callSilent('vendor:publish', ['--tag' => 'horm-logger-assets', '--force' => true]);

        $this->comment('Publishing Horm Configuration...');
        $this->callSilent('vendor:publish', ['--tag' => 'horm-logger-config', '--force' => true]);

        $this->comment('Publishing Horm Migrations...');
        $this->call('vendor:publish', ['--tag' => 'horm-logger-migrations']);

        $this->info('Horm installed successfully.');
    }
}
