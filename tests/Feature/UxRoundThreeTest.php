<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\PolicyInstrument;
use App\Models\SourceCheck;
use App\Models\User;
use App\Services\PolicyData\PolicyCatalog;
use App\Services\PolicyData\RecordStanding;
use App\Services\Reviewers\ReviewerRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Third UX round: labels that must not overstate a record (a draft is not yet
 * binding, an unreviewed record is not "good", an old change is not urgent now),
 * the listing order a reader sees, and one H1 on every auth page.
 */
class UxRoundThreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_a_binding_draft_reads_binding_once_adopted(): void
    {
        $draft = PolicyInstrument::published()->where('status', 'proposed')->firstOrFail();
        PolicyInstrument::whereKey($draft->id)->update(['is_binding' => true]);

        $this->get('/policies?status=proposed&binding=yes')->assertOk()->assertSee('Binding once adopted');
        $this->get($draft->url())->assertOk()->assertSee('Binding once adopted');

        $inForce = PolicyInstrument::published()->where('status', 'in_force')->where('is_binding', true)->firstOrFail();
        $this->get($inForce->url())->assertOk()->assertDontSee('Binding once adopted');
    }

    public function test_the_binding_sort_orders_by_the_title_a_row_shows(): void
    {
        $shown = app(PolicyCatalog::class)->policyQuery(['sort' => 'binding'])->get()
            ->where('is_binding', true)
            ->map(fn ($p) => mb_strtolower($p->short_title ?: $p->title))->values()->all();
        $sorted = $shown;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $shown);
    }

    public function test_a_record_pending_review_never_gets_the_good_tone(): void
    {
        $quiet = PolicyInstrument::where('review_status', 'verified')->whereDoesntHave('changeEvents')->firstOrFail();
        SourceCheck::create(['record_slug' => $quiet->slug, 'outcome' => 'no_change', 'checked_at' => now()->subDay()]);
        $this->assertSame('good', RecordStanding::forPolicy($quiet)['tone']);

        PolicyInstrument::whereKey($quiet->id)->update(['review_status' => 'pending_review']);
        $standing = RecordStanding::forPolicy($quiet->fresh());
        $this->assertSame('neutral', $standing['tone']);
        $this->assertStringStartsWith('No change recorded as of', $standing['label']);
    }

    public function test_a_future_dated_change_is_not_reported_as_changed(): void
    {
        $change = ChangeEvent::published()->whereNotNull('policy_instrument_id')->firstOrFail();
        ChangeEvent::query()->where('policy_instrument_id', $change->policy_instrument_id)->update(['occurred_on' => now()->subYears(2)]);
        ChangeEvent::whereKey($change->id)->update(['occurred_on' => now()->addDays(5)]);

        $this->assertNotSame('info', RecordStanding::forPolicy($change->policyInstrument()->first())['tone']);
    }

    public function test_an_old_urgent_change_is_labelled_urgent_at_the_time(): void
    {
        $change = ChangeEvent::published()->firstOrFail();
        ChangeEvent::whereKey($change->id)->update(['impact_level' => 'urgent', 'occurred_on' => now()->subYear()]);
        $this->get($change->url())->assertOk()->assertSee('Urgent at the time')->assertDontSee('bg-state-badbg text-state-bad', false);

        ChangeEvent::whereKey($change->id)->update(['occurred_on' => now()->subDays(10)]);
        $this->get($change->url())->assertOk()->assertDontSee('Urgent at the time');
    }

    public function test_every_auth_page_has_exactly_one_h1(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, '<h1'), $path);
        }
    }

    public function test_profile_and_reviewer_pages_compile_without_raw_directives(): void
    {
        $pages = ['/reviewers'];
        $first = app(ReviewerRoster::class)->published()->first();
        if ($first) {
            $pages[] = route('reviewers.show', $first['slug']);
        }
        foreach ($pages as $page) {
            $this->get($page)->assertOk()->assertDontSee('@if', false)->assertDontSee('@endif', false);
        }

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertDontSee('@if', false)->assertDontSee('@endif', false);
    }
}
