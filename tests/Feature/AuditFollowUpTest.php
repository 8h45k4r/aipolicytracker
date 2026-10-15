<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\ExternalIncident;
use App\Models\Jurisdiction;
use App\Models\ReportSnapshot;
use App\Services\Report\StateOfAiRegulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The "Next: correctness and data" items of docs/reference/code-audit-2026-10-14.md. */
class AuditFollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_frozen_quarter_is_not_rewritten_by_a_second_freeze(): void
    {
        $first = StateOfAiRegulation::freeze('2026-Q2');
        ReportSnapshot::whereKey($first->id)->update(['data' => json_encode(['marker' => 'as cited'])]);

        $second = StateOfAiRegulation::freeze('2026-Q2');
        $this->assertSame($first->id, $second->id);
        $this->assertSame(['marker' => 'as cited'], $second->fresh()->data);
    }

    public function test_first_publication_is_stamped_when_a_change_is_published_not_when_it_is_created(): void
    {
        $eu = Jurisdiction::create(['slug' => 'eu', 'name' => 'European Union', 'published_at' => now()]);
        $change = ChangeEvent::create(['slug' => 'draft-change', 'jurisdiction_id' => $eu->id, 'occurred_on' => now()->toDateString(), 'title' => 'A draft', 'what_changed' => 'Not yet public.', 'impact_level' => 'routine', 'published_at' => null]);
        $this->assertNull($change->fresh()->first_published_at);

        $this->travel(5)->days();
        $change->update(['published_at' => now()]);
        $this->assertTrue($change->fresh()->first_published_at->isToday());

        $this->travel(5)->days();
        $change->update(['title' => 'Retitled']);
        $this->assertSame(now()->subDays(5)->toDateString(), $change->fresh()->first_published_at->toDateString(), 'set once');
    }

    public function test_the_incident_api_filters_by_domain_number_and_any_case_country(): void
    {
        ExternalIncident::create(['incident_id' => 9001, 'slug' => 'privacy-case', 'title' => 'A privacy case', 'mit_domain' => 'Privacy & Security', 'countries' => ['US'], 'occurred_on' => '2025-01-01', 'year' => 2025]);
        ExternalIncident::create(['incident_id' => 9002, 'slug' => 'misinfo-case', 'title' => 'A misinformation case', 'mit_domain' => 'Misinformation', 'countries' => ['GB'], 'occurred_on' => '2025-01-02', 'year' => 2025]);

        $this->getJson('/api/v1/incidents?domain=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'A privacy case');
        $this->getJson('/api/v1/incidents?domain=99')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/incidents?country=gb')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'A misinformation case');
    }
}
