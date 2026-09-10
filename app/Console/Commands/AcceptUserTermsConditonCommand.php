<?php

namespace App\Console\Commands;

use App\Models\UserInfo;
use Illuminate\Console\Command;

class AcceptUserTermsConditonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:terms-condition';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'update user info table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        UserInfo::query()->update(['terms_condition' => true]);
    }
}
