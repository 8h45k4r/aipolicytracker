<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** Findings from the November 2026 check of the live site: what an agent, a crawler and a reader are served. */
class AgentReadabilityTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8';

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_a_record_url_returns_markdown_to_a_client_that_asks_for_it(): void
    {
        $policy = PolicyInstrument::published()->firstOrFail();
        $path = '/policies/'.$policy->slug;

        $response = $this->get($path, ['Accept' => 'text/markdown, text/html;q=0.9, */*;q=0.1']);
        $response->assertOk();
        $this->assertStringStartsWith('text/markdown', (string) $response->headers->get('Content-Type'));
        $this->assertSame($this->get($path.'.md')->getContent(), $response->getContent());
        $this->assertSame($path.'.md', $response->headers->get('Content-Location'));
        $this->assertContains('Accept', array_map('trim', explode(',', implode(',', $response->headers->all('vary')))));
        // The page URL also answers browsers, so a shared cache must not keep the Markdown copy.
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('rel="canonical"', implode(',', $response->headers->all('link')));
    }

    public function test_every_record_kind_negotiates_markdown(): void
    {
        $paths = [
            '/policies/'.PolicyInstrument::published()->firstOrFail()->slug,
            '/jurisdictions/'.Jurisdiction::published()->firstOrFail()->slug,
            '/obligations/'.Obligation::published()->firstOrFail()->slug,
            '/controls/'.Control::published()->firstOrFail()->slug,
            '/changes/'.ChangeEvent::published()->firstOrFail()->slug,
        ];

        foreach ($paths as $path) {
            $response = $this->get($path, ['Accept' => 'text/markdown']);
            $response->assertOk();
            $this->assertStringStartsWith('text/markdown', (string) $response->headers->get('Content-Type'), $path);
            $this->assertStringStartsWith('# ', $response->getContent(), $path);
        }
    }

    public function test_a_browser_and_a_wildcard_still_get_the_page(): void
    {
        $path = '/policies/'.PolicyInstrument::published()->firstOrFail()->slug;

        foreach ([self::BROWSER, '*/*', 'text/html, text/markdown', 'text/markdown;q=0.5, text/html', 'text/markdown;q=0, */*'] as $accept) {
            $response = $this->get($path, ['Accept' => $accept]);
            $response->assertOk();
            $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'), $accept);
            $response->assertSee('<h1', false);
        }
    }

    public function test_a_record_page_names_its_markdown_file_in_a_link_header(): void
    {
        $policy = PolicyInstrument::published()->firstOrFail();

        $response = $this->get('/policies/'.$policy->slug, ['Accept' => self::BROWSER]);
        $response->assertOk();
        $this->assertStringContainsString(
            '<'.route('policies.context', $policy->slug).'>; rel="alternate"; type="text/markdown"',
            implode(',', $response->headers->all('link'))
        );
        $this->assertContains('Accept', array_map('trim', explode(',', implode(',', $response->headers->all('vary')))));
    }

    public function test_markdown_is_not_offered_where_there_is_no_record(): void
    {
        // A path that only looks like a record is answered by its own route, not by a 404 for a missing file.
        $feed = $this->get('/changes/feed', ['Accept' => 'text/markdown']);
        $feed->assertOk();
        $this->assertStringNotContainsString('text/markdown', (string) $feed->headers->get('Content-Type'));

        $this->get('/policies/no-such-record', ['Accept' => 'text/markdown'])->assertNotFound();
        $this->get('/policies', ['Accept' => 'text/markdown'])->assertOk()->assertSee('<h1', false);
    }

    public function test_a_trailing_slash_and_the_front_controller_path_redirect_to_the_one_url(): void
    {
        $cases = [
            '/policies/' => '/policies',
            '/policies/?status=in_force&page=2' => '/policies?status=in_force&page=2',
            '/index.php' => '/',
            '/index.php/policies' => '/policies',
            '/index.php/policies/?q=a' => '/policies?q=a',
        ];

        // The script name is what the web server sends with every request. With it set, the
        // framework treats `/index.php` as a prefix and would add it back to a generated URL.
        $server = ['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => public_path('index.php')];

        foreach ($cases as $from => $to) {
            $response = $this->app->handle(Request::create($from, 'GET', [], [], [], $server));
            $this->assertSame(301, $response->getStatusCode(), $from);
            $this->assertSame('http://localhost'.$to, $response->headers->get('Location'), $from);
        }
    }

    public function test_the_redirect_never_leaves_the_site_and_leaves_other_requests_alone(): void
    {
        // What the web server passes on for a request line of `GET //example.com/`.
        $request = Request::create('/');
        $request->server->set('REQUEST_URI', '//example.com/');
        $response = $this->app->handle($request);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('http://localhost/example.com', $response->headers->get('Location'));

        $this->assertSame(200, $this->app->handle(Request::create('/'))->getStatusCode());
        $this->assertSame(200, $this->app->handle(Request::create('/policies'))->getStatusCode());
        // A form post is never redirected: the body would be lost.
        $this->assertNotSame(301, $this->app->handle(Request::create('/subscribe/', 'POST'))->getStatusCode());
    }

    public function test_the_footer_address_is_marked_so_the_edge_leaves_it_readable(): void
    {
        $address = config('aipolicytracker.contact_email');
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<!--email_off--><a href="mailto:'.preg_quote($address, '#').'"[^>]*>'.preg_quote($address, '#').'</a><!--/email_off-->#',
            $html
        );
    }

    public function test_every_page_names_a_feed(): void
    {
        $changes = '<link rel="alternate" type="application/rss+xml" title="AI policy changes" href="'.route('changes.feed').'">';

        foreach (['/', '/policies', '/about', '/policies/'.PolicyInstrument::published()->firstOrFail()->slug] as $path) {
            $this->get($path)->assertOk()->assertSee($changes, false);
        }

        // A page with a feed of its own keeps it, and names only that one.
        $jurisdiction = Jurisdiction::published()->whereHas('changeEvents')->first();
        if ($jurisdiction !== null) {
            $html = $this->get(route('updates.jurisdiction', $jurisdiction->slug))->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'type="application/rss+xml"'));
            $this->assertStringContainsString(route('updates.jurisdiction.feed', $jurisdiction->slug), $html);
        }
    }
}
