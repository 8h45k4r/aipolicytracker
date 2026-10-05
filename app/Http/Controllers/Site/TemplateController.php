<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\TemplateDownloadMail;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TemplateDownloadRequest;
use App\Models\TemplateVersion;
use App\Rules\NotDisposableEmail;
use App\Rules\WorkEmail;
use App\Services\Records\AnswerBox;
use App\Services\Records\KeyFacts;
use App\Services\Security\Turnstile;
use App\Services\Templates\Records;
use App\Services\Templates\TemplateBuilder;
use App\Services\Templates\TemplateCatalog;
use App\Support\ContentCache;
use App\Support\PageTitle;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The templates library: a hub, one page per template with a preview built
 * from the stored version (never from the definition at request time), the
 * file downloads (requested with a work email; the signed links arrive by mail), and a feed of versions.
 */
class TemplateController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $filters = [
            'type' => array_key_exists((string) $request->query('type'), config('templates.types')) ? $request->query('type') : null,
            'topic' => array_key_exists((string) $request->query('topic'), config('templates.topics')) ? $request->query('topic') : null,
            'framework' => array_key_exists((string) $request->query('framework'), config('templates.frameworks')) ? $request->query('framework') : null,
        ];
        $filtered = array_filter($filters) !== [];
        // One framework or type on its own has a landing page of its own: send the filter
        // there, so search engines and readers meet one URL for "EU AI Act templates".
        $set = array_filter($filters);
        if (count($set) === 1 && ($landing = TemplateCatalog::facet((string) array_key_first($set), (string) reset($set)))) {
            return redirect()->to($landing['url'], 301);
        }
        $latest = TemplateVersion::latestAll();
        $items = TemplateCatalog::filter(TemplateCatalog::all(), $filters)->map(fn ($m) => $m + ['version' => $latest[$m['slug']] ?? null])->values();
        $counts = ContentCache::remember('templates.index.counts', 600, fn () => [
            'duties' => Obligation::published()->count(),
            'instruments' => PolicyInstrument::published()->count(),
            'controls' => Records::controls()->count(),
        ]);
        $lastBuilt = collect($latest)->max('generated_at');
        $all = TemplateCatalog::all();
        $summary = sprintf(
            '%d free AI governance templates in %s, generated from the %s recorded duties, %s controls and %s instruments on this site and rebuilt when the records change. Every row that cites a duty links to its record; each file carries its version, dataset hash, date and licence on a README page. Request any template with your work email and the files arrive by email.',
            $all->count(), 'XLSX and DOCX', number_format($counts['duties']), number_format($counts['controls']), number_format($counts['instruments'])
        );
        $facets = collect(TemplateCatalog::facets());
        $frameworkLinks = $facets->where('facet', 'framework')->map(fn ($f) => $f['label'].' ('.$f['count'].')')->join(', ', ' and ');
        $faq = [
            ['Which template do I need for the EU AI Act?', 'Start with the AI system inventory and the EU AI Act role and risk classifier: they tell you which systems are in scope and in what role. Providers of high-risk systems then need the conformity assessment and QMS workbook and the technical documentation template; deployers the high-risk deployer pack and the FRIA; anyone with a chatbot or generated content the Article 50 transparency kit. Templates by framework: '.$frameworkLinks.'.'],
            ...$this->contentsQuestions($lastBuilt),
            ['Are the templates free?', 'Yes. There is no charge and no account: enter your name, company and work email on the template page and the download links arrive by email, valid for '.TemplateDownloadRequest::LINK_DAYS.' days. The files are licensed '.config('templates.licence')],
            ['Where does the content come from?', 'From the records on this site: obligations, controls, deadlines, framework mappings and the MIT AI Risk Repository taxonomy. Nothing in a file is written by hand; the catalogue says what each template is, and the builder turns the records into sheets and pages.'],
            ['What happens when a law changes?', 'The library is rebuilt daily. A template whose content changed gets the next version number, a changelog on its page, an entry in the updates hub and in the templates feed, and a line in the weekly digest for subscribers who chose the templates topic.'],
            ['Does completing a template make us compliant?', 'No. A template is an informational resource, not legal advice. It helps produce the evidence a regulator, customer or auditor asks for; whether a duty applies, and whether it is met, is a judgement the template cannot make.'],
        ];

        $seo = Seo::make(PageTitle::templatesHub(), sprintf('%d free AI governance templates (XLSX, DOCX) built from %s recorded duties: AI inventory, risk register, FRIA, EU AI Act and ISO 42001 kits.', $all->count(), number_format($counts['duties'])), route('templates.index'), ! $filtered)
            ->withBreadcrumbs([['Home', route('home')], ['Templates', route('templates.index')]])
            ->withModified($lastBuilt)
            ->withFeed(route('templates.feed'))
            ->withPageType('CollectionPage', ['name' => 'AI governance templates', 'mainEntity' => Seo::itemList($items, fn ($m) => $m['title'], fn ($m) => TemplateCatalog::url($m['slug']), 'AI governance templates')])
            ->withFaq(array_map(fn ($q) => ['question' => $q[0], 'answer' => $q[1]], $faq));
        if ($filtered) {
            $seo->noindex();
        }

        return view('site.templates.index', compact('seo', 'items', 'filters', 'filtered', 'summary', 'counts', 'lastBuilt', 'faq', 'facets'));
    }

    /**
     * A landing page for one framework or one type: "EU AI Act templates". Indexable,
     * with its own intro, list and questions, computed from the catalogue so it can
     * never list a template that does not carry the framework.
     */
    public function facet(string $facet, string $key): View
    {
        $landing = TemplateCatalog::facet($facet, $key);
        abort_unless($landing, 404);
        $latest = TemplateVersion::latestAll();
        $items = TemplateCatalog::filter(TemplateCatalog::all(), [$facet => $key])->map(fn ($m) => $m + ['version' => $latest[$m['slug']] ?? null])->values();
        $covered = $items->flatMap(fn ($m) => Records::obligations(TemplateCatalog::obligationFilter($m))->pluck('id'))->unique()->count();
        $types = $items->countBy('type')->map(fn ($n, $t) => $n.' '.Str::plural(mb_strtolower(TemplateCatalog::typeLabel($t)), $n))->values()->join(', ', ' and ');
        $label = $landing['label'];
        $intro = $facet === 'framework'
            ? sprintf('%d free %s templates in XLSX and DOCX (%s), generated from %d recorded duties and rebuilt when those records change. Every row that cites a duty links to the record and its official source.', $items->count(), $label, $types, $covered)
            : sprintf('%d free %s templates in XLSX and DOCX, generated from %d recorded duties across the frameworks on this site and rebuilt when the records change.', $items->count(), mb_strtolower(TemplateCatalog::typeLabel($key)), $covered);
        $faq = [
            ['Are these '.$label.' templates free?', 'Yes. Each template page has a short form: enter your name, company and work email and the download links arrive by email, valid for '.TemplateDownloadRequest::LINK_DAYS.' days. Licensed '.config('templates.licence')],
            ['Where should I start?', 'With '.$items->take(2)->pluck('title')->join(' and ').'. '.($facet === 'framework' ? 'They establish which systems and duties are in scope; the other templates then cover individual duties.' : 'They are the most widely used of this type.')],
            ['Do they make us compliant?', 'No. They are informational resources, not legal advice. They help you produce the evidence a regulator, customer or auditor asks for; whether a duty applies is a judgement they cannot make.'],
        ];
        $title = PageTitle::templateFacet($label, $items->count());
        $seo = Seo::make($title, PageTitle::description($intro), $landing['url'])
            ->withBreadcrumbs([['Home', route('home')], ['Templates', route('templates.index')], [$label.' templates', $landing['url']]])
            ->withModified($items->pluck('version')->filter()->max('generated_at'))
            ->withFeed(route('templates.feed'))
            ->withPageType('CollectionPage', ['name' => $label.' templates', 'description' => $intro, 'mainEntity' => Seo::itemList($items, fn ($m) => $m['title'], fn ($m) => TemplateCatalog::url($m['slug']), $label.' templates')])
            ->withFaq(array_map(fn ($q) => ['question' => $q[0], 'answer' => $q[1]], $faq));
        $others = collect(TemplateCatalog::facets())->reject(fn ($f) => $f['facet'] === $facet && $f['key'] === $key)->values();

        return view('site.templates.facet', compact('seo', 'items', 'landing', 'intro', 'others', 'title'));
    }

    public function show(string $slug): View
    {
        $meta = TemplateCatalog::find($slug);
        abort_unless($meta, 404);
        $version = TemplateVersion::latestFor($slug) ?? app(TemplateBuilder::class)->build($slug)['version'];
        $versions = TemplateVersion::for($slug)->orderByDesc('version')->get();
        $covered = ContentCache::remember("templates.covered.{$slug}.{$version->id}", 600, fn () => Records::obligations(TemplateCatalog::obligationFilter($meta)));
        $basis = $this->legalBasis($meta);
        $caveat = $this->caveat($meta);
        $formats = TemplateCatalog::formatList($meta);
        $related = TemplateCatalog::related($slug);
        $audience = TemplateCatalog::audience($covered);
        $answer = AnswerBox::template($meta, $version, $covered, $audience);
        $facts = KeyFacts::template($meta, $version, $covered, $audience);
        $steps = $this->howToSteps($meta, $version);
        $landing = collect($meta['frameworks'] ?? [])->map(fn ($f) => TemplateCatalog::facet('framework', $f))->filter()->first();
        $refs = $covered->pluck('source_reference')->filter()->unique()->take(6)->values();

        // Questions answered from this template's own data first; the general ones
        // (price, updates, compliance) once each, so no two pages share most of their FAQ.
        $faq = array_values(array_filter([
            ['What is in the '.$meta['title'].'?', implode(' ', array_map(fn ($line) => rtrim($line, '.').'.', $meta['inside']))],
            $covered->isNotEmpty() ? ['Which duties does it cite?', sprintf('%d recorded %s from %s%s. Each row links to the record, and the record to the official source.', $covered->count(), Str::plural('duty', $covered->count()), $covered->map(fn ($o) => $o->policyInstrument->short_title ?: $o->policyInstrument->title)->unique()->take(4)->join(', ', ' and '), $refs->isNotEmpty() ? ', including '.$refs->join(', ', ' and ') : '')] : null,
            $audience ? ['Who is it for?', 'The duties it cites fall on '.Str::lower(collect($audience)->join(', ', ' and ')).'. Whoever owns AI governance for those roles usually completes it, with the system owner supplying the facts.'] : null,
            ['Is it free?', 'Yes. Request the '.$formats.' with your work email on this page; the download links arrive by email, valid for '.TemplateDownloadRequest::LINK_DAYS.' days. No account and no charge. Licensed '.config('templates.licence')],
            ['How will I know when it changes?', sprintf('Version %s was built on %s. The library is rebuilt daily; when a change to the records reaches this template it gets the next version, a changelog below and an entry in the templates feed.', $version->label(), $version->generated_at->format('j F Y'))],
            $caveat ? ['Are the dates in it current?', $caveat] : null,
            ['Does completing it make us compliant?', 'No. It is an informational resource, not legal advice; it helps produce the evidence a regulator, customer or auditor asks for. Whether a duty applies to you is a judgement the template cannot make.'],
        ]));

        $first = $versions->last();
        $url = TemplateCatalog::url($slug);
        $document = array_filter([
            '@type' => 'DigitalDocument',
            '@id' => $url.'#document',
            'name' => $meta['title'],
            'description' => $meta['short'],
            'version' => $version->label(),
            'dateModified' => $version->generated_at->toDateString(),
            'datePublished' => $first?->generated_at->toDateString(),
            'encodingFormat' => array_map(fn ($f) => $this->mime($f['format']), $version->files),
            'isAccessibleForFree' => true,
            // Free, but the files are emailed on request rather than linked: said here
            // so the markup does not promise an open download the page does not offer.
            'conditionsOfAccess' => 'Free. Request the files on this page with a work email address; a download link valid for '.TemplateDownloadRequest::LINK_DAYS.' days is emailed.',
            'offers' => ['@type' => 'Offer', 'price' => 0, 'priceCurrency' => 'USD', 'availability' => 'https://schema.org/InStock', 'url' => $url.'#download'],
            'license' => 'https://creativecommons.org/licenses/by/4.0/',
            'author' => ['@id' => url('/').'#organization'],
            'audience' => $audience ? ['@type' => 'Audience', 'audienceType' => implode(', ', $audience)] : null,
            'keywords' => implode(', ', array_merge([$meta['title'].' template'], array_map(fn ($f) => TemplateCatalog::frameworkLabel($f), $meta['frameworks'] ?? []), array_map(fn ($t) => TemplateCatalog::topicLabel($t), $meta['topics'] ?? []))),
            'associatedMedia' => array_map(fn ($f) => ['@type' => 'MediaObject', 'name' => $f['filename'], 'encodingFormat' => $this->mime($f['format']), 'contentSize' => $f['bytes'].' B'], $version->files),
            'about' => $basis->map(fn ($b) => ['@type' => $b['type'] === 'policy' ? 'Legislation' : 'CreativeWork', 'name' => $b['title'], 'url' => $b['url']])->values()->all() ?: null,
            'isBasedOn' => $basis->where('type', 'policy')->pluck('url')->values()->all() ?: null,
        ]);

        $crumbs = array_values(array_filter([['Home', route('home')], ['Templates', route('templates.index')], $landing ? [$landing['label'].' templates', $landing['url']] : null, [$meta['title'], $url]]));
        // A search snippet of its own: the facts in the order a searcher scans them,
        // then as much of the template's own summary as fits.
        $name = preg_replace('/\s*\([^)]*\)$/', '', $meta['seo_name'] ?? $meta['title']);
        $snippet = sprintf('%s%s: free %s%s. %s',
            $name,
            preg_match('/\b(template|kit|pack)$/i', $name) ? '' : ' template',
            TemplateCatalog::formatList($meta),
            ($version->stats['citations'] ?? 0) > 0 ? ', citing '.$version->stats['citations'].' recorded duties ('.$version->label().')' : ' ('.$version->label().')',
            $meta['short']);
        $seo = Seo::make(PageTitle::template($meta), PageTitle::description($snippet), $url)
            ->withBreadcrumbs($crumbs)
            ->withModified($version->generated_at)
            ->withPublished($first?->generated_at)
            ->withFeed(route('templates.feed'))
            ->withAlternate('application/json', route('api.v1.template', $slug))
            // The page is about one document: an item page whose main entity is the file.
            ->withPageType('ItemPage', ['name' => $meta['title'], 'mainEntity' => $document])
            ->withJsonLd(Seo::howTo('How to use the '.$meta['title'], PageTitle::description($answer), $url, $steps))
            ->withFaq(array_map(fn ($q) => ['question' => $q[0], 'answer' => $q[1]], $faq));

        return view('site.templates.show', compact('seo', 'meta', 'version', 'versions', 'covered', 'basis', 'caveat', 'formats', 'related', 'faq', 'answer', 'facts', 'steps', 'audience'));
    }

    /**
     * How to use it, as steps a reader can follow: the same steps are shown on the page
     * and emitted as HowTo, never one without the other. Built from what the file holds.
     *
     * @return list<array{title:string, body:string}>
     */
    /**
     * The questions people search for about these documents ("what should an AI risk
     * register include?"), answered from the templates themselves: their sections and
     * columns, so an answer changes when a template does. Cached per library build.
     *
     * @return list<array{0:string,1:string}>
     */
    private function contentsQuestions(mixed $lastBuilt): array
    {
        $questions = [
            'acceptable-use-policy' => 'What should an AI acceptable use policy include?',
            'ai-risk-register' => 'What should an AI risk register include?',
            'ai-impact-assessment' => 'What goes into an AI impact assessment?',
            'ai-system-inventory' => 'What should an AI system inventory record?',
        ];

        return ContentCache::remember('templates.contents-faq.'.md5((string) $lastBuilt), now()->addDay(), function () use ($questions) {
            $out = [];
            foreach ($questions as $slug => $question) {
                $definition = TemplateCatalog::definition($slug);
                $meta = TemplateCatalog::find($slug);
                if (! $definition || ! $meta) {
                    continue;
                }
                $sections = collect($definition->blocks())->where('type', 'h1')->pluck('text')
                    ->reject(fn ($t) => str_starts_with($t, 'Duties this') || str_starts_with($t, 'Duties that'))
                    ->map(fn ($t) => $this->lowerFirst(preg_replace('/^\d+\.\s*/', '', $t)))->values();
                $sheets = collect($definition->sheets());
                $columns = collect($sheets->first()['columns'] ?? [])->pluck('label')->take(10)->map(fn ($l) => $this->lowerFirst($l));
                $duties = collect($definition->blocks())->where('type', 'h2')->count();

                $parts = [];
                if ($sections->isNotEmpty()) {
                    $parts[] = 'Our '.$meta['title'].' template has '.$sections->count().' sections: '.$sections->join(', ', ' and ').'.';
                }
                if ($columns->isNotEmpty()) {
                    $parts[] = ($sections->isEmpty() ? 'Our '.$meta['title'].' template records' : 'Its '.$sheets->first()['name'].' sheet records').' '.$columns->join(', ', ' and ').'.';
                }
                if ($duties > 0) {
                    $parts[] = 'It cites the '.$duties.' recorded duties it helps meet, each with its source reference.';
                }
                if ($parts !== []) {
                    $out[] = [$question, implode(' ', $parts).' It is free on '.TemplateCatalog::url($slug).'.'];
                }
            }

            return $out;
        });
    }

    /** "Risk domain (MIT)" becomes "risk domain (MIT)"; "EU AI Act tier" and "ID" stay as written. */
    private function lowerFirst(string $text): string
    {
        return preg_match('/^\p{Lu}\p{Ll}/u', $text) ? mb_strtolower(mb_substr($text, 0, 1)).mb_substr($text, 1) : $text;
    }

    private function howToSteps(array $meta, TemplateVersion $version): array
    {
        $sheets = collect($version->preview['sheets'] ?? []);
        $fill = $sheets->filter(fn ($s) => ($s['editable_rows'] ?? 0) > 0 || ($s['row_count'] ?? 0) === 0)->pluck('name');
        $reference = $sheets->filter(fn ($s) => ($s['row_count'] ?? 0) > 0)->pluck('name');
        $outline = collect($version->preview['outline'] ?? [])->where('level', 1)->pluck('text');

        return array_values(array_filter([
            ['title' => 'Request the files', 'body' => 'Enter your name, company and work email in the form on this page. The '.TemplateCatalog::formatList($meta).' download links arrive by email and work for '.TemplateDownloadRequest::LINK_DAYS.' days.'],
            ['title' => 'Read the README page', 'body' => 'It states the version ('.$version->label().'), the dataset it was built from and the licence, so anyone reviewing your copy knows which records it reflects.'],
            $fill->isNotEmpty() ? ['title' => 'Fill in your rows', 'body' => 'Complete the '.$fill->map(fn ($n) => '"'.$n.'"')->join(', ', ' and ').' '.Str::plural('sheet', $fill->count()).' for your own systems. Dropdowns, formulas and colour rules are already set.'] : null,
            $reference->isNotEmpty() ? ['title' => 'Check the duties against your situation', 'body' => 'The '.$reference->map(fn ($n) => '"'.$n.'"')->join(', ', ' and ').' '.Str::plural('sheet', $reference->count()).' '.($reference->count() === 1 ? 'lists' : 'list').' the recorded duties with their source references. Mark which apply to you and follow each link to the official text.'] : null,
            $outline->isNotEmpty() ? ['title' => 'Complete the document', 'body' => 'Work through the DOCX sections ('.$outline->take(4)->join(', ').') and replace each placeholder with your organisation\'s answer.'] : null,
            ['title' => 'Keep the evidence and watch for new versions', 'body' => 'Link each completed row to the evidence that supports it. When the law on record changes, this template gets a new version and a changelog on this page.'],
        ]));
    }

    /**
     * The request form on a template page. The files go to the address given, as
     * signed links, so the address has to be a working one: a work mailbox (not a
     * consumer one), not a throwaway domain, and with a mail route. Turnstile keeps
     * the form from being scripted.
     */
    public function requestDownload(Request $request, string $slug, Turnstile $turnstile): RedirectResponse
    {
        $meta = TemplateCatalog::find($slug);
        abort_unless($meta, 404);

        // A filled honeypot is a bot. Answer as if it worked, and do nothing.
        if (filled($request->input('website'))) {
            return redirect()->to(route('templates.show', $slug).'#download')->with('template_requested', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', new NotDisposableEmail, new WorkEmail],
            'company' => ['required', 'string', 'min:2', 'max:160'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:80'],
            'terms' => ['accepted'],
            'updates' => ['nullable', 'boolean'],
        ], [
            'terms.accepted' => 'Please accept the terms of use to receive the template.',
        ]);

        if (! $turnstile->passes($request->input('cf-turnstile-response'), $request->ip())) {
            return back()->withInput()->withErrors(['captcha' => 'The security check did not pass. Please try again.'])->withFragment('download');
        }

        $email = strtolower(trim($data['email']));
        $key = 'template-request:'.sha1($email);
        if (RateLimiter::tooManyAttempts($key, (int) config('templates.gate.per_email_per_day', 10))) {
            return back()->withInput()->withErrors(['email' => 'Too many requests for this address today. Please use the links already sent, or try again tomorrow.'])->withFragment('download');
        }
        RateLimiter::hit($key, 86400);

        $version = TemplateVersion::latestFor($slug) ?? app(TemplateBuilder::class)->build($slug)['version'];
        $downloadRequest = TemplateDownloadRequest::create([
            'template_slug' => $slug,
            'name' => trim($data['name']),
            'email' => $email,
            'company' => trim($data['company']),
            'job_title' => $data['job_title'] ?? null,
            'country' => $data['country'] ?? null,
            'terms_accepted_at' => now(),
            'marketing_consent_at' => ! empty($data['updates']) ? now() : null,
            'ip_hash' => hash('sha256', (string) $request->ip().config('app.key')),
            'referrer' => mb_substr((string) $request->headers->get('referer'), 0, 512) ?: null,
        ]);
        Mail::to($email)->send(new TemplateDownloadMail($downloadRequest, $version));
        $downloadRequest->forceFill(['emailed_at' => now()])->save();

        return redirect()->to(route('templates.show', $slug).'#download')->with('template_requested', $email);
    }

    /**
     * Serves a file from a signed, expiring link sent by email. A link without a valid
     * signature goes back to the template page, where the form is.
     */
    public function download(Request $request, string $slug): BinaryFileResponse|RedirectResponse
    {
        $meta = TemplateCatalog::find($slug);
        abort_unless($meta, 404);
        if (! $request->hasValidSignature()) {
            return redirect()->to(route('templates.show', $slug).'#download');
        }
        $downloadRequest = TemplateDownloadRequest::where('id', (int) $request->query('request'))->where('template_slug', $slug)->first();
        abort_unless($downloadRequest, 404);

        $version = TemplateVersion::latestFor($slug) ?? app(TemplateBuilder::class)->build($slug)['version'];
        $format = (string) $request->query('format', $version->formats()[0] ?? 'xlsx');
        $file = $version->file($format);
        abort_unless($file, 404);
        $disk = Storage::disk(TemplateBuilder::DISK);
        if (! $disk->exists($file['path'])) {
            // The row survived but the file did not (a deploy that replaced the disk): rebuild and serve that.
            $version = app(TemplateBuilder::class)->build($slug, true)['version'];
            $file = $version->file($format);
            abort_unless($file && $disk->exists($file['path']), 404);
        }
        TemplateVersion::whereKey($version->id)->increment('downloads');
        $downloadRequest->forceFill(['downloads' => $downloadRequest->downloads + 1, 'first_downloaded_at' => $downloadRequest->first_downloaded_at ?? now()])->save();

        return response()->download($disk->path($file['path']), $file['filename'], [
            'Content-Type' => $this->mime($format),
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** RSS of versions, newest first: what changed and where to get it. */
    public function feed(): Response
    {
        $versions = TemplateVersion::orderByDesc('generated_at')->orderByDesc('id')->limit(50)->get();

        return response(view('site.templates.feed', compact('versions'))->render(), 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    /** The instruments and frameworks a template rests on, as links. */
    private function legalBasis(array $meta): Collection
    {
        $slugs = $meta['legal_basis'] ?? [];
        $policies = PolicyInstrument::published()->with('jurisdiction')->whereIn('slug', $slugs)->get()->keyBy('slug');
        $out = collect();
        foreach ($slugs as $s) {
            if ($p = $policies->get($s)) {
                $out->push(['type' => 'policy', 'title' => $p->short_title ?: $p->title, 'meta' => $p->jurisdiction->name.' · '.$p->statusEnum()->label(), 'url' => $p->url()]);
            } elseif ($key = Records::frameworkKey($s) and config("frameworks.{$key}")) {
                $out->push(['type' => 'framework', 'title' => config("frameworks.{$key}.name", $s), 'meta' => 'Framework crosswalk', 'url' => route('frameworks.show', config("frameworks.{$key}.slug", $s))]);
            }
        }

        return $out;
    }

    /** The record's own note on its dates, when the catalogue flags the template as date-sensitive. */
    private function caveat(array $meta): ?string
    {
        if (($meta['caveat'] ?? null) !== 'dates') {
            return null;
        }
        $notes = [];
        foreach ($meta['legal_basis'] ?? [] as $s) {
            $p = Records::policy($s);
            $text = trim((string) ($p?->status_note ?: $p?->date_notes));
            if ($text !== '') {
                $notes[] = ($p->short_title ?: $p->title).': '.$text;
            }
        }

        return $notes === []
            ? 'Dates are copied from the records as they stood on the build date; each dated row links to the record, which states when it was last checked.'
            : 'Dates are as recorded on the build date. '.implode(' ', $notes);
    }

    private function mime(string $format): string
    {
        return match ($format) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }
}
