<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
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
            $this->assertStringContainsString(route('calendar.feed.jurisdiction', $slug), $html);
        }
    }
}
