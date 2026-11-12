<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Consultation;
use App\Models\JobRun;
use App\Models\User;
use App\Services\Consultations\FederalRegisterConsultations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Official consultations: collected from the Federal Register API, kept only when the title
 * or abstract is about AI, shown on the calendar apart from reviewed entries, and moved to
 * confirmed only by a reviewer. A confirmed entry whose date moves goes back to review.
 */
class ConsultationsTest extends TestCase
{
    use RefreshDatabase;

    private function doc(string $number, string $title, string $closes, ?string $abstract = null): array
    {
        return ['document_number' => $number, 'title' => $title, 'type' => 'Notice', 'abstract' => $abstract,
            'agencies' => [['name' => 'Commerce Department'], ['name' => 'National Institute of Standards and Technology']],
            'html_url' => 'https://www.federalregister.gov/documents/x/'.$number, 'comment_url' => 'https://www.regulations.gov/commenton/'.$number,
            'publication_date' => now()->subDays(5)->toDateString(), 'comments_close_on' => $closes];
    }

    /** What the fake API answers; a test changes it between runs. */
    private array $docs = [];

    private bool $faked = false;

    private function fakeApi(array $docs): void
    {
        $this->docs = $docs;
        if (! $this->faked) {
            Http::fake(['www.federalregister.gov/*' => fn () => Http::response(['count' => count($this->docs), 'total_pages' => 1, 'results' => $this->docs])]);
            $this->faked = true;
        }
    }

    private function reviewer(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['admin_role' => AdminRole::Reviewer])->save();

        return $user;
    }

    public function test_only_open_comment_periods_about_ai_are_collected_once_each(): void
    {
        $this->fakeApi([
            $this->doc('2026-1', 'Request for Information on Artificial Intelligence Evaluation', now()->addDays(20)->toDateString()),
            $this->doc('2026-2', 'Medicare Program: Payment Rates', now()->addDays(30)->toDateString(), 'Payment updates for hospitals.'),
            $this->doc('2026-3', 'Guidance on automated decision tools in lending', now()->addDays(10)->toDateString(), 'Proposed guidance on algorithmic underwriting.'),
        ]);

        $this->artisan('consultations:sync')->expectsOutputToContain('2 about AI; 2 new')->assertSuccessful();
        $this->assertEqualsCanonicalizing(['2026-1', '2026-3'], Consultation::pluck('external_id')->all());
        $this->assertSame(['collected'], Consultation::distinct()->pluck('status')->all(), 'nothing is confirmed by the collector');
        $this->assertSame('Commerce Department; National Institute of Standards and Technology', Consultation::where('external_id', '2026-1')->value('agency'));

        // The request asks for open comment periods, with fields as the API wants them.
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'conditions%5Bcomment_date%5D%5Bgte%5D='.now()->toDateString())
            && str_contains($r->url(), 'fields%5B%5D=comments_close_on') && ! str_contains($r->url(), 'fields%5B0%5D'));

        $this->artisan('consultations:sync')->expectsOutputToContain('0 new')->assertSuccessful();
        $this->assertSame(2, Consultation::count());
    }

    public function test_the_calendar_keeps_reviewed_and_unreviewed_apart_and_never_shows_hidden_or_closed(): void
    {
        $base = ['source' => 'federal_register', 'url' => 'https://www.federalregister.gov/documents/x', 'agency' => 'Agency', 'document_type' => 'Notice'];
        Consultation::create($base + ['external_id' => 'a', 'title' => 'Confirmed AI consultation', 'comments_close_on' => now()->addDays(5), 'status' => 'confirmed']);
        Consultation::create($base + ['external_id' => 'b', 'title' => 'Collected AI consultation', 'comments_close_on' => now()->addDays(9), 'status' => 'collected']);
        Consultation::create($base + ['external_id' => 'c', 'title' => 'Hidden consultation', 'comments_close_on' => now()->addDays(9), 'status' => 'hidden']);
        Consultation::create($base + ['external_id' => 'd', 'title' => 'Closed consultation', 'comments_close_on' => now()->subDay(), 'status' => 'confirmed']);

        $html = $this->get(route('calendar'))->assertOk()->getContent();
        $this->assertStringContainsString('Open consultations and comment deadlines', $html);
        $reviewed = substr($html, strpos($html, '>Reviewed<'), strpos($html, 'Collected automatically, not yet reviewed') - strpos($html, '>Reviewed<'));
        $this->assertStringContainsString('Confirmed AI consultation', $reviewed);
        $this->assertStringNotContainsString('Collected AI consultation', $reviewed);
        $unreviewed = substr($html, strpos($html, 'data-unreviewed'));
        $this->assertStringContainsString('Collected AI consultation', $unreviewed);
        $this->assertStringNotContainsString('Hidden consultation', $html);
        $this->assertStringNotContainsString('Closed consultation', $html);
        $this->assertStringNotContainsString('Collected AI consultation', $this->get(route('calendar.feed'))->getContent(), 'the subscription feed carries only recorded deadlines');
    }

    public function test_only_a_reviewer_confirms_and_a_moved_date_sends_it_back_to_review(): void
    {
        $closes = now()->addDays(20)->toDateString();
        $this->fakeApi([$this->doc('2026-9', 'AI risk management profile: request for comments', $closes)]);
        $this->artisan('consultations:sync')->assertSuccessful();
        $c = Consultation::sole();

        // An account without an admin role is turned away by the admin middleware; an admin role
        // without records.verify is refused by the permission.
        $this->actingAs(User::factory()->create())->post(route('backend.admin.consultations.review', $c), ['status' => 'confirmed'])->assertRedirect();
        $analyst = User::factory()->create();
        $analyst->forceFill(['admin_role' => AdminRole::Analyst])->save();
        $this->actingAs($analyst)->post(route('backend.admin.consultations.review', $c), ['status' => 'confirmed'])->assertForbidden();
        $this->assertSame('collected', $c->fresh()->status);
        $reviewer = $this->reviewer();
        $this->actingAs($reviewer)->get(route('backend.admin.consultations.index'))->assertOk()->assertSee('AI risk management profile');
        $this->actingAs($reviewer)->post(route('backend.admin.consultations.review', $c), ['status' => 'confirmed'])->assertRedirect();
        $this->assertSame(['confirmed', $reviewer->id], [$c->fresh()->status, $c->fresh()->reviewed_by]);

        // The same date again changes nothing; a new date needs a person to look again.
        $this->artisan('consultations:sync')->assertSuccessful();
        $this->assertSame('confirmed', $c->fresh()->status);
        $this->fakeApi([$this->doc('2026-9', 'AI risk management profile: request for comments', now()->addDays(40)->toDateString())]);
        $this->artisan('consultations:sync')->expectsOutputToContain('1 confirmed entries back to review')->assertSuccessful();
        $this->assertSame(['collected', null], [$c->fresh()->status, $c->fresh()->reviewed_by]);
    }

    public function test_a_failing_source_fails_the_run_and_keeps_what_was_there(): void
    {
        Consultation::create(['source' => 'federal_register', 'external_id' => 'keep', 'title' => 'Kept', 'url' => 'https://www.federalregister.gov/x', 'comments_close_on' => now()->addDays(3)]);
        Http::fake(['www.federalregister.gov/*' => Http::response('down', 503)]);

        $this->artisan('consultations:sync')->assertFailed();
        $this->assertSame(1, Consultation::count());
    }

    public function test_the_job_is_catalogued_and_scheduled(): void
    {
        $this->assertSame('consultations:sync', JobRun::JOBS['consultations_sync']['command']);
        $this->artisan('schedule:list')->expectsOutputToContain('Collect official consultations')->assertSuccessful();
    }

    /**
     * A meeting is listed by the first full date in the notice's own words, which are shown
     * beside it; a notice with no stated date, or about something other than AI, or already
     * past, is not stored. Meetings are listed apart from comment periods.
     */
    public function test_public_meetings_on_ai_are_listed_by_their_stated_date(): void
    {
        $held = now()->addDays(12);
        $meeting = fn (string $n, string $title, ?string $dates, ?string $abstract = null) => ['document_number' => $n, 'title' => $title, 'type' => 'Notice', 'abstract' => $abstract,
            'agencies' => [['name' => 'Commerce Department']], 'html_url' => 'https://www.federalregister.gov/documents/m/'.$n,
            'publication_date' => now()->subDays(3)->toDateString(), 'comments_close_on' => null, 'dates' => $dates];
        $this->fakeApi([
            $meeting('m1', 'Public Meeting on Artificial Intelligence Standards', $held->format('l, F j, Y').'; 1:00 p.m. ET.'),
            $meeting('m2', 'Workshop on AI evaluation', 'The date will be announced later.'),
            $meeting('m3', 'Public Meeting of the Fisheries Council', $held->format('F j, Y'), 'Quota allocations.'),
            $meeting('m4', 'Public Meeting on Artificial Intelligence in Education', now()->subDays(2)->format('F j, Y')),
        ]);

        $this->artisan('consultations:sync')->expectsOutputToContain('1 upcoming AI meetings or hearings with a stated date, 1 new')->assertSuccessful();
        $m = Consultation::where('kind', 'meeting')->sole();
        $this->assertSame(['m1', $held->toDateString(), 'collected'], [$m->external_id, $m->event_on->toDateString(), $m->status]);
        $this->assertSame(0, Consultation::where('kind', 'comment_period')->count(), 'a notice with no comment date is not a comment period');

        $html = $this->get(route('calendar'))->assertOk()->getContent();
        $this->assertStringContainsString('Upcoming public meetings and hearings', $html);
        $section = substr($html, strpos($html, 'data-unreviewed-meetings'));
        $this->assertStringContainsString('Public Meeting on Artificial Intelligence Standards', $section);
        $this->assertStringContainsString('as published: '.$held->format('l, F j, Y').'; 1:00 p.m. ET.', $section);

        $this->assertSame('2026-12-03', FederalRegisterConsultations::firstDate('Thursday, December 3, 2026; 10 a.m. to 4 p.m., and December 4, 2026'));
        $this->assertNull(FederalRegisterConsultations::firstDate('Comments are due 30 days after publication.'));
    }
}
