<?php

namespace Tests\Feature;

use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyImporter;
use App\Support\Navigation;
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

    public function test_each_menu_shows_five_to_seven_entries_and_the_hub_lists_the_rest(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (config('navigation.primary') as $key => $group) {
            $this->assertStringContainsString(e($group['label']), $html, "Group {$group['label']} is missing from the header.");
            $menu = Navigation::menu($group);
            $this->assertGreaterThanOrEqual(min(5, Navigation::items($group)->count()), $menu->count(), "{$key}: too few menu entries");
            $this->assertLessThanOrEqual(7, $menu->count(), "{$key}: a menu longer than seven entries is a wall of links");

            // A menu entry appears in the desktop nav and the mobile menu: a desktop-only link is invisible to most visitors.
            foreach ($menu as $item) {
                $url = Navigation::url($item);
                $this->assertGreaterThanOrEqual(2, substr_count($html, 'href="'.$url.'"'), "{$url} ({$item['label']}) is missing from the desktop nav or the mobile menu.");
            }

            // Everything else is one click away, on the group's hub.
            if (Navigation::hasMore($group)) {
                $this->assertGreaterThanOrEqual(2, substr_count($html, 'href="'.route('explore', $key).'"'), "{$key}: the menus do not link to the hub");
            }
            $hub = $this->get(route('explore', $key))->assertOk()->assertSee('noindex', false)->getContent();
            foreach (Navigation::items($group) as $item) {
                $this->assertStringContainsString('href="'.Navigation::url($item).'"', $hub, "{$item['label']} is not on the {$key} hub");
            }
        }

        $this->get('/explore/nonsense')->assertNotFound();
    }

    public function test_the_current_page_is_marked_in_its_group(): void
    {
        // A parameterised route must match on the resolved URL, not the route name: nine
        // landing pages share one name and would otherwise all claim to be current.
        $here = route('landing', 'eu-ai-act');
        $sibling = route('landing', 'ai-regulation-uk');
        $html = $this->get($here)->assertOk()->getContent();

        $marked = fn (string $url) => (bool) preg_match('#href="'.preg_quote($url, '#').'"[^>]*aria-current="page"#', $html);

        $this->assertTrue($marked($here), 'The page being viewed is not marked in the menu.');
        $this->assertFalse($marked($sibling), 'A sibling sharing the route name must not also claim to be current.');

        // A page listed only on the hub still marks its group as the current one.
        $html = $this->get(route('frameworks.crosswalk', ['iso-42001', 'eu']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#nav-link-active" aria-haspopup="true">\s*Guides#', $html);
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
