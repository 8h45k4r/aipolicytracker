<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxonomyTerm extends Model
{
    protected $guarded = [];

    public const TAXONOMIES = [
        'actor' => 'AI actor',
        'ai_system_type' => 'AI system type',
        'sector' => 'Sector',
        'risk_category' => 'Risk category',
        'use_case' => 'AI use case',
        'obligation_category' => 'Obligation category',
    ];

    public function scopeTaxonomy($query, string $taxonomy)
    {
        return $query->where('taxonomy', $taxonomy)->orderBy('sort_order')->orderBy('name');
    }

    public function policyInstruments()
    {
        return $this->morphedByMany(PolicyInstrument::class, 'assignable', 'taxonomy_assignments');
    }

    public function obligations()
    {
        return $this->morphedByMany(Obligation::class, 'assignable', 'taxonomy_assignments');
    }
}
