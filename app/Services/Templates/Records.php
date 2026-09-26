<?php

namespace App\Services\Templates;

use App\Models\Control;
use App\Models\ControlFrameworkReference;
use App\Models\Deadline;
use App\Models\FrameworkMapping;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TaxonomyTerm;
use App\Services\ExternalData\ExternalDataset;
use Illuminate\Support\Collection;

/**
 * The read model, as rows a template can use. Every builder draws from here,
 * so a duty is described the same way in every file, and every row that
 * cites a duty carries the record link and the source reference.
 */
final class Records
{
    /**
     * @param  array{categories?:list<string>, policies?:list<string>, actors?:list<string>}  $filter
     * @return Collection<int,Obligation>
     */
    public static function obligations(array $filter = []): Collection
    {
        $q = Obligation::published()->with(['policyInstrument.jurisdiction', 'terms', 'evidenceArtifacts', 'frameworkMappings', 'applicabilityRules'])
            ->whereHas('policyInstrument', fn ($p) => $p->published());
        if (! empty($filter['categories'])) {
            $q->whereIn('category', $filter['categories']);
        }
        if (! empty($filter['policies'])) {
            $q->whereHas('policyInstrument', fn ($p) => $p->whereIn('slug', $filter['policies']));
        }
        if (! empty($filter['actors'])) {
            $q->whereHas('terms', fn ($t) => $t->where('taxonomy', 'actor')->whereIn('slug', $filter['actors']));
        }

        return $q->get()->sortBy(fn ($o) => [$o->policyInstrument->jurisdiction->name, $o->policyInstrument->title, $o->sort_order ?? 0, $o->title])->values();
    }

    /** @param Collection<int,Obligation> $obligations @return list<array<string,mixed>> */
    public static function obligationRows(Collection $obligations): array
    {
        return $obligations->map(fn (Obligation $o) => [
            'duty' => $o->title,
            'category' => self::categoryName($o->category),
            'instrument' => $o->policyInstrument->short_title ?: $o->policyInstrument->title,
            'jurisdiction' => $o->policyInstrument->jurisdiction->name,
            'actors' => $o->termsOf('actor')->pluck('name')->implode(', '),
            'binding' => $o->is_binding ? 'Legal requirement' : 'Voluntary',
            'reference' => $o->source_reference,
            'applies_from' => $o->applies_from?->toDateString(),
            'summary' => $o->summary,
            'action' => $o->practical_action,
            'evidence' => $o->evidenceArtifacts->pluck('title')->implode('; '),
            'iso' => $o->frameworkMappings->where('framework', self::frameworkKey('iso-42001'))->pluck('reference')->implode('; '),
            'nist' => $o->frameworkMappings->where('framework', self::frameworkKey('nist-ai-rmf'))->pluck('reference')->implode('; '),
            'verification' => $o->verificationLabel(),
            'url' => $o->url(),
        ])->values()->all();
    }

    /** The standard columns for a duties reference sheet. */
    public static function obligationColumns(): array
    {
        return [
            ['key' => 'duty', 'label' => 'Duty', 'width' => 52],
            ['key' => 'category', 'label' => 'Category', 'width' => 22],
            ['key' => 'instrument', 'label' => 'Instrument', 'width' => 28],
            ['key' => 'jurisdiction', 'label' => 'Jurisdiction', 'width' => 18],
            ['key' => 'actors', 'label' => 'Who it binds', 'width' => 22],
            ['key' => 'binding', 'label' => 'Nature', 'width' => 16],
            ['key' => 'reference', 'label' => 'Source reference', 'width' => 24],
            ['key' => 'applies_from', 'label' => 'Applies from', 'width' => 13, 'type' => 'date'],
            ['key' => 'summary', 'label' => 'What it requires', 'width' => 60],
            ['key' => 'evidence', 'label' => 'Evidence a reviewer expects', 'width' => 40],
            ['key' => 'iso', 'label' => 'ISO/IEC 42001', 'width' => 16],
            ['key' => 'nist', 'label' => 'NIST AI RMF', 'width' => 16],
            ['key' => 'verification', 'label' => 'Verification', 'width' => 26],
            ['key' => 'url', 'label' => 'Record', 'width' => 44, 'type' => 'url'],
        ];
    }

    /** @return Collection<int,Control> */
    public static function controls(): Collection
    {
        return Control::published()->with(['evidence', 'frameworkReferences', 'obligations' => fn ($q) => $q->whereNotNull('obligations.published_at')])->orderBy('title')->get();
    }

    /** @return list<array<string,mixed>> */
    public static function controlRows(): array
    {
        return self::controls()->map(fn (Control $c) => [
            'control' => $c->title,
            'kind' => ucfirst((string) $c->kind),
            'purpose' => $c->purpose,
            'owner' => $c->owner_role,
            'frequency' => $c->frequency,
            'satisfies' => $c->obligations->where('pivot.relationship', 'satisfies')->count(),
            'supports' => $c->obligations->where('pivot.relationship', 'supports')->count(),
            'evidence' => $c->evidence->pluck('title')->implode('; '),
            'iso' => $c->frameworkReferences->where('framework', self::frameworkKey('iso-42001'))->pluck('reference')->implode('; '),
            'nist' => $c->frameworkReferences->where('framework', self::frameworkKey('nist-ai-rmf'))->pluck('reference')->implode('; '),
            'url' => $c->url(),
        ])->values()->all();
    }

    public static function controlColumns(): array
    {
        return [
            ['key' => 'control', 'label' => 'Control', 'width' => 44],
            ['key' => 'kind', 'label' => 'Kind', 'width' => 12],
            ['key' => 'purpose', 'label' => 'Purpose', 'width' => 60],
            ['key' => 'owner', 'label' => 'Typical owner', 'width' => 22],
            ['key' => 'frequency', 'label' => 'Frequency', 'width' => 14],
            ['key' => 'satisfies', 'label' => 'Duties it satisfies', 'width' => 12, 'type' => 'number'],
            ['key' => 'supports', 'label' => 'Duties it supports', 'width' => 12, 'type' => 'number'],
            ['key' => 'evidence', 'label' => 'Evidence it produces', 'width' => 44],
            ['key' => 'iso', 'label' => 'ISO/IEC 42001', 'width' => 16],
            ['key' => 'nist', 'label' => 'NIST AI RMF', 'width' => 16],
            ['key' => 'url', 'label' => 'Record', 'width' => 44, 'type' => 'url'],
        ];
    }

    /** @param list<string>|null $policySlugs @return list<array<string,mixed>> */
    public static function deadlineRows(?array $policySlugs = null): array
    {
        return Deadline::with('policyInstrument.jurisdiction')
            ->whereHas('policyInstrument', fn ($p) => $p->published()->when($policySlugs, fn ($q) => $q->whereIn('slug', $policySlugs)))
            ->orderBy('due_on')->get()
            ->map(fn (Deadline $d) => [
                'date' => $d->due_on?->toDateString(),
                'milestone' => $d->title,
                'instrument' => $d->policyInstrument->short_title ?: $d->policyInstrument->title,
                'jurisdiction' => $d->policyInstrument->jurisdiction->name,
                'reference' => $d->source_reference,
                'status' => $d->deadline_status,
                'confidence' => $d->confidence_level,
                'note' => $d->description,
                'url' => $d->policyInstrument->url(),
            ])->values()->all();
    }

    public static function deadlineColumns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date', 'width' => 13, 'type' => 'date'],
            ['key' => 'milestone', 'label' => 'Milestone', 'width' => 60],
            ['key' => 'instrument', 'label' => 'Instrument', 'width' => 28],
            ['key' => 'jurisdiction', 'label' => 'Jurisdiction', 'width' => 18],
            ['key' => 'reference', 'label' => 'Source reference', 'width' => 22],
            ['key' => 'status', 'label' => 'Status', 'width' => 12],
            ['key' => 'confidence', 'label' => 'Confidence', 'width' => 12],
            ['key' => 'note', 'label' => 'Note', 'width' => 50],
            ['key' => 'url', 'label' => 'Record', 'width' => 44, 'type' => 'url'],
        ];
    }

    /** @return Collection<int,PolicyInstrument> */
    public static function bindingInstruments(): Collection
    {
        return PolicyInstrument::published()->where('is_binding', true)->whereNotNull('official_source_url')->with(['jurisdiction', 'terms', 'deadlines'])->get()
            ->sortBy(fn ($p) => [$p->jurisdiction->name, $p->title])->values();
    }

    public static function policy(string $slug): ?PolicyInstrument
    {
        return PolicyInstrument::published()->with(['jurisdiction', 'deadlines', 'obligations'])->where('slug', $slug)->first();
    }

    /** @return list<array{id:string, name:string, description:?string, subdomains:list<array{id:string,name:string,description:?string}>, use_cases:list<string>}> */
    public static function mitDomains(): array
    {
        return array_values(app(ExternalDataset::class)->mitRisk()['domains'] ?? []);
    }

    /** @return list<array<string,mixed>> */
    public static function regulatorRows(): array
    {
        $rows = [];
        foreach (Jurisdiction::published()->orderBy('name')->get() as $j) {
            foreach ($j->regulators ?? [] as $r) {
                if (! empty($r['name'])) {
                    $rows[] = ['jurisdiction' => $j->name, 'regulator' => $r['name'], 'role' => $r['role'] ?? null, 'url' => $r['url'] ?? null, 'record' => $j->url()];
                }
            }
        }

        return $rows;
    }

    /**
     * Every reference to a framework across the recorded mappings and control
     * references, with the duties and controls mapped to each.
     *
     * @return list<array{reference:string, duties:int, controls:int, duty_titles:string, control_titles:string, confidence:string}>
     */
    public static function frameworkReferences(string $framework): array
    {
        $framework = self::frameworkKey($framework);
        $out = [];
        foreach (FrameworkMapping::where('framework', $framework)->with('obligation.policyInstrument.jurisdiction')->get() as $m) {
            if (! $m->obligation || ! $m->obligation->published_at) {
                continue;
            }
            $ref = trim((string) $m->reference);
            $out[$ref]['duties'][] = $m->obligation->title.' ('.($m->obligation->policyInstrument->short_title ?: $m->obligation->policyInstrument->title).')';
            $out[$ref]['confidence'][] = $m->confidence_level;
        }
        foreach (ControlFrameworkReference::where('framework', $framework)->with('control')->get() as $r) {
            if (! $r->control || ! $r->control->published_at) {
                continue;
            }
            $ref = trim((string) $r->reference);
            $out[$ref]['controls'][] = $r->control->title;
            $out[$ref]['confidence'][] = $r->confidence_level;
        }
        ksort($out, SORT_NATURAL);
        $rows = [];
        foreach ($out as $ref => $x) {
            $conf = array_count_values(array_filter($x['confidence'] ?? []));
            arsort($conf);
            $rows[] = [
                'reference' => $ref,
                'duties' => count($x['duties'] ?? []),
                'controls' => count($x['controls'] ?? []),
                'duty_titles' => implode('; ', array_unique($x['duties'] ?? [])),
                'control_titles' => implode('; ', array_unique($x['controls'] ?? [])),
                'confidence' => (string) (array_key_first($conf) ?? ''),
            ];
        }

        return $rows;
    }

    public static function categoryName(?string $slug): string
    {
        static $names = null;
        $names ??= TaxonomyTerm::where('taxonomy', 'obligation_category')->pluck('name', 'slug')->all();

        return $names[$slug] ?? ucfirst(str_replace('_', ' ', (string) $slug));
    }

    /** @return list<string> */
    public static function actorNames(): array
    {
        return TaxonomyTerm::where('taxonomy', 'actor')->orderBy('sort_order')->pluck('name')->all();
    }

    /** @return list<string> */
    public static function jurisdictionNames(bool $bindingOnly = true): array
    {
        $q = Jurisdiction::published()->orderBy('name');
        if ($bindingOnly) {
            $q->whereHas('policyInstruments', fn ($p) => $p->published()->where('is_binding', true));
        }

        return $q->pluck('name')->all();
    }

    public static function frameworkName(string $key): string
    {
        $key = self::frameworkKey($key);

        return config("frameworks.{$key}.name") ?? config("templates.frameworks.{$key}") ?? $key;
    }

    /**
     * The key a framework is recorded under in the mappings ("iso_42001"),
     * from either that key or the public slug ("iso-42001"). The catalogue
     * and the URLs use the slug; the read model uses the key.
     */
    public static function frameworkKey(string $slugOrKey): string
    {
        static $bySlug = null;
        $bySlug ??= collect(config('frameworks', []))->mapWithKeys(fn ($f, $k) => [$f['slug'] ?? $k => $k])->all();

        return $bySlug[$slugOrKey] ?? $slugOrKey;
    }

    /** A duty as one line for a document, with its citation. */
    public static function dutyLine(Obligation $o): string
    {
        return $o->title.' — '.($o->policyInstrument->short_title ?: $o->policyInstrument->title).($o->source_reference ? ', '.$o->source_reference : '').' ('.$o->policyInstrument->jurisdiction->name.')';
    }
}
