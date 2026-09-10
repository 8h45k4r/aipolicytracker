<?php

namespace App\Services\PolicyData;

use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Models\TaxonomyTerm;

class OpenDataExporter
{
    public function __construct(private readonly PolicySerializer $serializer)
    {
    }

    public function bundle(): array
    {
        return [
            'dataset' => 'AIPolicyTracker open policy data',
            'license' => config('aipolicytracker.data_license'),
            'license_url' => config('aipolicytracker.data_license_url'),
            'generated_at' => now()->toIso8601String(),
            'schema_version' => config('aipolicytracker.data_schema_version'),
            'citation' => config('aipolicytracker.citation'),
            'disclaimer' => 'Informational only; not legal advice. Verify against the linked official sources.',
            'taxonomies' => TaxonomyTerm::orderBy('taxonomy')->orderBy('sort_order')->get()->groupBy('taxonomy')->map(fn ($terms) => $terms->map(fn ($t) => ['slug' => $t->slug, 'name' => $t->name, 'description' => $t->description])->values())->all(),
            'jurisdictions' => Jurisdiction::published()->orderBy('name')->get()->map(fn ($j) => $this->serializer->jurisdiction($j))->all(),
            'policies' => PolicyInstrument::published()->with('jurisdiction')->orderBy('slug')->get()->map(fn ($p) => $this->serializer->policy($p))->all(),
            'changes' => ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])->orderByDesc('occurred_on')->get()->map(fn ($c) => $this->serializer->change($c))->all(),
        ];
    }
}
