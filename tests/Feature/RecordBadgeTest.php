<?php

namespace Tests\Feature;

use App\Models\PolicyInstrument;
use App\Services\PolicyData\DeadlineCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** /badges/{policy}.svg, the snippet on the record page, and the calendar subscribe links. */
class RecordBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    public function test_the_badge_states_the_recorded_status_and_review_state(): void
    {
        $verified = PolicyInstrument::published()->where('review_status', 'verified')->whereNotNull('last_verified_at')->firstOrFail();
        $response = $this->get('/badges/'.$verified->slug.'.svg')->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8')
            ->assertHeader('X-Robots-Tag', 'noindex');
        $response->assertCookieMissing(config('session.cookie'));
        $svg = $response->getContent();
        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertNotFalse(simplexml_load_string($svg), 'the badge is well-formed XML');
        $this->assertStringContainsString($verified->statusEnum()->label().' · verified '.$verified->last_verified_at->format('j M Y'), $svg);
        $this->assertStringNotContainsString('<script', $svg);

        $pending = PolicyInstrument::published()->where('review_status', 'pending_review')->first();
        if ($pending) {
            $this->assertStringContainsString('· pending review', $this->get('/badges/'.$pending->slug.'.svg')->assertOk()->getContent());
        }
    }

    public function test_an_unpublished_or_unknown_record_has_no_badge(): void
    {
        $this->get('/badges/not-a-record.svg')->assertNotFound();
        $hidden = PolicyInstrument::published()->firstOrFail();
        $hidden->update(['published_at' => null]);
        $this->get('/badges/'.$hidden->slug.'.svg')->assertNotFound();
    }

    public function test_a_title_with_markup_characters_is_escaped(): void
    {
        $policy = PolicyInstrument::published()->firstOrFail();
        $policy->update(['title' => 'Rules <for> "AI" & data']);
        $svg = $this->get('/badges/'.$policy->slug.'.svg')->assertOk()->getContent();
        $this->assertNotFalse(simplexml_load_string($svg));
        $this->assertStringContainsString('Rules &lt;for&gt; &quot;AI&quot; &amp; data', $svg);
    }

    public function test_the_record_page_offers_the_badge_linked_to_the_permanent_address(): void
    {
        $policy = PolicyInstrument::published()->whereNotNull('aipt_id')->firstOrFail();
        $html = $this->get(route('policies.show', $policy->slug))->assertOk()->getContent();
        $this->assertStringContainsString('Add a status badge', $html);
        $this->assertStringContainsString('src="'.route('badges.policy', $policy->slug).'"', $html);
        $this->assertStringContainsString('('.route('record.id', $policy->aipt_id).')</code>', $html);
        $this->get(route('embed.index'))->assertOk()->assertSee('Status badges')->assertSee(route('badges.policy', 'eu-ai-act'), false);
    }

    public function test_calendar_pages_offer_a_webcal_subscription(): void
    {
        $html = $this->get(route('calendar'))->assertOk()->getContent();
        $this->assertStringStartsWith('webcal://', DeadlineCalendar::subscribeUrl(route('calendar.feed')));
        $this->assertStringContainsString('href="'.DeadlineCalendar::subscribeUrl(route('calendar.feed')).'"', $html);
        $this->assertStringContainsString('href="'.route('calendar.feed').'"', $html, 'the one-off download stays');
    }
}
