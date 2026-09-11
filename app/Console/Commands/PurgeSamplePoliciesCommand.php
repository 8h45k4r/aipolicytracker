<?php

namespace App\Console\Commands;

use App\Models\AiPolicyTracker;
use Illuminate\Console\Command;

/**
 * Removes the fictional sample policies that earlier versions of the seeder
 * created (they had invented titles and URLs). Idempotent; safe to run on
 * every startup. Related news and bookmarks cascade at the database level.
 */
class PurgeSamplePoliciesCommand extends Command
{
    protected $signature = 'policies:purge-sample';

    protected $description = 'Delete the fictional sample policies shipped by pre-1.1 seeders';

    public const SAMPLE_NAMES = [
        'Act of AI Regulation',
        'AI Ethics Act',
        'AI Surveillance Regulation',
        'AI Innovation Act',
        'AI Accountability Framework',
        'AI Fairness Act',
        'AI Safety Protocol',
        'AI Transparency Act',
        'AI Ethics and Governance Framework',
        'AI Impact Assessment Act',
        'Global AI Collaboration Framework',
        'AI Research and Development Act',
    ];

    public function handle(): int
    {
        $query = AiPolicyTracker::withTrashed()
            ->whereIn('ai_policy_name', self::SAMPLE_NAMES)
            ->where('whitepaper_document_link', 'like', 'http://www.%');

        $count = $query->count();
        foreach ($query->get() as $policy) {
            $policy->news()->forceDelete();
            $policy->aIPolicyActivityLogs()->delete();
            $policy->forceDelete();
        }

        $this->info("Removed {$count} sample policies.");

        return self::SUCCESS;
    }
}
