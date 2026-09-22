<?php

namespace App\Services\PolicyData;

use App\Models\Control;
use App\Models\ControlFrameworkReference;
use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Services\ExternalData\ExternalDataset;
use Illuminate\Support\Collection;

/**
 * The joins that turn a control catalogue into an intelligence index: which
 * risks a control addresses and how often they have been recorded as
 * incidents, and how many controls, evidence types and risk areas sit behind
 * each framework. Every number is a count over published records, so a page
 * can state its own denominator.
 */
class ControlIntelligence
{
    public function __construct(private readonly ExternalDataset $external) {}

    /**
     * MIT AI Risk Repository subdomains by id, with the names the incident and
     * risk tables are keyed on.
     *
     * @return array<string, array{id: string, name: string, domain_id: string, domain: string, domain_label: ?string}>
     */
    public function subdomains(): array
    {
        return once(function () {
            $out = [];
            foreach ($this->external->mitRisk()['domains'] ?? [] as $domain) {
                foreach ($domain['subdomains'] ?? [] as $sub) {
                    $out[(string) $sub['id']] = ['id' => (string) $sub['id'], 'name' => $sub['name'], 'domain_id' => (string) $domain['id'], 'domain' => $domain['name'], 'domain_label' => $domain['aiid_domain_label'] ?? null];
                }
            }

            return $out;
        });
    }

    /**
     * The risk subdomains a control addresses, each with live incident and risk-entry counts.
     *
     * @return Collection<int, array{id: string, name: string, domain: string, incidents: int, risks: int, url: string}>
     */
    public function risksFor(Control $control): Collection
    {
        $known = $this->subdomains();

        return collect($control->risk_subdomains ?? [])
            ->filter(fn ($id) => isset($known[$id]))
            ->map(function ($id) use ($known) {
                $sub = $known[$id];

                return [
                    'id' => $sub['id'],
                    'name' => $sub['name'],
                    'domain' => $sub['domain'],
                    'incidents' => $this->incidentCount($sub['name']),
                    'risks' => $this->riskCount($sub['id']),
                    'url' => route('risk.subdomain', [$sub['domain_id'], $sub['id']]),
                ];
            })->values();
    }

    /** Counters for one framework: the controls, evidence types, risk areas and incidents behind its references. */
    public function frameworkCounters(string $framework): array
    {
        $controls = Control::published()->with('evidence')->whereHas('frameworkReferences', fn ($q) => $q->where('framework', $framework))->get();
        $subdomains = $controls->flatMap(fn ($c) => $c->risk_subdomains ?? [])->unique()->values();
        $known = $this->subdomains();

        return [
            'controls' => $controls->count(),
            'evidence_types' => $controls->flatMap(fn ($c) => $c->evidence->pluck('evidence_type'))->unique()->count(),
            'risk_subdomains' => $subdomains->count(),
            'incidents' => $subdomains->filter(fn ($id) => isset($known[$id]))->sum(fn ($id) => $this->incidentCount($known[$id]['name'])),
            'list' => $controls->sortBy('title')->values(),
        ];
    }

    /** Controls that cite a framework, keyed by the reference they cite, for a framework page. */
    public function controlsByReference(string $framework): Collection
    {
        return ControlFrameworkReference::with('control.evidence')->where('framework', $framework)->get()
            ->filter(fn ($r) => $r->control?->published_at)
            ->sortBy(fn ($r) => $r->control->title)
            ->values();
    }

    private function incidentCount(string $subdomainName): int
    {
        return once(fn () => ExternalIncident::whereNotNull('mit_subdomain')->selectRaw('mit_subdomain, COUNT(*) as n')->groupBy('mit_subdomain')->pluck('n', 'mit_subdomain')->all())[$subdomainName] ?? 0;
    }

    private function riskCount(string $subdomainId): int
    {
        return once(fn () => ExternalRisk::whereNotNull('subdomain')->selectRaw('subdomain, COUNT(*) as n')->groupBy('subdomain')->pluck('n', 'subdomain')->all())[$subdomainId] ?? 0;
    }
}
