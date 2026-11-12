<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Deadline;
use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Services\PolicyData\DeadlineCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeadlineCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    private function instrument(): PolicyInstrument
    {
        return PolicyInstrument::published()->firstOrFail();
    }

    private function deadline(array $attrs = []): Deadline
    {
        return Deadline::create(array_merge([
            'policy_instrument_id' => $this->instrument()->id,
            'title' => 'Test application date',
            'due_on' => now()->addDays(45)->toDateString(),
            'date_precision' => 'exact',
            'deadline_status' => 'scheduled',
            'confidence_level' => 'high',
            'sort_order' => 90,
        ], $attrs));
    }

    public function test_the_feed_is_a_calendar_document_with_one_dated_event_per_deadline(): void
    {
        $deadline = $this->deadline();

        $response = $this->get('/calendar/ai-policy-deadlines.ics')->assertOk();
        $this->assertStringContainsString('text/calendar', $response->headers->get('Content-Type'));
        $body = $response->getContent();

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $body);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $body);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:'.$deadline->due_on->format('Ymd'), $body);
        $this->assertStringContainsString('UID:deadline-'.$deadline->id.'@', $body);
        $this->assertStringContainsString('TRIGGER:-P30D', $body, 'a reminder 30 days ahead');
        $this->assertStringContainsString('TRIGGER:-P7D', $body, 'a reminder 7 days ahead');
        $this->assertSame(substr_count($body, 'BEGIN:VEVENT'), substr_count($body, 'END:VEVENT'));
    }

    public function test_only_dates_precise_enough_to_act_on_are_published(): void
    {
        $exact = $this->deadline(['title' => 'Exact dated duty']);
        $month = $this->deadline(['title' => 'Month only duty', 'date_precision' => 'month']);
        $tbd = $this->deadline(['title' => 'Undecided duty', 'date_precision' => 'tbd']);
        $passed = $this->deadline(['title' => 'Already passed duty', 'deadline_status' => 'passed']);
        $superseded = $this->deadline(['title' => 'Superseded duty', 'deadline_status' => 'superseded']);

        $body = $this->get('/calendar/ai-policy-deadlines.ics')->assertOk()->getContent();

        $this->assertStringContainsString('Exact dated duty', $body);
        foreach ([$month, $tbd, $passed, $superseded] as $excluded) {
            $this->assertStringNotContainsString($excluded->title, $body, 'a calendar entry must not claim precision the record lacks');
        }
        $this->assertSame(1, substr_count($body, 'SUMMARY:'.($exact->policyInstrument->jurisdiction->short_name ?: $exact->policyInstrument->jurisdiction->name)) > 0 ? 1 : 0);
    }

    public function test_each_event_carries_the_record_status_and_the_no_advice_line(): void
    {
        $deadline = $this->deadline();
        $deadline->policyInstrument->update(['review_status' => 'pending_review', 'reviewed_by' => null, 'last_verified_at' => null]);
        $body = str_replace(["\r\n ", "\r\n"], ['', "\n"], $this->get('/calendar/ai-policy-deadlines.ics')->assertOk()->getContent());

        $this->assertStringContainsString('Record status: pending review', $body, 'the reader is told the record is not yet verified');
        $this->assertStringContainsString('not legal advice', $body);
        $this->assertStringContainsString('Date confidence: high', $body);
    }

    public function test_a_jurisdiction_feed_carries_only_that_jurisdiction(): void
    {
        $mine = $this->deadline(['title' => 'In scope duty']);
        $place = $mine->policyInstrument->jurisdiction;

        $elsewhere = PolicyInstrument::published()->whereHas('jurisdiction', fn ($q) => $q->where('id', '!=', $place->id))->firstOrFail();
        $this->deadline(['title' => 'Out of scope duty', 'policy_instrument_id' => $elsewhere->id]);

        $body = $this->get('/calendar/'.$place->slug.'.ics')->assertOk()->getContent();
        $this->assertStringContainsString('In scope duty', $body);
        $this->assertStringNotContainsString('Out of scope duty', $body);

        $this->get('/calendar/no-such-jurisdiction.ics')->assertNotFound();
    }

    public function test_the_page_explains_the_feed_lists_the_dates_and_is_in_the_sitemap(): void
    {
        $deadline = $this->deadline();

        $this->get('/calendar')->assertOk()
            ->assertSee('Application dates in your own calendar')
            ->assertSee(route('calendar.feed'))
            ->assertSee($deadline->title)
            ->assertSee('What is in the feed');

        $this->get('/sitemap-static.xml')->assertOk()->assertSee('/calendar');
    }

    public function test_an_empty_corpus_still_returns_a_valid_calendar(): void
    {
        Deadline::query()->update(['deadline_status' => 'passed']);
        $body = $this->get('/calendar/ai-policy-deadlines.ics')->assertOk()->getContent();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringNotContainsString('BEGIN:VEVENT', $body);
        $this->get('/calendar')->assertOk()->assertSee('No dated deadlines are scheduled right now');
    }

    public function test_no_content_line_exceeds_the_calendar_format_limit(): void
    {
        $this->deadline(['title' => str_repeat('A very long application deadline title ', 6), 'description' => str_repeat('Detail. ', 60)]);
        $body = $this->get('/calendar/ai-policy-deadlines.ics')->assertOk()->getContent();

        foreach (explode("\r\n", $body) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), 'lines must be folded to 75 octets');
        }
    }

    public function test_jurisdictions_without_dates_are_not_offered_on_the_page(): void
    {
        $this->deadline();
        $offered = Jurisdiction::published()->whereHas('policyInstruments.deadlines')->pluck('slug');
        $this->assertNotEmpty($offered);

        $html = $this->get('/calendar')->assertOk()->getContent();
        foreach ($offered as $slug) {
            $this->assertStringContainsString(DeadlineCalendar::subscribeUrl(route('calendar.feed.jurisdiction', $slug)), $html);
        }
    }

    /**
     * The page answers "what is the next AI compliance deadline?" from the records before
     * anything else, lists what is ahead by year, keeps recently passed dates in view, and
     * names them in its structured data.
     */
    public function test_the_page_answers_with_the_next_deadline_and_keeps_recent_ones(): void
    {
        Deadline::query()->update(['deadline_status' => 'passed']);
        $soon = $this->deadline(['title' => 'Soonest test date', 'due_on' => now()->addDays(3)->toDateString()]);
        $this->deadline(['title' => 'Later test date', 'due_on' => now()->addYears(2)->toDateString()]);
        $this->deadline(['title' => 'Month-only test date', 'due_on' => now()->addDays(2)->toDateString(), 'date_precision' => 'month']);
        $this->deadline(['title' => 'Passed test date', 'due_on' => now()->subDays(10)->toDateString(), 'deadline_status' => 'passed']);
        $this->deadline(['title' => 'Old passed test date', 'due_on' => now()->subDays(400)->toDateString(), 'deadline_status' => 'passed']);

        $html = $this->get('/calendar')->assertOk()->getContent();
        $this->assertStringContainsString('<title>Upcoming AI compliance deadlines', $html);
        $this->assertMatchesRegularExpression('#data-answer>The next recorded deadline is <strong>Soonest test date</strong>.*'.preg_quote($soon->due_on->format('j F Y'), '#').'#', $html);
        $this->assertStringContainsString('Later test date', $html);
        $this->assertStringNotContainsString('Month-only test date', $html, 'a date not recorded to the day is not listed');
        $this->assertStringContainsString('Recently passed', $html);
        $this->assertStringContainsString('Passed test date', $html);
        $this->assertStringNotContainsString('Old passed test date', $html);
        $this->assertStringContainsString('"name":"'.$soon->due_on->toDateString().': Soonest test date"', $html, 'listed in the page ItemList');
        $this->assertLessThan(strpos($html, 'Later test date'), strpos($html, 'Soonest test date'), 'in date order');
    }

    /**
     * The same dates as a month grid: a deadline, a comment period closing and a meeting each
     * sit on their day, an unreviewed entry is marked, months page within range, and the grid
     * is not indexed in place of the list.
     */
    public function test_the_calendar_view_lays_the_month_out_as_a_grid_and_a_list(): void
    {
        $month = now()->addMonths(2)->startOfMonth();
        $this->deadline(['title' => 'Grid test deadline', 'due_on' => $month->copy()->addDays(9)->toDateString()]);
        $this->deadline(['title' => 'Month-only grid date', 'due_on' => $month->copy()->addDays(4)->toDateString(), 'date_precision' => 'month']);
        Consultation::create(['source' => 'federal_register', 'external_id' => 'g1', 'kind' => 'comment_period', 'title' => 'Grid test consultation', 'url' => 'https://www.federalregister.gov/x', 'comments_close_on' => $month->copy()->addDays(14), 'status' => 'collected']);
        Consultation::create(['source' => 'federal_register', 'external_id' => 'g2', 'kind' => 'meeting', 'title' => 'Grid test meeting', 'url' => 'https://www.federalregister.gov/y', 'event_on' => $month->copy()->addDays(20), 'status' => 'confirmed']);
        Consultation::create(['source' => 'federal_register', 'external_id' => 'g3', 'kind' => 'meeting', 'title' => 'Hidden grid meeting', 'url' => 'https://www.federalregister.gov/z', 'event_on' => $month->copy()->addDays(21), 'status' => 'hidden']);

        $html = $this->get(route('calendar', ['view' => 'calendar', 'month' => $month->format('Y-m')]))->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex,follow"', $html);
        $this->assertStringContainsString('>'.$month->format('F Y').'</h2>', $html);
        $this->assertMatchesRegularExpression('/aria-current="page"\s+data-track="calendar_view_grid"/', $html);
        $grid = substr($html, strpos($html, 'data-month-grid'), strpos($html, 'data-month-agenda') - strpos($html, 'data-month-grid'));
        foreach (['Grid test deadline', 'Grid test consultation', 'Grid test meeting'] as $title) {
            $this->assertStringContainsString($title, $grid, $title.' is on the grid');
        }
        $this->assertStringNotContainsString('Month-only grid date', $html, 'the grid keeps the exact-day rule');
        $this->assertStringNotContainsString('Hidden grid meeting', $html);
        $agenda = substr($html, strpos($html, 'data-month-agenda'));
        $this->assertMatchesRegularExpression('/Grid test consultation.*not reviewed/s', $agenda);
        $this->assertLessThan(strpos($agenda, 'Grid test consultation'), strpos($agenda, 'Grid test deadline'), 'in date order');

        // Paging is bounded; an out-of-range or malformed month shows this month.
        $this->assertStringContainsString('month='.$month->copy()->addMonth()->format('Y-m'), $html);
        foreach (['2099-01', 'garbage', '2026-13'] as $bad) {
            $this->assertStringContainsString('>'.now()->format('F Y').'</h2>', $this->get(route('calendar', ['view' => 'calendar', 'month' => $bad]))->assertOk()->getContent());
        }

        // The list stays the indexed page, with the switch pointing at the grid.
        $list = $this->get(route('calendar'))->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="robots" content="index', $list);
        $this->assertMatchesRegularExpression('/aria-current="page"\s+data-track="calendar_view_list"/', $list);
        $this->assertStringNotContainsString('data-month-grid', $list);
    }
}
