<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ResetRefreshDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resets/Refresh the database(ie: dp wipe, migrate, seed)';

    /**
     * Execute the console command.
     *
     * aqqqq
     * @return int
     */
    public function handle()
    {
        //-- Wipe the database
        $this->call('db:wipe');

        //-- Migrate the database
        $this->call('migrate');

        //-- Seed the database
        $this->call('db:seed');

        return Command::SUCCESS;
    }
}
