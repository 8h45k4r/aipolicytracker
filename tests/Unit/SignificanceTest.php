<?php

namespace Tests\Unit;

use App\Services\Changes\Significance;
use PHPUnit\Framework\TestCase;

/**
 * The score is a pure function of stated facts, so every weight in the
 * published rule can be checked in isolation and the ranking cannot drift
 * without this file changing with it.
 */
class SignificanceTest extends TestCase
{
    public function test_a_routine_unsourced_entry_with_no_date_scores_the_floor(): void
    {
        $this->assertSame(8, Significance::score([], null));
        $this->assertSame(8, Significance::score(['impact_level' => 'routine'], null));
    }

    public function test_each_fact_adds_its_published_weight(): void
    {
        $this->assertSame(40, Significance::score(['impact_level' => 'urgent'], null));
        $this->assertSame(25, Significance::score(['impact_level' => 'high'], null));
        $this->assertSame(8 + 15, Significance::score(['binding' => true], null));
        $this->assertSame(8 + 12, Significance::score(['status_after' => 'in_force'], null));
        $this->assertSame(8 + 12, Significance::score(['status_after' => 'enforcement_action'], null));
        $this->assertSame(8 + 4, Significance::score(['status_after' => 'under_consultation'], null));
        $this->assertSame(8, Significance::score(['status_after' => 'repealed'], null), 'a status not in the table adds nothing');
        $this->assertSame(8 + 8, Significance::score(['verified' => true], null));
        $this->assertSame(8 + 5, Significance::score(['sourced' => true], null));
        $this->assertSame(8 + 5, Significance::score(['featured' => true], null));
    }

    public function test_recency_rewards_the_week_and_the_month_and_ages_out_after_a_year(): void
    {
        $this->assertSame(8 + 10, Significance::score([], 0));
        $this->assertSame(8 + 10, Significance::score([], 7));
        $this->assertSame(8 + 5, Significance::score([], 8));
        $this->assertSame(8 + 5, Significance::score([], 30));
        $this->assertSame(8, Significance::score([], 31));
        $this->assertSame(8, Significance::score([], 365));
        $this->assertSame(8 - 5, Significance::score([], 366));
    }

    public function test_the_score_is_additive_and_clamped_to_one_hundred(): void
    {
        $everything = ['impact_level' => 'urgent', 'binding' => true, 'status_after' => 'in_force', 'verified' => true, 'sourced' => true, 'featured' => true];
        $this->assertSame(40 + 15 + 12 + 8 + 5 + 5, Significance::score($everything, 100));
        $this->assertSame(95, Significance::score($everything, 1), 'every weight plus the week bonus');
        // The floor and the ceiling hold whatever the facts: an unknown impact
        // level reads as routine, and an entry older than a year loses five.
        $this->assertSame(3, Significance::score(['impact_level' => 'nonsense'], 400));
        foreach ([[[], null], [$everything, 0], [['impact_level' => 'urgent', 'binding' => true], 1000]] as [$facts, $age]) {
            $this->assertGreaterThanOrEqual(0, Significance::score($facts, $age));
            $this->assertLessThanOrEqual(100, Significance::score($facts, $age));
        }
    }

    public function test_an_urgent_binding_entry_into_force_outranks_a_fresh_routine_note(): void
    {
        $story = Significance::score(['impact_level' => 'urgent', 'binding' => true, 'status_after' => 'in_force', 'sourced' => true], 20);
        $note = Significance::score(['impact_level' => 'routine', 'sourced' => true], 1);
        $this->assertGreaterThan($note, $story);
    }
}
