<?php

namespace Tests\Feature;

use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObligationVerificationInheritanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    public function test_obligation_without_own_verification_does_not_inherit_verified(): void
    {
        $verified = PolicyInstrument::published()
            ->where('review_status', 'verified')
            ->whereNotNull('last_verified_at')
            ->has('obligations')
            ->firstOrFail();

        $obligation = $verified->obligations()
            ->whereNull('last_verified_at')
            ->first();

        if (! $obligation) {
            $this->markTestSkipped('No obligation without its own last_verified_at under a verified instrument.');
        }

        $this->assertNotEquals(
            'verified',
            $obligation->review_status,
            "Obligation {$obligation->aipt_id} has no last_verified_at and must not inherit 'verified' from its instrument."
        );
    }

    public function test_obligation_with_own_verification_keeps_verified(): void
    {
        $obligation = Obligation::whereNotNull('last_verified_at')
            ->where('review_status', 'verified')
            ->first();

        if (! $obligation) {
            $this->markTestSkipped('No obligation with its own last_verified_at and verified status.');
        }

        $this->assertNotNull($obligation->last_verified_at);
        $this->assertSame('verified', $obligation->review_status);
    }

    public function test_no_obligation_is_verified_without_last_verified_at(): void
    {
        $orphan = Obligation::where('review_status', 'verified')
            ->whereNull('last_verified_at')
            ->first();

        $this->assertNull(
            $orphan,
            $orphan
                ? "Obligation {$orphan->aipt_id} is verified but has no last_verified_at."
                : ''
        );
    }
}
