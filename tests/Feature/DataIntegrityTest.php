<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Gate 1 evidence: every interlink is proven with a query.
 * For each foreign key, the number of rows must equal the number of rows
 * whose reference resolves. Runs against the seeded schema; links that the
 * seeders populate must also be non-empty so the proof cannot be vacuous.
 */
class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @return array<string, array{string,string,string,string,bool}> */
    public static function interlinks(): array
    {
        return [
            'policies -> countries' => ['ai_policy_trackers', 'country_id', 'countries', 'id', true],
            'policies -> statuses' => ['ai_policy_trackers', 'status_id', 'statuses', 'id', true],
            'news -> policies' => ['news', 'policy_tracker_id', 'ai_policy_trackers', 'id', true],
            'news -> statuses' => ['news', 'status_id', 'statuses', 'id', true],
            'thumbnails -> news' => ['thumbnails', 'news_id', 'news', 'id', false],
            'activity logs -> policies' => ['a_i_policy_activity_logs', 'ai_policy_tracker_id', 'ai_policy_trackers', 'id', true],
            'bookmarks -> users' => ['book_marks', 'user_id', 'users', 'id', false],
            'bookmarks -> policies' => ['book_marks', 'ai_policy_tracker_id', 'ai_policy_trackers', 'id', false],
            'user_infos -> users' => ['user_infos', 'user_id', 'users', 'id', false],
            'nav_bars -> users' => ['nav_bars', 'user_id', 'users', 'id', false],
            'contributing_orgs -> users' => ['contributing_orgs', 'user_id', 'users', 'id', false],
        ];
    }

    #[DataProvider('interlinks')]
    public function test_every_interlink_resolves(string $table, string $column, string $refTable, string $refColumn, bool $mustHaveRows): void
    {
        $total = DB::table($table)->whereNotNull($column)->count();
        $resolves = DB::table($table)
            ->whereNotNull($column)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from($refTable)
                ->whereColumn("$refTable.$refColumn", "$table.$column"))
            ->count();

        if ($mustHaveRows) {
            $this->assertGreaterThan(0, $total, "$table.$column has no rows after seeding");
        }

        $this->assertSame($total, $resolves, "$table.$column: $total rows, $resolves resolve");
        fwrite(STDERR, sprintf("\n[interlink] %s.%s -> %s.%s: total=%d resolves=%d", $table, $column, $refTable, $refColumn, $total, $resolves));
    }

    public function test_policies_module_has_inbound_and_outbound_links(): void
    {
        $this->assertGreaterThan(0, DB::table('ai_policy_trackers')->count(), 'seeded policies');
        $this->assertGreaterThan(0, DB::table('news')->whereNotNull('policy_tracker_id')->count(), 'inbound: news');
        $this->assertGreaterThan(0, DB::table('a_i_policy_activity_logs')->count(), 'inbound: activity logs');
    }
}
