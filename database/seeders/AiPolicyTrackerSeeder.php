<?php

namespace Database\Seeders;

use App\Models\AiPolicyTracker;
use App\Models\AIPolicyActivityLog;
use App\Models\Country;
use App\Models\Status;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Loads the source-backed policy dataset from database/data/ai_policies.json.
 *
 * Idempotent: entries are matched on (policy name, jurisdiction) and updated in
 * place, so the seeder can be re-run after the dataset changes. Every entry in
 * the dataset carries an official source URL and access date; see
 * SOURCE_ATTRIBUTION.md and docs/modules/policies.md.
 */
class AiPolicyTrackerSeeder extends Seeder
{
    public const DATASET = 'database/data/ai_policies.json';

    public function run(): void
    {
        $entries = json_decode(File::get(base_path(self::DATASET)), true, 512, JSON_THROW_ON_ERROR);
        $statuses = Status::pluck('id', 'name');
        $created = 0;
        $updated = 0;

        foreach ($entries as $entry) {
            $country = Country::firstOrCreate(
                ['symbol' => strtoupper($entry['country_symbol'])],
                ['name' => $entry['country_name'], 'status' => true]
            );

            $status = $statuses[$entry['status']] ?? null;
            if (! $status) {
                throw new \RuntimeException("Unknown status '{$entry['status']}' for {$entry['ai_policy_name']}");
            }

            $attributes = [
                'gov_ai_index' => $entry['gov_ai_index'],
                'status_id' => $status,
                'governing_body' => $entry['governing_body'] ?? null,
                'announcement_year' => $entry['announcement_date'],
                'whitepaper_document_link' => $entry['source_url'],
                'technology_partners' => $entry['technology_partners'] ?? null,
                'governance_structure' => Str::limit($entry['governance_structure'] ?? '', 250, ''),
                'main_motivation' => Str::limit($entry['main_motivation'] ?? '', 250, ''),
                'description' => $this->describe($entry),
            ];

            $policy = AiPolicyTracker::withTrashed()->firstOrNew([
                'ai_policy_name' => $entry['ai_policy_name'],
                'country_id' => $country->id,
            ]);
            $isNew = ! $policy->exists;
            $policy->fill($attributes);
            $policy->deleted_at = null;
            $policy->save();
            $isNew ? $created++ : $updated++;

            if ($isNew) {
                AIPolicyActivityLog::create([
                    'user_id' => null,
                    'ai_policy_tracker_id' => $policy->id,
                    'activity_name' => 'added data',
                    'description' => 'Imported from the source-backed dataset ('.$entry['source_publisher'].', accessed '.$entry['date_accessed'].').',
                ]);
            }

            foreach ($entry['milestones'] ?? [] as $milestone) {
                $policy->news()->updateOrCreate(
                    ['title' => $milestone['title']],
                    [
                        'status_id' => $status,
                        'upload_date' => $milestone['date'],
                        'description' => '<p>'.e($milestone['title']).' Source: <a href="'.e($milestone['source_url']).'" rel="noopener">'.e($milestone['source_url']).'</a></p>',
                    ]
                );
            }
        }

        $this->command?->info("Policies: {$created} created, {$updated} updated from ".self::DATASET);
    }

    private function describe(array $entry): string
    {
        $html = '<p>'.e($entry['description']).'</p>';
        $html .= '<p><strong>Official source:</strong> <a href="'.e($entry['source_url']).'" rel="noopener">'.e($entry['source_publisher']).'</a> (accessed '.e($entry['date_accessed']).').';
        if (! empty($entry['notes'])) {
            $html .= ' <em>Note:</em> '.e($entry['notes']);
        }

        return $html.'</p>';
    }
}
