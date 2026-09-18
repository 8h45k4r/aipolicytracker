<?php

namespace App\Support;

/**
 * Per-page metadata passed from controllers to the public layout.
 * Every indexable page must set a unique title, description and canonical URL.
 */
class Seo
{
    public array $jsonLd = [];

    public array $breadcrumbs = [];

    public ?string $ogImage = null;

    public string $ogType = 'website';

    public ?string $feedUrl = null;

    public ?\DateTimeInterface $modified = null;

    /**
     * schema.org type for this page's own node. Controllers that describe a page
     * more precisely (a collection, an about page, an article) set it here instead
     * of hand-writing a node, so every page carries exactly one page entity.
     */
    public string $pageType = 'WebPage';

    /** Extra properties merged into this page's own node, e.g. `about`, `mainEntity`. */
    public array $pageProperties = [];

    private function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public string $robots = 'index,follow',
    ) {}

    public static function make(string $title, string $description, string $canonical, bool $index = true): self
    {
        return new self(
            self::trim($title, 70),
            self::trim($description, 160),
            $canonical,
            $index ? 'index,follow,max-image-preview:large' : 'noindex,follow',
        );
    }

    public function noindex(): self
    {
        $this->robots = 'noindex,follow';

        return $this;
    }

    public function withBreadcrumbs(array $crumbs): self
    {
        $this->breadcrumbs = $crumbs;
        $items = [];
        foreach ($crumbs as $i => [$name, $url]) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
        }
        if ($items !== []) {
            $this->jsonLd[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', '@id' => $this->canonical.'#breadcrumb', 'itemListElement' => $items];
        }

        return $this;
    }

    /**
     * Use the card drawn for this record instead of the site-wide image.
     *
     * The version token is part of the URL on purpose. Platforms cache a preview
     * against its URL and re-fetch only when that changes, so a card whose URL is
     * fixed keeps showing yesterday's title forever. Anything that moves when the
     * record moves works; the record's own update time is the obvious one.
     */
    public function withCard(string $kind, string $slug, ?\DateTimeInterface $version = null): self
    {
        $this->ogImage = route('social.card', ['kind' => $kind, 'slug' => $slug])
            .'?v='.substr(hash('crc32b', ($version?->format('U') ?? '0').$slug), 0, 8);

        return $this;
    }

    /**
     * The preview image for this page: its own card, the site card, or the static
     * file when cards cannot be drawn.
     *
     * The site card is generated too, rather than a file, because the file it
     * replaces had counts painted into it and had been wrong for months without
     * anything being able to notice.
     */
    public function socialImage(): string
    {
        if ($this->ogImage) {
            return $this->ogImage;
        }

        if (! config('social.cards', true)) {
            return url(config('aipolicytracker.default_og_image'));
        }

        return route('social.card', ['kind' => 'site', 'slug' => 'default']).'?v='.self::corpusVersion();
    }

    /**
     * A token that moves when the corpus does, so a platform re-fetches the site
     * card after an import instead of showing last month's counts.
     */
    private static function corpusVersion(): string
    {
        try {
            return \Illuminate\Support\Facades\Cache::remember('seo.corpus-version', 3600, function () {
                $stamp = \App\Models\PolicyInstrument::query()->published()->max('updated_at');

                return substr(hash('crc32b', (string) $stamp), 0, 8);
            });
        } catch (\Throwable) {
            return '0';
        }
    }

    /** Describe the page itself more precisely than the default `WebPage`. */
    public function withPageType(string $type, array $properties = []): self
    {
        $this->pageType = $type;
        $this->pageProperties = array_merge($this->pageProperties, $properties);

        return $this;
    }

    /** Merge properties into this page's own node without changing its type. */
    public function withPageProperties(array $properties): self
    {
        $this->pageProperties = array_merge($this->pageProperties, $properties);

        return $this;
    }

    public function withJsonLd(array $schema): self
    {
        $this->jsonLd[] = ['@context' => 'https://schema.org'] + $schema;

        return $this;
    }

    public function withFeed(string $url): self
    {
        $this->feedUrl = $url;

        return $this;
    }

    public function withModified(?\DateTimeInterface $date): self
    {
        $this->modified = $date;

        return $this;
    }

    public function withOgType(string $type): self
    {
        $this->ogType = $type;

        return $this;
    }

    public function fullTitle(): string
    {
        $site = config('aipolicytracker.site_name');

        return str_ends_with($this->title, $site) ? $this->title : $this->title.' | '.$site;
    }

    /**
     * Page types that already describe the page itself. A page carrying one of
     * these needs no generated node; anything else gets one.
     *
     * FAQPage is deliberately absent. On this site it always sits *beside* a page
     * node rather than replacing it — a policy page with questions is a page that
     * happens to answer questions, not a page made of questions.
     */
    private const PAGE_TYPES = [
        'WebPage', 'CollectionPage', 'AboutPage', 'ContactPage', 'ProfilePage',
        'ItemPage', 'SearchResultsPage', 'CheckoutPage', 'Article', 'NewsArticle', 'BlogPosting',
    ];

    /**
     * Everything this page publishes as JSON-LD, in the order it is rendered.
     *
     * The shared nodes come first and appear on *every* page. They used to be on
     * the homepage alone, which meant every inner page referenced `#organization`
     * and `#website` identifiers that resolved to nothing when that page was
     * fetched on its own — and a record page fetched on its own is exactly how an
     * answer engine reads this site.
     *
     * @return list<array<string,mixed>>
     */
    public function jsonLdBlocks(): array
    {
        $blocks = array_map(fn ($node) => ['@context' => 'https://schema.org'] + $node, self::graph());

        if (! $this->hasOwnPageNode()) {
            $blocks[] = ['@context' => 'https://schema.org'] + $this->pageNode();
        }

        return array_merge($blocks, $this->jsonLd);
    }

    private function hasOwnPageNode(): bool
    {
        foreach ($this->jsonLd as $node) {
            if (in_array($node['@type'] ?? '', self::PAGE_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    /** This page as an entity: what it is, what it belongs to, when it changed. */
    private function pageNode(): array
    {
        return array_filter(array_merge([
            '@type' => $this->pageType,
            '@id' => $this->canonical.'#webpage',
            'url' => $this->canonical,
            'name' => $this->title,
            'description' => $this->description,
            'isPartOf' => ['@id' => url('/').'#website'],
            'breadcrumb' => $this->breadcrumbs !== [] ? ['@id' => $this->canonical.'#breadcrumb'] : null,
            'inLanguage' => 'en',
            'dateModified' => $this->modified?->format(DATE_ATOM),
            'publisher' => ['@id' => url('/').'#organization'],
            'license' => config('aipolicytracker.data_license_url'),
        ], $this->pageProperties), fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * The nodes that are true of every page: who publishes this and what the site is.
     *
     * @return list<array<string,mixed>>
     */
    public static function graph(): array
    {
        return [self::organization(), self::website()];
    }

    /** A list of records as an ItemList, for a page that enumerates them. */
    public static function itemList(iterable $items, callable $name, callable $url, ?string $listName = null): array
    {
        $elements = [];
        foreach ($items as $item) {
            $href = $url($item);
            if (! $href) {
                continue;
            }
            $elements[] = array_filter([
                '@type' => 'ListItem',
                'position' => count($elements) + 1,
                'name' => $name($item),
                'url' => $href,
            ], fn ($v) => $v !== null && $v !== '');
        }

        return array_filter([
            '@type' => 'ItemList',
            'name' => $listName,
            'numberOfItems' => count($elements),
            'itemListElement' => $elements,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public static function organization(): array
    {
        $org = [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => config('aipolicytracker.site_name'),
            'url' => url('/'),
            'logo' => url('/brand/logo-on-light.svg'),
            'parentOrganization' => ['@type' => 'Organization', 'name' => config('aipolicytracker.organization.name'), 'url' => config('aipolicytracker.organization.url')],
            'founder' => collect(config('aipolicytracker.maintainers', []))->map(fn ($m) => ['@type' => 'Person', 'name' => $m['name'], 'url' => $m['url'], 'sameAs' => $m['same_as'] ?? []])->values()->all(),
            'description' => config('aipolicytracker.positioning'),
        ];
        $profiles = array_values(array_unique(array_filter(array_merge([config('aipolicytracker.github_url')], array_column(config('aipolicytracker.social', []), 'url'), config('aipolicytracker.social_profiles', [])))));
        if ($profiles !== []) {
            $org['sameAs'] = $profiles;
        }

        // What the corpus actually covers, read from the corpus. A claim to serve
        // a region is only made where published jurisdictions exist in it, and the
        // subjects come from the taxonomy the records are filed under. Stating a
        // global remit the data does not support would be the same overclaim this
        // project exists to avoid, in machine-readable form.
        $coverage = self::coverage();
        if ($coverage['regions'] !== []) {
            $org['areaServed'] = array_map(fn ($r) => ['@type' => 'Place', 'name' => $r], $coverage['regions']);
        }
        if ($coverage['subjects'] !== []) {
            $org['knowsAbout'] = $coverage['subjects'];
        }

        return $org;
    }

    /**
     * Regions and subjects the published corpus covers.
     *
     * Cached for a day and wrapped, because this now runs on every page render:
     * an Organization node that cannot be enriched is still a correct Organization
     * node, and a database hiccup must never take a page down to decorate one.
     *
     * @return array{regions: list<string>, subjects: list<string>}
     */
    private static function coverage(): array
    {
        // Deliberately no static memo. A static would outlive the request in a
        // worker and outlive the database in a test run, handing every later page
        // an answer computed against data that has since changed. The cache is the
        // memo, and it is scoped to the application instance that owns it.
        try {
            return \Illuminate\Support\Facades\Cache::remember('seo.coverage', 86400, function () {
                $regions = \App\Models\Jurisdiction::query()->published()
                    ->whereNotNull('region')->distinct()->orderBy('region')->pluck('region')
                    ->filter()->values()->all();

                // The named topics the site is about, then whatever the taxonomy
                // adds on top of them, capped so the node stays a description
                // rather than a keyword dump.
                $subjects = ['AI regulation', 'AI governance', 'AI compliance', 'AI policy', 'algorithmic accountability'];
                $terms = \App\Models\TaxonomyTerm::query()->orderBy('name')->pluck('name')
                    ->map(fn ($t) => trim((string) $t))->filter()->values()->all();

                return [
                    'regions' => $regions,
                    'subjects' => array_values(array_slice(array_unique(array_merge($subjects, $terms)), 0, 40)),
                ];
            });
        } catch (\Throwable) {
            // An Organization node that cannot be enriched is still a correct
            // Organization node. A database hiccup must never take a page down to
            // decorate one.
            return ['regions' => [], 'subjects' => []];
        }
    }

    /**
     * A record as an openly licensed Dataset, with the files that actually serve it.
     *
     * This is the strongest thing the site can say to an answer engine: the page
     * is not prose about a law, it is a structured record with a machine-readable
     * form at a stable URL under a licence that permits reuse with attribution.
     *
     * @param  array<string,string>  $distributions  media type => URL
     */
    public static function dataset(string $name, string $description, string $url, array $distributions, ?\DateTimeInterface $modified = null, ?string $identifier = null): array
    {
        return array_filter([
            '@type' => 'Dataset',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'identifier' => $identifier,
            'license' => config('aipolicytracker.data_license_url'),
            'isAccessibleForFree' => true,
            'creator' => ['@id' => url('/').'#organization'],
            'publisher' => ['@id' => url('/').'#organization'],
            'isPartOf' => ['@id' => url('/').'#website'],
            'dateModified' => $modified?->format(DATE_ATOM),
            'distribution' => array_values(array_map(
                fn ($type, $href) => ['@type' => 'DataDownload', 'encodingFormat' => $type, 'contentUrl' => $href],
                array_keys($distributions), $distributions
            )),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * schema.org Legislation for a binding instrument.
     *
     * Binding instruments only. A strategy, a framework or a voluntary standard is
     * not legislation, and this project's whole argument is that the difference
     * matters, so saying otherwise to an answer engine would be the same overclaim
     * in machine-readable form.
     *
     * `legislationLegalForce` is the valuable part: it states in a controlled
     * vocabulary whether the law is actually in force, which is the question
     * readers and answer engines get wrong most often.
     */
    public static function legislation(\App\Models\PolicyInstrument $policy): array
    {
        $force = match ($policy->status) {
            'in_force' => 'https://schema.org/InForce',
            'partially_applicable' => 'https://schema.org/PartiallyInForce',
            'superseded', 'repealed', 'archived' => 'https://schema.org/NotInForce',
            // Proposed, adopted-but-not-applying and consultations are deliberately
            // left unstated rather than asserted as "not in force", which would read
            // as a finding about them rather than an absence of one.
            default => null,
        };

        return array_filter([
            '@type' => 'Legislation',
            'name' => $policy->short_title ?: $policy->title,
            'alternateName' => $policy->short_title ? $policy->title : null,
            'url' => $policy->url(),
            'description' => $policy->summary_plain ? self::trim(preg_replace('/\s+/', ' ', $policy->summary_plain), 300) : null,
            'legislationIdentifier' => $policy->source_reference ?: null,
            'legislationType' => $policy->typeEnum()->label(),
            'legislationJurisdiction' => $policy->jurisdiction ? ['@type' => 'AdministrativeArea', 'name' => $policy->jurisdiction->name] : null,
            'legislationPassedBy' => $policy->issuing_body ? ['@type' => 'Organization', 'name' => $policy->issuing_body] : null,
            'legislationDate' => $policy->adopted_on?->toDateString(),
            'legislationDateOfApplicability' => $policy->applies_from?->toDateString(),
            'legislationLegalForce' => $force,
            'datePublished' => $policy->published_on?->toDateString(),
            'dateModified' => $policy->updated_at?->toIso8601String(),
            'isBasedOn' => $policy->official_source_url ?: null,
            'publisher' => ['@id' => url('/').'#organization'],
            'isPartOf' => ['@id' => url('/').'#website'],
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** schema.org HowTo for a guide that lays out ordered, named steps. */
    public static function howTo(string $name, string $description, string $url, array $steps): array
    {
        return [
            '@type' => 'HowTo',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'step' => collect($steps)->values()->map(fn ($step, $i) => array_filter([
                '@type' => 'HowToStep',
                'position' => $i + 1,
                'name' => is_array($step) ? ($step['title'] ?? null) : null,
                'text' => is_array($step) ? ($step['body'] ?? $step['text'] ?? null) : (string) $step,
            ], fn ($v) => $v !== null && $v !== ''))->all(),
        ];
    }

    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => config('aipolicytracker.site_name'),
            'url' => url('/'),
            'publisher' => ['@id' => url('/').'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('policies.index').'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    private static function trim(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space ? mb_substr($cut, 0, $space) : $cut, ',;:').'…';
    }
}
