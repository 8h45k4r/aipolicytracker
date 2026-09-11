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
            $this->jsonLd[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
        }

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

        return $org;
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
