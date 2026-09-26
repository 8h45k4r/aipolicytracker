<?php

namespace App\Services\ExternalData;

use App\Models\ExternalIncident;
use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Support\RiskTaxonomy;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * What this site adds to an AI Incident Database record: the policy angle.
 *
 * The database says what happened. This says which harm domain it falls in,
 * which laws address that harm where it happened, and, for the records that
 * concern sexual imagery, that the page is to be kept out of search. All of it
 * is derived from fields already on the record and from this site's own data;
 * none of it is written by hand except the overrides, which live in
 * data/external/incident_overrides.yaml where a reviewer can see and change
 * them, and which always win.
 *
 * The columns are ours, not the database's: the importers never write them,
 * so a weekly re-import cannot undo an override, and apply() fills only what
 * is empty unless asked to refresh.
 */
final class IncidentEnrichment
{
    public const OVERRIDES = 'data/external/incident_overrides.yaml';

    public const SENSITIVE = 'sensitive';

    public const STANDARD = 'standard';

    /** @var array<int,array<string,mixed>>|null */
    private static ?array $overrides = null;

    private static ?string $overridesPath = null;

    /** Point at another overrides file (tests); null restores the default. */
    public static function useOverrides(?string $path): void
    {
        self::$overridesPath = $path;
        self::$overrides = null;
    }

    /** @var array<string,string>|null AIID country value (ISO-2 or name, upper) => jurisdiction slug */
    private static ?array $countryMap = null;

    /** @return int rows written */
    public static function apply(bool $refresh = false): int
    {
        $written = 0;
        $query = ExternalIncident::query()->orderBy('incident_id');
        if (! $refresh) {
            $query->where(fn ($q) => $q->whereNull('sensitivity')->orWhereNull('harm_domain')->orWhereNull('related_policy_slugs'));
        }
        $query->chunkById(300, function ($rows) use (&$written) {
            foreach ($rows as $incident) {
                ExternalIncident::where('incident_id', $incident->incident_id)->update(self::fieldsFor($incident));
                $written++;
            }
        }, 'incident_id');
        // An override can name a record that already had every field filled.
        foreach (self::overrides() as $id => $override) {
            $incident = ExternalIncident::find($id);
            if ($incident) {
                ExternalIncident::where('incident_id', $id)->update(self::fieldsFor($incident));
            }
        }

        return $written;
    }

    /** @return array{harm_domain:?string, sensitivity:string, policy_angle:?string, related_policy_slugs:string} */
    public static function fieldsFor(ExternalIncident $incident): array
    {
        $override = self::overrides()[(int) $incident->incident_id] ?? [];
        $harm = $override['harm_domain'] ?? self::harmDomain($incident);
        $sensitivity = $override['sensitivity'] ?? (IncidentSensitivity::classify($incident) ? self::SENSITIVE : self::STANDARD);
        $related = isset($override['related_policy_slugs']) ? array_values((array) $override['related_policy_slugs']) : self::relatedPolicies($incident)->pluck('slug')->all();
        $angle = $override['policy_angle'] ?? self::policyAngle($incident, $harm, $related);

        return [
            'harm_domain' => $harm,
            'sensitivity' => in_array($sensitivity, [self::SENSITIVE, self::STANDARD], true) ? $sensitivity : self::STANDARD,
            'policy_angle' => $angle,
            'related_policy_slugs' => json_encode($related, JSON_UNESCAPED_SLASHES),
        ];
    }

    /** The MIT domain as a slug, or null when the record is not coded. */
    public static function harmDomain(ExternalIncident $incident): ?string
    {
        $label = trim((string) $incident->mit_domain);
        if ($label === '') {
            return null;
        }
        foreach (app(ExternalDataset::class)->mitRisk()['domains'] ?? [] as $d) {
            if (mb_strtolower($d['aiid_domain_label'] ?? '') === mb_strtolower($label) || mb_strtolower($d['name'] ?? '') === mb_strtolower($label)) {
                return RiskTaxonomy::domainSlug($d['id']);
            }
        }

        return Str::slug(str_replace('&', ' ', $label));
    }

    /**
     * The laws that address this harm where it happened: published instruments
     * in the countries the record names, covering one of the use cases the
     * incident's risk domain concerns, binding first. When the record names no
     * country, or its country has nothing on the use case, the binding
     * instruments covering that use case anywhere, so the page always points
     * somewhere real.
     *
     * @return Collection<int,PolicyInstrument>
     */
    public static function relatedPolicies(ExternalIncident $incident, int $limit = 5): Collection
    {
        $useCases = self::useCasesFor($incident);
        $jurisdictions = self::jurisdictionsFor($incident);
        $base = fn () => PolicyInstrument::published()->with('jurisdiction')->whereNotNull('official_source_url');

        $local = collect();
        if ($jurisdictions !== [] && $useCases !== []) {
            $local = $base()->whereIn('jurisdiction_id', $jurisdictions)->withTerm('use_case', $useCases)->orderByDesc('is_binding')->orderByDesc('featured')->orderBy('title')->limit($limit)->get();
        }
        if ($local->count() >= 3 || $useCases === []) {
            return $local->take($limit)->values();
        }
        $global = $base()->where('is_binding', true)->withTerm('use_case', $useCases)->whereNotIn('id', $local->pluck('id'))->orderByDesc('featured')->orderBy('title')->limit($limit - $local->count())->get();

        return $local->concat($global)->take($limit)->values();
    }

    /**
     * One sentence, from structured fields only, on what policy question the
     * record raises. It names the domain, the country and the count of laws
     * found; it never characterises the incident beyond what the database's
     * own classification says.
     *
     * @param  list<string>  $relatedSlugs
     */
    public static function policyAngle(ExternalIncident $incident, ?string $harm, array $relatedSlugs): ?string
    {
        if ($harm === null) {
            return null;
        }
        $domain = trim((string) $incident->mit_domain);
        $sub = trim((string) $incident->mit_subdomain);
        // The countries named, not their sub-national parts: the sentence says
        // where it happened, and the list below says which rules apply.
        $places = collect($incident->countries ?? [])->map(fn ($c) => self::countryMap()[mb_strtoupper(trim((string) $c))] ?? null)->filter()->unique()
            ->map(fn ($slug) => Jurisdiction::published()->where('slug', $slug)->first()?->nameWithArticle())->filter()->values();
        $n = count($relatedSlugs);
        $where = $places->isNotEmpty() ? ' in '.$places->take(2)->implode(' and ') : '';
        $laws = $n === 0 ? 'no recorded instrument yet addresses this use case'.$where : ($n === 1 ? 'one recorded instrument addresses this use case'.$where : "{$n} recorded instruments address this use case".$where);

        return 'Classified under '.$domain.($sub !== '' ? " ({$sub})" : '').' in the MIT AI Risk Repository taxonomy; '.$laws.'.';
    }

    /** @return list<string> */
    private static function useCasesFor(ExternalIncident $incident): array
    {
        $label = mb_strtolower(trim((string) $incident->mit_domain));
        foreach (app(ExternalDataset::class)->mitRisk()['domains'] ?? [] as $d) {
            if (mb_strtolower($d['aiid_domain_label'] ?? '') === $label || mb_strtolower($d['name'] ?? '') === $label) {
                return array_values($d['use_cases'] ?? []);
            }
        }

        return [];
    }

    /**
     * Jurisdiction ids for the record's countries, with each country's
     * sub-national jurisdictions: a hiring incident in the United States is
     * addressed by Colorado's and New York City's rules as much as by federal
     * guidance.
     *
     * @return list<int>
     */
    private static function jurisdictionsFor(ExternalIncident $incident): array
    {
        $ids = [];
        foreach ($incident->countries ?? [] as $country) {
            $slug = self::countryMap()[mb_strtoupper(trim((string) $country))] ?? null;
            if ($slug && ($id = Jurisdiction::published()->where('slug', $slug)->value('id'))) {
                $ids[] = (int) $id;
                foreach (Jurisdiction::published()->where('parent_jurisdiction_id', $id)->pluck('id') as $child) {
                    $ids[] = (int) $child;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return array<string,string> */
    private static function countryMap(): array
    {
        if (self::$countryMap === null) {
            self::$countryMap = [];
            foreach (Jurisdiction::published()->get(['slug', 'name', 'iso_code']) as $j) {
                if ($j->iso_code) {
                    self::$countryMap[mb_strtoupper($j->iso_code)] = $j->slug;
                }
                self::$countryMap[mb_strtoupper($j->name)] = $j->slug;
            }
            // The database writes a few countries as names in capitals.
            self::$countryMap['UNITED STATES'] = self::$countryMap['US'] ?? 'us';
            self::$countryMap['UNITED KINGDOM'] = self::$countryMap['GB'] ?? 'uk';
        }

        return self::$countryMap;
    }

    /** @return array<int,array<string,mixed>> incident id => override fields */
    public static function overrides(): array
    {
        if (self::$overrides === null) {
            self::$overrides = [];
            $path = self::$overridesPath ?? base_path(self::OVERRIDES);
            $rows = is_file($path) ? ((array) Yaml::parseFile($path))['overrides'] ?? [] : [];
            foreach ($rows as $row) {
                if (isset($row['incident_id'])) {
                    self::$overrides[(int) $row['incident_id']] = $row;
                }
            }
        }

        return self::$overrides;
    }

    /** For tests: forget the cached override file and country map. */
    public static function reset(): void
    {
        self::$overrides = null;
        self::$countryMap = null;
    }
}
