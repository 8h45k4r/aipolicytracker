<?php

namespace Tests\Feature;

use App\Models\TransitionMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every record type whose page offers "Report a correction" must open the form
 * with the record named, and the submission must keep that subject.
 */
class CorrectionSubjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_a_transition_measure_can_be_corrected(): void
    {
        $measure = TransitionMeasure::whereNotNull('published_at')->firstOrFail();

        $this->get($measure->url())->assertOk()->assertSee('subject_type=transition_measure', false);
        $this->get(route('contribute', ['type' => 'correction', 'subject_type' => 'transition_measure', 'subject_slug' => $measure->slug]))
            ->assertOk()->assertSee($measure->title);

        $this->post('/contribute', ['type' => 'correction', 'subject_type' => 'transition_measure', 'subject_slug' => $measure->slug, 'summary' => 'The status should be checked against the bill text.'])
            ->assertRedirect(route('contribute'));
        $this->assertDatabaseHas('contributor_submissions', ['subject_type' => 'transition_measure', 'subject_slug' => $measure->slug, 'status' => 'pending_review']);
    }
}
