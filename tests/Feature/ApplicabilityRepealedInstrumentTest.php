<?php

namespace Tests\Feature;

use App\Models\PolicyInstrument;
use App\Services\Applicability\ApplicabilityScreener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A repealed instrument stays published as a record of what was, but its duties are
 * nobody's to meet. Colorado's SB 24-205 was repealed before it ever applied; the
 * screener must offer the replacement law's duties and not the old ones.
 */
class ApplicabilityRepealedInstrumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_repealed_instruments_duties_are_not_screened_in(): void
    {
        $this->seed();
        $this->artisan('policy:import');
        $this->assertSame('repealed', PolicyInstrument::where('slug', 'us-colorado-ai-act')->value('status'));

        $screener = app(ApplicabilityScreener::class);
        $result = $screener->screen($screener->normalise(['jurisdictions' => ['us-colorado'], 'role' => 'deployer', 'use_case' => 'hiring_and_hr']));

        $policies = $result['policies']->pluck('policy.slug');
        $this->assertContains('us-colorado-automated-decision-making-technology-act', $policies->all());
        $this->assertNotContains('us-colorado-ai-act', $policies->all());
        $obligations = $result['obligations']->pluck('slug');
        $this->assertContains('us-colorado-admt-advance-notice', $obligations->all());
        $this->assertNotContains('us-colorado-deployer-impact-assessment', $obligations->all());
        // The record itself is still there for anyone who links to it.
        $this->get('/policies/us-colorado-ai-act')->assertOk()->assertSee('repealed');
    }
}
