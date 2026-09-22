<?php

namespace Tests\Feature;

use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new PolicyImporter(PolicyDataRepository::default()))->run();
    }

    /** @return list<array{group: string, label: string, url: string}> */
    private function links(): array
    {
        $links = [];
        foreach (config('navigation.primary') as $key => $group) {
            foreach ($group['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    // Absolute, because that is what route() emits in the component and so
                    // what actually appears in the href.
                    $links[] = ['group' => $key, 'label' => $item['label'], 'url' => route($item['route'], $item['params'] ?? [])];
                }
            }
        }

        return $links;
    }

    public function test_every_navigation_entry_resolves_to_a_route(): void
    {
        $links = $this->links();

        $this->assertGreaterThanOrEqual(40, count($links), 'The navigation should expose the whole site, not a handful of pages.');
        $this->assertSame(count($links), count(array_unique(array_column($links, 'url'))), 'A URL listed twice in the menu splits its own click signal.');
    }

    public function test_every_navigation_entry_actually_answers(): void
    {
        $broken = [];
        foreach ($this->links() as $link) {
            $status = $this->get($link['url'])->getStatusCode();
            if ($status !== 200) {
                $broken[] = "{$link['url']} returned {$status} ({$link['label']})";
            }
        }

        $this->assertSame([], $broken, "A menu entry that does not answer is worse than no entry:\n".implode("\n", $broken));
    }

    public function test_the_header_renders_every_group_and_the_mobile_menu_hides_nothing(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (config('navigation.primary') as $group) {
            $this->assertStringContainsString(e($group['label']), $html, "Group {$group['label']} is missing from the header.");
        }

        // Every link must appear at least twice: once in the desktop nav, once in the mobile
        // menu. A desktop-only link is invisible to most visitors.
        foreach ($this->links() as $link) {
            $this->assertGreaterThanOrEqual(
                2,
                substr_count($html, 'href="'.$link['url'].'"'),
                "{$link['url']} ({$link['label']}) appears fewer than twice, so it is missing from either the desktop nav or the mobile menu."
            );
        }
    }

    public function test_the_current_page_is_marked_in_its_group(): void
    {
        // A parameterised route must match on the resolved URL, not the route name: nine
        // landing pages share one name and would otherwise all claim to be current.
        $here = route('frameworks.crosswalk', ['iso-42001', 'eu']);
        $sibling = route('frameworks.crosswalk', ['nist-ai-rmf', 'eu']);
        $html = $this->get($here)->assertOk()->getContent();

        $marked = fn (string $url) => (bool) preg_match('#href="'.preg_quote($url, '#').'"[^>]*aria-current="page"#', $html);

        $this->assertTrue($marked($here), 'The page being viewed is not marked in the menu.');
        $this->assertFalse($marked($sibling), 'A sibling sharing the route name must not also claim to be current.');
    }

    public function test_the_menu_works_without_javascript(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // <details>/<summary> is the whole mechanism; a nav built on buttons alone would be
        // inert with scripting off.
        $this->assertStringContainsString('<details class="relative" data-nav-group>', $html);
        $this->assertStringContainsString('aria-haspopup="true"', $html);
    }
}
