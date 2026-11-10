<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The change desk narrows by search, jurisdiction and legal force, and exports what it shows. */
class ChangeDeskFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    private function reviewer(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['admin_role' => AdminRole::Reviewer])->save();

        return $user;
    }

    /** @return list<array<int, string>> */
    private function csv(string $url, User $as): array
    {
        $body = $this->actingAs($as)->get($url)->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);

        return array_map('str_getcsv', array_values(array_filter(explode("\n", trim(substr($body, 3))))));
    }

    public function test_the_list_narrows_by_jurisdiction_binding_and_search(): void
    {
        $reviewer = $this->reviewer();
        $html = $this->actingAs($reviewer)->get(route('backend.admin.changedesk.index', ['show' => 'all', 'jurisdiction' => 'eu']))->assertOk()->getContent();
        $this->assertStringContainsString('EU AI Act', $html);
        $this->assertStringNotContainsString('Texas Responsible AI Governance Act', $html);
        $this->assertStringContainsString('<input type="hidden" name="show" value="all">', $html, 'filtering keeps the view');

        $html = $this->actingAs($reviewer)->get(route('backend.admin.changedesk.index', ['show' => 'all', 'q' => 'AIPT-POL-0073']))->assertOk()->getContent();
        $this->assertStringContainsString('EU AI Act', $html);
        $this->assertMatchesRegularExpression('#<span class="font-semibold text-brand-navy">1</span> instrument#', $html);
    }

    public function test_the_export_carries_exactly_the_filtered_rows(): void
    {
        $reviewer = $this->reviewer();
        $rows = $this->csv(route('backend.admin.changedesk.export', ['show' => 'all', 'binding' => 'yes', 'jurisdiction' => 'eu']), $reviewer);
        $this->assertSame(['aipt_id', 'instrument', 'jurisdiction', 'binding', 'obligations', 'watchers', 'cadence_days', 'last_checked', 'last_outcome', 'due', 'overdue_days', 'official_source_url'], $rows[0]);
        $this->assertGreaterThan(1, count($rows));
        foreach (array_slice($rows, 1) as $row) {
            $this->assertSame('European Union', $row[2]);
            $this->assertSame('yes', $row[3]);
        }
        $this->actingAs(User::factory()->create())->get(route('backend.admin.changedesk.export'))->assertRedirect();
    }
}
