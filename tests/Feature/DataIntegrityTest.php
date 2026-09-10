<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gate 1 evidence: every interlink is proven with a query.
 * For each foreign key, the number of rows must equal the number of rows
 * whose reference resolves. Runs against the seeded schema.
 */
class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** @return array<string, array{string,string,string,string}> */
    public static function interlinks(): array
    {
        return [
            'policies -> countries' => ['ai_policy_trackers', 'country_id', 'countries', 'id'],
            'policies -> statuses' => ['ai_policy_trackers', 'status_id', 'statuses', 'id'],
            'news -> policies' => ['news', 'policy_tracker_id', 'ai_policy_trackers', 'id'],
            'news -> statuses' => ['news', 'status_id', 'statuses', 'id'],
            'thumbnails -> news' => ['thumbnails', 'news_id', 'news', 'id'],
            'activity logs -> policies' => ['a_i_policy_activity_logs', 'ai_policy_tracker_id', 'ai_policy_trackers', 'id'],
            'bookmarks -> users' => ['book_marks', 'user_id', 'users', 'id'],
            'bookmarks -> policies' => ['book_marks', 'ai_policy_tracker_id', 'ai_policy_trackers', 'id'],
            'user_infos -> users' => ['user_infos', 'user_id', 'users', 'id'],
            'nav_bars -> users' => ['nav_bars', 'user_id', 'users', 'id'],
            'contributing_orgs -> users' => ['contributing_orgs', 'user_id', 'users', 'id'],
        ];
    }

    /** @dataProvider interlinks */
    public function test_every_interlink_resolves(string $table, string $column, string $refTable, string $refColumn): void
    {
        $total = DB::table($table)->whereNotNull($column)->count();
        $resolves = DB::table($table)
            ->whereNotNull($column)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from($refTable)
                ->whereColumn("$refTable.$refColumn", "$table.$column"))
            ->count();

        $this->assertSame($total, $resolves, "$table.$column: $total rows, $resolves resolve");
    }

    public function test_policies_module_has_inbound_and_outbound_links(): void
    {
        $this->assertGreaterThan(0, DB::table('ai_policy_trackers')->count(), 'seeded policies');
        $this->assertGreaterThan(0, DB::table('news')->whereNotNull('policy_tracker_id')->count(), 'inbound: news');
        $this->assertGreaterThan(0, DB::table('a_i_policy_activity_logs')->count(), 'inbound: activity logs');
    }
}
