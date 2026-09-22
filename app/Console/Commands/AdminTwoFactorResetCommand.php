<?php

namespace App\Console\Commands;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * The escape hatch for an admin who has lost both the authenticator and the
 * recovery codes. It needs shell access to the host, which is a stronger proof
 * of control than anything a web form could ask for, and it leaves an audit row
 * so the reset is visible in the same log as every other admin action.
 */
class AdminTwoFactorResetCommand extends Command
{
    protected $signature = 'admin:two-factor-reset {email : The admin account to reset}';

    protected $description = 'Remove an admin account\'s authenticator so they enrol again at next sign-in';

    public function handle(): int
    {
        $user = User::where('email', strtolower(trim((string) $this->argument('email'))))->first();
        if (! $user) {
            $this->error('No account with that address.');

            return self::FAILURE;
        }
        if (! $user->hasTwoFactorEnabled()) {
            $this->warn('That account has no authenticator enrolled; nothing to reset.');

            return self::SUCCESS;
        }
        if (! $this->confirm("Remove the authenticator from {$user->email}? They will be asked to enrol again at their next sign-in.")) {
            return self::SUCCESS;
        }

        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
        AdminAuditLog::create([
            'user_id' => $user->getKey(), 'user_email' => $user->email, 'method' => 'CLI',
            'route_name' => 'admin:two-factor-reset', 'path' => 'artisan admin:two-factor-reset', 'route_params' => null,
            'status' => 200, 'ip_hash' => null, 'user_agent' => 'console', 'created_at' => now(),
        ]);
        $this->info("Authenticator removed from {$user->email}. Recorded in the admin audit log.");

        return self::SUCCESS;
    }
}
