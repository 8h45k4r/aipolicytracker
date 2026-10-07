<?php

namespace Tests\Unit;

use App\Services\Implementation\ImplementationStatus;
use PHPUnit\Framework\TestCase;

/**
 * "Overdue" is derived, never declared: the due date has passed and the measure
 * has been neither adopted nor published. A pure function of its inputs, so each
 * rule is checked on its own.
 */
class ImplementationStatusTest extends TestCase
{
    private const TODAY = '2026-10-07';

    public function test_a_planned_measure_past_its_due_date_is_overdue(): void
    {
        $this->assertSame('overdue', ImplementationStatus::derive('planned', '2026-02-02', null, null, self::TODAY));
        $this->assertSame('overdue', ImplementationStatus::derive('consultation', '2026-10-06', null, null, self::TODAY));
        $this->assertSame('overdue', ImplementationStatus::derive('draft', '2025-08-02', null, null, self::TODAY));
    }

    public function test_the_due_date_itself_is_not_yet_overdue(): void
    {
        $this->assertSame('planned', ImplementationStatus::derive('planned', self::TODAY, null, null, self::TODAY));
        $this->assertSame('consultation', ImplementationStatus::derive('consultation', '2027-01-01', null, null, self::TODAY));
    }

    public function test_an_adopted_or_published_measure_is_never_overdue(): void
    {
        $this->assertSame('adopted', ImplementationStatus::derive('adopted', '2026-02-02', '2026-05-01', null, self::TODAY));
        $this->assertSame('published', ImplementationStatus::derive('published', '2026-02-02', null, '2026-06-10', self::TODAY));
        // A date recorded against a lagging declared status still means it exists.
        $this->assertSame('draft', ImplementationStatus::derive('draft', '2026-02-02', '2026-05-01', null, self::TODAY));
        $this->assertSame('planned', ImplementationStatus::derive('planned', '2026-02-02', null, '2026-03-01', self::TODAY));
    }

    public function test_without_a_due_date_nothing_is_overdue(): void
    {
        $this->assertSame('planned', ImplementationStatus::derive('planned', null, null, null, self::TODAY));
    }

    public function test_an_unverified_draft_is_never_called_overdue(): void
    {
        $this->assertSame('unverified', ImplementationStatus::derive('unverified', '2020-01-01', null, null, self::TODAY));
    }

    public function test_labels_cover_every_derived_value(): void
    {
        foreach (['unverified', 'planned', 'consultation', 'draft', 'adopted', 'published', 'overdue'] as $status) {
            $this->assertArrayHasKey($status, ImplementationStatus::LABELS);
        }
        $this->assertArrayNotHasKey('overdue', ImplementationStatus::DECLARED, 'overdue cannot be declared in a record');
    }
}
