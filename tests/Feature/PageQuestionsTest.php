<?php

namespace Tests\Feature;

use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyImporter;
use App\Support\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageQuestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new PolicyImporter(PolicyDataRepository::default()))->run();
    }

    public function test_every_configured_page_shows_its_questions_and_marks_them_up(): void
    {
        foreach (array_keys(config('faq')) as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $items = Faq::for($route);

            $this->assertNotEmpty($items, "No questions resolved for {$route}.");
            $this->assertStringContainsString('Frequently asked questions', $html, "{$route} has questions configured but renders no section.");
            // Exactly one. Two FAQPage blocks is invalid markup, and it is the failure mode
            // when a controller emits its own alongside the one the layout attaches.
            $this->assertSame(
                1,
                substr_count(str_replace(' ', '', $html), '"@type":"FAQPage"'),
                "{$route} must carry exactly one FAQPage block."
            );

            foreach ($items as $item) {
                // Visible text and the schema must carry the same question, or the markup
                // describes something the visitor cannot read.
                $this->assertStringContainsString(e($item['question']), $html, "{$route} is missing the visible question: {$item['question']}");
            }
        }
    }

    public function test_the_licence_token_is_substituted_rather_than_leaking_or_emptying(): void
    {
        $licence = config('aipolicytracker.data_license');
        $this->assertNotEmpty($licence);

        // config/faq.php cannot call config(): under config:cache that resolves to null and the
        // sentence ships with a hole in it. The token is substituted at render time instead.
        $raw = json_encode(config('faq'));
        $this->assertStringContainsString(':license', $raw, 'The token should still be in config; substitution happens on read.');

        foreach (['policies.index', 'open-data'] as $route) {
            $answers = implode(' ', array_column(Faq::for($route), 'answer'));
            $this->assertStringContainsString($licence, $answers, "{$route} lost the licence name.");
            $this->assertStringNotContainsString(':license', $answers, "{$route} leaked the raw token to the reader.");

            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertStringNotContainsString(':license', $html);
            $this->assertStringContainsString(e($licence), $html);
        }
    }

    public function test_a_page_with_its_own_questions_is_not_overwritten(): void
    {
        // Guides, jurisdictions and prepared comparisons already declare their own questions.
        // The layout must not replace them, and must not emit a second FAQPage block.
        $html = $this->get(route('guides.show', 'iso-42001-vs-eu-ai-act'))->assertOk()->getContent();

        $this->assertSame(1, substr_count(str_replace(' ', '', $html), '"@type":"FAQPage"'), 'Two FAQPage blocks on one page is invalid markup.');
    }

    public function test_pages_without_configured_questions_render_no_empty_section(): void
    {
        $this->assertSame([], Faq::for('about'));
        $this->get(route('about'))->assertOk()->assertDontSee('Frequently asked questions');
    }
}
