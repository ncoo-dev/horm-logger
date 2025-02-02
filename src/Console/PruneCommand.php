<?php

namespace NcooDev\HormLogger\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'horm:prune')]
class PruneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'horm:prune {--pretend}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old entries from the database.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->call('model:prune', [
            '--model' => [config('horm.model.entry')],
            '--pretend' => $this->option('pretend'),
        ]);

    }
}
