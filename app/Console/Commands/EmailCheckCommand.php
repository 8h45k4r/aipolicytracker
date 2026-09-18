<?php

namespace App\Console\Commands;

use App\Services\Security\EmailDomainPolicy;
use Illuminate\Console\Command;

/**
 * Answers "why was my address refused?" with a file and a line.
 *
 * The first false positive will arrive as a support message from somebody with
 * a real work address, and the answer has to be specific enough to act on.
 */
class EmailCheckCommand extends Command
{
    protected $signature = 'email:check {address* : One or more addresses to test}';

    protected $description = 'Show what the address policy decides about an address, and which rule decided it';

    public function handle(EmailDomainPolicy $policy): int
    {
        $rows = [];
        $refused = 0;

        foreach ((array) $this->argument('address') as $address) {
            $verdict = $policy->inspect((string) $address);
            $refused += $verdict->allowed ? 0 : 1;
            $rows[] = [
                $address,
                $verdict->domain ?? '—',
                $verdict->allowed ? 'accepted' : 'refused',
                $verdict->reason ?? '—',
                $verdict->evidence ?? '—',
            ];
        }

        $this->table(['Address', 'Domain', 'Verdict', 'Reason', 'Decided by'], $rows);

        if (! config('email.enforce', true)) {
            $this->warn('email.enforce is off, so nothing is actually refused at the forms.');
        }

        // Non-zero when something was refused, so this is usable in a script.
        return $refused === 0 ? self::SUCCESS : self::FAILURE;
    }
}
