<?php

namespace Tests\Feature;

use Database\Seeders\AiPolicyTrackerSeeder;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Gate 4 evidence: every dataset entry carries an official https source,
 * an access date, a valid status, and a jurisdiction code.
 */
class PolicyDatasetTest extends TestCase
{
    private const STATUSES = ['research', 'whitepaper', 'pilot', 'development', 'launched', 'cancelled'];

    private const FORBIDDEN_HOSTS = ['wikipedia.org', 'medium.com', 'linkedin.com', 'twitter.com', 'x.com', 'facebook.com', 'reuters.com', 'bloomberg.com', 'lexology.com', 'jdsupra.com'];

    public function test_dataset_entries_are_source_backed(): void
    {
        $entries = json_decode(File::get(base_path(AiPolicyTrackerSeeder::DATASET)), true, 512, JSON_THROW_ON_ERROR);
        $this->assertGreaterThanOrEqual(30, count($entries), 'dataset size');

        $seen = [];
        foreach ($entries as $i => $e) {
            $key = $e['country_symbol'].'|'.$e['ai_policy_name'];
            $this->assertArrayNotHasKey($key, $seen, "duplicate entry $key");
            $seen[$key] = true;

            $this->assertMatchesRegularExpression('/^[A-Z]{2}(-[A-Z]{3})?$/', $e['country_symbol'], "#$i country_symbol");
            $this->assertNotEmpty($e['country_name'], "#$i country_name");
            $this->assertNotEmpty($e['ai_policy_name'], "#$i ai_policy_name");
            $this->assertContains($e['gov_ai_index'], ['policy', 'strategy'], "#$i gov_ai_index");
            $this->assertContains($e['status'], self::STATUSES, "#$i status");
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $e['announcement_date'], "#$i announcement_date");
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $e['date_accessed'], "#$i date_accessed");
            $this->assertStringStartsWith('https://', $e['source_url'], "#$i source_url must be https");
            $host = parse_url($e['source_url'], PHP_URL_HOST);
            foreach (self::FORBIDDEN_HOSTS as $bad) {
                $this->assertStringNotContainsString($bad, $host, "#$i source must be official, not $bad");
            }
            $this->assertNotEmpty($e['source_publisher'], "#$i source_publisher");
            $this->assertGreaterThanOrEqual(120, strlen($e['description']), "#$i description too short");
            foreach ($e['milestones'] ?? [] as $m) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $m['date'], "#$i milestone date");
                $this->assertStringStartsWith('https://', $m['source_url'], "#$i milestone source");
            }
        }
    }
}
