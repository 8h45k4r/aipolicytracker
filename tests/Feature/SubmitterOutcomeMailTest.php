<?php

namespace Tests\Feature;

use App\Mail\SubmissionOutcomeMail;
use App\Models\ContributorSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The person who sent a submission hears that it arrived and what was decided, never the internal notes. */
class SubmitterOutcomeMailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        config(['aipolicytracker.admin_emails' => ['admin@example.com']]);

        return User::factory()->create(['email' => 'admin@example.com']);
    }

    private function submission(?string $email = 'reader@example.org'): ContributorSubmission
    {
        return ContributorSubmission::create(['type' => 'correction', 'summary' => 'The date on this record is out of date.', 'status' => 'pending_review', 'submitter_email' => $email]);
    }

    public function test_a_submission_with_an_address_is_acknowledged(): void
    {
        Mail::fake();
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret_key' => null]);

        $this->post(route('contribute.store'), ['type' => 'correction', 'summary' => 'The date on this record is out of date.', 'submitter_email' => 'reader@example.org'])->assertSessionHasNoErrors();

        Mail::assertSent(SubmissionOutcomeMail::class, fn ($m) => $m->outcome === 'received' && $m->hasTo('reader@example.org'));
    }

    public function test_an_anonymous_submission_sends_no_mail_to_the_submitter(): void
    {
        Mail::fake();
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret_key' => null]);

        $this->post(route('contribute.store'), ['type' => 'correction', 'summary' => 'The date on this record is out of date.']);

        Mail::assertNotSent(SubmissionOutcomeMail::class);
    }

    public function test_a_decision_is_sent_with_the_public_note_and_never_the_internal_notes(): void
    {
        Mail::fake();
        $submission = $this->submission();

        $this->actingAs($this->admin())->post(route('backend.review.decide', $submission), [
            'decision' => 'rejected', 'notes' => 'internal-only reasoning', 'public_note' => 'The register already shows the newer date.',
        ])->assertRedirect();

        Mail::assertSent(SubmissionOutcomeMail::class, function ($m) {
            $html = $m->render();

            return $m->outcome === 'rejected' && $m->hasTo('reader@example.org')
                && str_contains($html, 'The register already shows the newer date.')
                && ! str_contains($html, 'internal-only reasoning');
        });
    }

    public function test_a_decision_on_an_anonymous_submission_sends_nothing(): void
    {
        Mail::fake();
        $submission = $this->submission(null);

        $this->actingAs($this->admin())->post(route('backend.review.decide', $submission), ['decision' => 'approved'])->assertRedirect();

        Mail::assertNotSent(SubmissionOutcomeMail::class);
    }

    public function test_each_outcome_has_its_own_subject_and_heading(): void
    {
        $s = $this->submission();
        $this->assertSame('Your submission was accepted', (new SubmissionOutcomeMail($s, 'approved'))->envelope()->subject);
        $this->assertSame('Your submission was not accepted', (new SubmissionOutcomeMail($s, 'rejected'))->envelope()->subject);
        $this->assertSame('We need more information about your submission', (new SubmissionOutcomeMail($s, 'needs_information'))->envelope()->subject);
        $this->assertSame('We received your submission', (new SubmissionOutcomeMail($s, 'received'))->envelope()->subject);
    }
}
