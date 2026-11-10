<?php

namespace App\Console\Commands;

use App\Enums\AdminRole;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\Admin\AdminInvitations;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Invites a person from the host, the way the admin users page does: the account is
 * created with the role, and an email carries a link to choose a password. Useful for
 * the first editors and reviewers of a new install, or when nobody can reach the page.
 * An existing account is reported and left as it is; roles on existing accounts are
 * changed on the users page, where the change is confirmed and logged.
 */
class AdminInviteCommand extends Command
{
    protected $signature = 'admin:invite
        {email : Address to invite}
        {name : Name on the account}
        {--role=reviewer : editor, reviewer, analyst, or none}
        {--by= : Owner account the invitation comes from (defaults to the first owner with an account)}
        {--note= : A line added to the invitation email}';

    protected $description = 'Create an account with an admin role and email its invitation link';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("\"{$email}\" is not an email address.");

            return self::FAILURE;
        }
        $roleName = (string) $this->option('role');
        $role = $roleName === 'none' ? null : AdminRole::tryFrom($roleName);
        if ($roleName !== 'none' && ! $role) {
            $this->error('Unknown role "'.$roleName.'". Use one of: '.implode(', ', array_map(fn ($r) => $r->value, AdminRole::cases())).', none.');

            return self::FAILURE;
        }
        if ($existing = User::where('email', $email)->first()) {
            $this->warn("{$email} already has an account (role: ".($existing->adminRole()?->value ?? ($existing->isOwner() ? 'owner' : 'none')).'). Nothing changed; change roles on the admin users page.');

            return self::SUCCESS;
        }
        $inviter = $this->inviter();
        if (! $inviter) {
            $this->error('No owner account to send the invitation from. Sign in once as an owner (ADMIN_EMAILS), or pass --by=<owner email>.');

            return self::FAILURE;
        }

        $user = new User(['name' => trim((string) $this->argument('name')), 'email' => $email, 'password' => Str::password(40)]);
        $user->forceFill([
            'invited_at' => now(),
            'invited_by' => $inviter->getKey(),
            'admin_role' => $role,
            'admin_role_granted_by' => $role ? $inviter->getKey() : null,
            'admin_role_granted_at' => $role ? now() : null,
        ])->save();
        $sent = AdminInvitations::send($user, $inviter, $role, $this->option('note') ?: null);
        AdminAuditLog::create([
            'user_id' => $inviter->getKey(), 'user_email' => $inviter->email, 'method' => 'CLI',
            'route_name' => 'admin:invite', 'path' => 'artisan admin:invite '.$email, 'route_params' => null,
            'status' => 200, 'ip_hash' => null, 'user_agent' => 'console', 'created_at' => now(),
        ]);

        if ($sent['delivered']) {
            $this->info("Invited {$email} as ".($role?->label() ?? 'a reader').'. The invitation email was sent.');
        } else {
            $this->warn("Created the account for {$email}, but the invitation was not delivered: {$sent['reason']}");
            $this->line('Send them this link yourself: '.$sent['url']);
        }

        return self::SUCCESS;
    }

    private function inviter(): ?User
    {
        if ($by = $this->option('by')) {
            $user = User::where('email', Str::lower(trim((string) $by)))->first();

            return $user?->isOwner() ? $user : null;
        }

        return User::whereIn('email', array_map('strtolower', config('aipolicytracker.admin_emails', [])))->get()->first(fn (User $u) => $u->isOwner());
    }
}
