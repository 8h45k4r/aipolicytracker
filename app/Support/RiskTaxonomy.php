<?php

namespace App\Support;

use App\Services\ExternalData\ExternalDataset;
use Illuminate\Support\Str;

/**
 * Addresses for the MIT AI Risk Repository's seven domains and their
 * subdomains.
 *
 * They were published at the taxonomy's own numbers ("/ai-risk/3",
 * "/ai-risk/1/1.2"). The numbers are how the repository orders its domains, not
 * how anyone searches for them, so the pages now live at the names
 * ("/ai-risk/misinformation", "/ai-risk/discrimination-toxicity/unfair-
 * discrimination-and-misrepresentation") and the numbered addresses redirect.
 * The number is still shown on the page, where it helps a reader place the
 * domain in the repository.
 */
final class RiskTaxonomy
{
    /** @var array{domains: array<string,string>, subdomains: array<string,string>}|null id => slug */
    private static ?array $map = null;

    public static function domainSlug(string|int $id): ?string
    {
        return self::map()['domains'][(string) $id] ?? null;
    }

    public static function subdomainSlug(string $code): ?string
    {
        return self::map()['subdomains'][$code] ?? null;
    }

    /** Domain id for a slug or an id; null when neither is known. */
    public static function domainId(string $key): ?string
    {
        $domains = self::map()['domains'];

        return isset($domains[$key]) ? $key : (array_search($key, $domains, true) ?: null);
    }

    /** Subdomain code ("1.2") for a slug or a code, within a domain. */
    public static function subdomainCode(string $domainId, string $key): ?string
    {
        foreach (self::map()['subdomains'] as $code => $slug) {
            if (str_starts_with($code, $domainId.'.') && ($code === $key || $slug === $key)) {
                return $code;
            }
        }

        return null;
    }

    public static function domainUrl(string|int|null $id): string
    {
        return route('risk.domain', self::domainSlug((string) $id) ?? (string) $id);
    }

    public static function subdomainUrl(string|int $domainId, string $code): string
    {
        return route('risk.subdomain', [self::domainSlug((string) $domainId) ?? (string) $domainId, self::subdomainSlug($code) ?? $code]);
    }

    /** Route constraint: a known domain slug, or a legacy number (which redirects). */
    public static function domainPattern(): string
    {
        return implode('|', array_map('preg_quote', array_values(self::map()['domains']))).'|[1-7]';
    }

    public static function subdomainPattern(): string
    {
        return '[1-7]\.[0-9]{1,2}|[a-z][a-z0-9-]*';
    }

    /** @return array{domains: array<string,string>, subdomains: array<string,string>} */
    private static function map(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }
        $domains = $subdomains = [];
        foreach (app(ExternalDataset::class)->mitRisk()['domains'] ?? [] as $d) {
            $domains[(string) $d['id']] = Str::slug(str_replace('&', ' ', (string) $d['name']));
            foreach ($d['subdomains'] ?? [] as $s) {
                $subdomains[(string) $s['id']] = Str::slug(str_replace('&', ' ', (string) $s['name']));
            }
        }

        return self::$map = ['domains' => $domains, 'subdomains' => $subdomains];
    }
}
