<?php

namespace Tests\Unit;

use App\Services\Verification\AgreementStatistics;
use PHPUnit\Framework\TestCase;

/**
 * Cohen's kappa checked against examples worked by hand, so the figure published on
 * /methodology cannot drift without this file changing with it.
 */
class AgreementStatisticsTest extends TestCase
{
    /** @return list<array{0: mixed, 1: mixed}> */
    private function pairs(array $counts): array
    {
        $pairs = [];
        foreach ($counts as [$a, $b, $n]) {
            for ($i = 0; $i < $n; $i++) {
                $pairs[] = [$a, $b];
            }
        }

        return $pairs;
    }

    public function test_the_textbook_two_by_two_example(): void
    {
        // 50 items. Both yes 20, A yes/B no 5, A no/B yes 10, both no 15.
        // p_o = 35/50 = 0.70. A says yes 25/50, B says yes 30/50.
        // p_e = 0.5 x 0.6 + 0.5 x 0.4 = 0.50. kappa = (0.70 - 0.50) / 0.50 = 0.40.
        $pairs = $this->pairs([['yes', 'yes', 20], ['yes', 'no', 5], ['no', 'yes', 10], ['no', 'no', 15]]);

        $this->assertEqualsWithDelta(0.4, AgreementStatistics::cohensKappa($pairs), 1e-9);
        $this->assertEqualsWithDelta(0.7, AgreementStatistics::percentAgreement($pairs), 1e-9);
    }

    public function test_three_categories(): void
    {
        // 10 items: (a,a)x3 (b,b)x2 (c,c)x1 (a,b)x1 (b,c)x1 (c,a)x2.
        // p_o = 6/10. First: a4 b3 c3. Second: a5 b3 c2.
        // p_e = (4x5 + 3x3 + 3x2) / 100 = 0.35. kappa = 0.25 / 0.65 = 0.384615...
        $pairs = $this->pairs([['a', 'a', 3], ['b', 'b', 2], ['c', 'c', 1], ['a', 'b', 1], ['b', 'c', 1], ['c', 'a', 2]]);

        $this->assertEqualsWithDelta(5 / 13, AgreementStatistics::cohensKappa($pairs), 1e-9);
    }

    public function test_perfect_agreement_is_one_and_systematic_disagreement_is_minus_one(): void
    {
        $this->assertEqualsWithDelta(1.0, AgreementStatistics::cohensKappa($this->pairs([[true, true, 7], [false, false, 3]])), 1e-9);
        // Every item swapped: p_o = 0, p_e = 0.5, kappa = -1.
        $this->assertEqualsWithDelta(-1.0, AgreementStatistics::cohensKappa($this->pairs([[true, false, 5], [false, true, 5]])), 1e-9);
    }

    public function test_kappa_is_undefined_when_both_reviewers_used_one_category_and_with_no_items(): void
    {
        // p_e = 1: chance alone explains the agreement, so kappa says nothing.
        $this->assertNull(AgreementStatistics::cohensKappa($this->pairs([['verified', 'verified', 25]])));
        $this->assertNull(AgreementStatistics::cohensKappa([]));
        $this->assertNull(AgreementStatistics::percentAgreement([]));
    }

    public function test_booleans_and_strings_are_different_categories(): void
    {
        // true and "1" must not be merged into one category.
        $this->assertEqualsWithDelta(0.0, AgreementStatistics::percentAgreement([[true, '1'], [false, '0']]), 1e-9);
    }

    private function review(array $coded, array $disputes = []): array
    {
        return ['reviewed_by' => 'B', 'reviewed_on' => '2026-10-01', 'coded' => $coded, 'agreed' => $disputes === [], 'fields_disputed' => $disputes];
    }

    public function test_below_the_minimum_sample_no_field_figures_are_reported(): void
    {
        $reviews = array_fill(0, 19, $this->review(['status' => 'in_force', 'is_binding' => true, 'review_status' => 'verified']));
        $result = (new AgreementStatistics(20))->compute($reviews);

        $this->assertSame(19, $result['n']);
        $this->assertFalse($result['sufficient']);
        $this->assertNull($result['fields']);
        $this->assertSame(19, $result['records_fully_agreed']);
    }

    public function test_field_figures_are_built_from_coded_values_and_disputes(): void
    {
        // 20 records. Status: 10 in force and 10 adopted per the second reviewer; on 2 of
        // the "adopted" ones the first reviewer had "in_force". Binding: the second
        // reviewer said true on all, the first false on 1. Dates disputed on 4 records.
        $reviews = [];
        for ($i = 0; $i < 20; $i++) {
            $status = $i < 10 ? 'in_force' : 'adopted';
            $disputes = [];
            if ($i === 10 || $i === 11) {
                $disputes[] = ['field' => 'status', 'first' => 'in_force', 'resolution' => 'Adopted; application date not reached.'];
            }
            if ($i === 0) {
                $disputes[] = ['field' => 'is_binding', 'first' => false];
            }
            if ($i >= 16) {
                $disputes[] = ['field' => 'dates', 'note' => 'Application date'];
            }
            $reviews[] = $this->review(['status' => $status, 'is_binding' => true, 'review_status' => 'verified'], $disputes);
        }
        $result = (new AgreementStatistics(20))->compute($reviews);

        $this->assertTrue($result['sufficient']);
        $this->assertSame(20, $result['n']);
        // Records 0, 10, 11, 16-19 had at least one dispute: 7. 13 agreed on everything.
        $this->assertSame(13, $result['records_fully_agreed']);

        // Status: p_o = 18/20 = 0.9. First: in_force 12, adopted 8. Second: 10 / 10.
        // p_e = 0.6 x 0.5 + 0.4 x 0.5 = 0.5. kappa = 0.4 / 0.5 = 0.8.
        $this->assertSame(90.0, $result['fields']['status']['percent']);
        $this->assertSame(0.8, $result['fields']['status']['kappa']);

        // Binding: p_o = 19/20. First: true 19, false 1. Second: true 20.
        // p_e = 0.95 x 1 = 0.95. kappa = (0.95 - 0.95) / 0.05 = 0.
        $this->assertSame(95.0, $result['fields']['is_binding']['percent']);
        $this->assertSame(0.0, $result['fields']['is_binding']['kappa']);

        // Review status: both reviewers always "verified": undefined, not 1.
        $this->assertSame(100.0, $result['fields']['review_status']['percent']);
        $this->assertNull($result['fields']['review_status']['kappa']);

        // Dates: not categorical, so percent agreement only.
        $this->assertSame(80.0, $result['fields']['dates']['percent']);
        $this->assertNull($result['fields']['dates']['kappa']);
        $this->assertFalse($result['fields']['dates']['categorical']);
    }
}
