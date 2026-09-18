<?php

namespace App\Console\Commands;

use App\Services\Security\EmailDomainPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Loads an operator-chosen list of throwaway domains into the overlay file.
 *
 * Deliberately not a scheduled job and deliberately not shipped with a default
 * source. A community blocklist is somebody else's work under somebody else's
 * licence, and a list of tens of thousands of domains fetched automatically is
 * a list nobody has read. The operator names the URL, reads its licence, and
 * owns the result; the overlay lives outside the repository so the project
 * never redistributes it.
 *
 * The curated list in data/email/ keeps working whether or not this is ever run.
 */
class EmailDomainsRefreshCommand extends Command
{
    protected $signature = 'email:domains-refresh
        {--source= : URL of a plain-text list, one domain per line}
        {--dry-run : Report what would change and write nothing}';

    protected $description = 'Refresh the operator overlay of throwaway e-mail domains from a chosen source';

    public function handle(EmailDomainPolicy $policy): int
    {
        $source = (string) $this->option('source');
        if ($source === '' || ! filter_var($source, FILTER_VALIDATE_URL) || ! str_starts_with($source, 'https://')) {
            $this->error('Pass --source with an https:// URL of a plain-text list. No default is assumed: you choose the source and accept its licence.');

            return self::FAILURE;
        }

        try {
            $response = Http::timeout(30)->withHeaders(['Accept' => 'text/plain'])->get($source);
        } catch (\Throwable $e) {
            $this->error('Could not fetch the list: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('The source answered '.$response->status().'.');

            return self::FAILURE;
        }

        $domains = [];
        foreach (preg_split('/\R/', $response->body()) ?: [] as $line) {
            $line = strtolower(trim((string) $line));
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            // Only well-formed domains are kept. A malformed line in somebody
            // else's file must not become a rule that refuses real sign-ups.
            if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $line)) {
                $domains[$line] = true;
            }
        }
        $domains = array_keys($domains);

        if ($domains === []) {
            $this->error('The source contained no usable domain. Nothing written.');

            return self::FAILURE;
        }

        // A domain on the trusted list would be shadowed by it anyway, but it is
        // better to say so than to store a line that silently never applies.
        $shadowed = array_values(array_filter($domains, fn ($d) => $policy->trustedEntryFor($d) !== null));

        $path = (string) config('email.overlay');
        $existing = is_file($path) ? count(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : 0;

        $this->line('Fetched '.count($domains).' domains from '.$source);
        $this->line('Overlay currently holds '.$existing.' lines.');
        if ($shadowed !== []) {
            $this->warn(count($shadowed).' of them are on the trusted list and will keep being accepted: '.implode(', ', array_slice($shadowed, 0, 10)));
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing written.');

            return self::SUCCESS;
        }

        @mkdir(dirname($path), 0755, true);
        $header = "# Operator overlay, written by `php artisan email:domains-refresh`.\n"
            .'# Source: '.$source."\n"
            .'# Fetched: '.now()->toIso8601String()."\n"
            ."# Edit by hand or re-run the command. Not tracked in the repository.\n";
        file_put_contents($path, $header.implode("\n", $domains)."\n");

        $this->info('Wrote '.count($domains).' domains to '.$path);
        $this->line('Run `php artisan config:clear` only if you changed the path; the file itself is read on each request.');

        return self::SUCCESS;
    }
}
