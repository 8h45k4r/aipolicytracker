<?php

namespace Tests\Unit;

use App\Support\MetaDescription;
use App\Support\PageTitle;
use PHPUnit\Framework\TestCase;

/** Meta descriptions fit the budget and end on a complete sentence, never mid-word. */
class MetaDescriptionTest extends TestCase
{
    public function test_a_long_description_is_cut_at_the_last_full_sentence(): void
    {
        $text = 'The EU AI Act is a binding regulation of the European Union. It has been in force since 1 August 2024. '
            .'Its first obligations applied from 2 February 2025, and providers of general-purpose models have further duties.';
        $d = PageTitle::description($text);

        $this->assertLessThanOrEqual(PageTitle::MAX_DESCRIPTION, mb_strlen($d));
        $this->assertSame('The EU AI Act is a binding regulation of the European Union. It has been in force since 1 August 2024.', $d);
    }

    public function test_an_abbreviation_is_not_taken_for_the_end_of_a_sentence(): void
    {
        $text = 'Withdrawal gazetted under Notice No. 3978 of 2026 in the Government Gazette, which the minister announced in April after the consultation closed; '
            .'the draft policy will be replaced by a new one later in the year.';
        $d = PageTitle::description($text);

        $this->assertLessThanOrEqual(PageTitle::MAX_DESCRIPTION, mb_strlen($d));
        $this->assertStringNotContainsString('No.', mb_substr($d, -4));
        $this->assertStringEndsWith('closed.', $d, 'cut at the clause and closed with a full stop');
    }

    public function test_text_with_no_sentence_or_clause_is_cut_at_a_word(): void
    {
        $d = PageTitle::description(str_repeat('regulation ', 30));

        $this->assertLessThanOrEqual(PageTitle::MAX_DESCRIPTION, mb_strlen($d));
        $this->assertStringEndsWith('regulation…', $d);
    }

    public function test_the_first_form_that_fits_is_used_and_every_form_ends_a_sentence(): void
    {
        $long = str_repeat('Very long instrument name ', 8);
        $this->assertSame('Short form.', MetaDescription::first([$long.'with every fact. Official sources linked.', 'Short form.']));

        $d = MetaDescription::first([$long.'. '.$long.'.']);
        $this->assertLessThanOrEqual(PageTitle::MAX_DESCRIPTION, mb_strlen($d));
        $this->assertMatchesRegularExpression('/[.…]$/u', $d);
        $this->assertDoesNotMatchRegularExpression('/\s\S{1,2}…$/u', $d, 'never a stub of a word before the cut');
    }
}
