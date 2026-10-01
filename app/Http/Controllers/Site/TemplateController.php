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
use App\Services\Security\Turnstile;
use App\Services\Templates\Records;
use App\Services\Templates\TemplateBuilder;
use App\Services\Templates\TemplateCatalog;
use App\Support\PageTitle;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The templates library: a hub, one page per template with a preview built
 * from the stored version (never from the definition at request time), the
 * file downloads (requested with a work email; the signed links arrive by mail), and a feed of versions.
 */
class TemplateController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'type' => array_key_exists((string) $request->query('type'), config('templates.types')) ? $request->query('type') : null,
            'topic' => array_key_exists((string) $request->query('topic'), config('templates.topics')) ? $request->query('topic') : null,
            'framework' => array_key_exists((string) $request->query('framework'), config('templates.frameworks')) ? $request->query('framework') : null,
        ];
        $filtered = array_filter($filters) !== [];
        $latest = TemplateVersion::latestAll();
        $items = TemplateCatalog::filter(TemplateCatalog::all(), $filters)->map(fn ($m) => $m + ['version' => $latest[$m['slug']] ?? null])->values();
        $counts = Cache::remember('templates.index.counts', 600, fn () => [
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
        $faq = [
            ['Are the templates free?', 'Yes. There is no charge and no account: enter your name, company and work email on the template page and the download links arrive by email, valid for '.TemplateDownloadRequest::LINK_DAYS.' days. The files are licensed '.config('templates.licence')],
            ['Where does the content come from?', 'From the records on this site: obligations, controls, deadlines, framework mappings and the MIT AI Risk Repository taxonomy. Nothing in a file is written by hand; the catalogue says what each template is, and the builder turns the records into sheets and pages.'],
            ['What happens when a law changes?', 'The library is rebuilt daily. A template whose content changed gets the next version number, a changelog on its page, an entry in the updates hub and in the templates feed, and a line in the weekly digest for subscribers who chose the templates topic.'],
            ['Does completing a template make us compliant?', 'No. A template is an informational resource, not legal advice. It helps produce the evidence a regulator, customer or auditor asks for; whether a duty applies, and whether it is met, is a judgement the template cannot make.'],
        ];

        $seo = Seo::make(PageTitle::templatesHub(), 'Free AI governance templates (XLSX, DOCX) generated from recorded law: inventory, risk register, FRIA, policies, incident playbook, EU AI Act and ISO 42001 kits. No account needed.', route('templates.index'), ! $filtered)
            ->withBreadcrumbs([['Home', route('home')], ['Templates', route('templates.index')]])
            ->withModified($lastBuilt)
            ->withFeed(route('templates.feed'))
            ->withPageType('CollectionPage', ['name' => 'AI governance templates', 'mainEntity' => Seo::itemList($items, fn ($m) => $m['title'], fn ($m) => TemplateCatalog::url($m['slug']), 'AI governance templates')])
            ->withFaq(array_map(fn ($q) => ['question' => $q[0], 'answer' => $q[1]], $faq));
        if ($filtered) {
            $seo->noindex();
        }

        return view('site.templates.index', compact('seo', 'items', 'filters', 'filtered', 'summary', 'counts', 'lastBuilt', 'faq'));
    }

    public function show(string $slug): View
    {
        $meta = TemplateCatalog::find($slug);
        abort_unless($meta, 404);
        $version = TemplateVersion::latestFor($slug) ?? app(TemplateBuilder::class)->build($slug)['version'];
        $versions = TemplateVersion::for($slug)->orderByDesc('version')->get();
        $covered = Cache::remember("templates.covered.{$slug}.{$version->id}", 600, fn () => Records::obligations(TemplateCatalog::obligationFilter($meta)));
        $basis = $this->legalBasis($meta);
        $caveat = $this->caveat($meta);
        $formats = TemplateCatalog::formatList($meta);
        $related = TemplateCatalog::all()->except($slug)->filter(fn ($m) => array_intersect($m['topics'] ?? [], $meta['topics'] ?? []) !== [])->take(4);

        $faq = array_values(array_filter([
            ['Is the '.$meta['title'].' free?', 'Yes. Request the '.$formats.' with your work email on this page; the download links arrive by email, valid for '.TemplateDownloadRequest::LINK_DAYS.' days. No account and no charge. Licensed '.config('templates.licence')],
            ['What is it generated from?', sprintf('Version %s was built on %s from dataset %s: %s recorded duties are cited in it%s. Every row that cites a duty links to the record, and the record links to the official source.', $version->label(), $version->generated_at->format('j F Y'), $version->dataset_version, number_format($version->stats['citations'] ?? 0), $covered->isNotEmpty() ? ', drawn from '.$covered->pluck('policyInstrument.short_title')->unique()->count().' instruments' : '')],
            ['How will I know when it changes?', 'The library is rebuilt daily. When a change to the records reaches this template it gets the next version, a changelog in the version history below, an entry in the AI policy updates hub and the templates feed, and a line in the weekly digest for subscribers of the templates topic.'],
            $caveat ? ['Are the dates in it current?', $caveat] : null,
            ['Does completing it make us compliant?', 'No. It is an informational resource, not legal advice; it helps produce the evidence a regulator, customer or auditor asks for. Whether a duty applies to you is a judgement the template cannot make.'],
        ]));

        $first = $versions->last();
        $seo = Seo::make(PageTitle::template($meta), $meta['short'], TemplateCatalog::url($slug))
            ->withBreadcrumbs([['Home', route('home')], ['Templates', route('templates.index')], [$meta['title'], TemplateCatalog::url($slug)]])
            ->withModified($version->generated_at)
            ->withPublished($first?->generated_at)
            ->withFeed(route('templates.feed'))
            ->withPageType('DigitalDocument', array_filter([
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
                'offers' => ['@type' => 'Offer', 'price' => 0, 'priceCurrency' => 'USD', 'availability' => 'https://schema.org/InStock', 'url' => TemplateCatalog::url($slug).'#download'],
                'license' => 'https://creativecommons.org/licenses/by/4.0/',
                'author' => ['@id' => url('/').'#organization'],
                'associatedMedia' => array_map(fn ($f) => ['@type' => 'MediaObject', 'name' => $f['filename'], 'encodingFormat' => $this->mime($f['format']), 'contentSize' => $f['bytes'].' B'], $version->files),
                'about' => $basis->map(fn ($b) => ['@type' => $b['type'] === 'policy' ? 'Legislation' : 'CreativeWork', 'name' => $b['title'], 'url' => $b['url']])->values()->all() ?: null,
            ]))
            ->withFaq(array_map(fn ($q) => ['question' => $q[0], 'answer' => $q[1]], $faq));

        return view('site.templates.show', compact('seo', 'meta', 'version', 'versions', 'covered', 'basis', 'caveat', 'formats', 'related', 'faq'));
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
