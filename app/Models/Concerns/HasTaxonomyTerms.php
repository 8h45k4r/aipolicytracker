<?php

namespace App\Models\Concerns;

use App\Models\TaxonomyTerm;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTaxonomyTerms
{
    public function terms(): MorphToMany
    {
        return $this->morphToMany(TaxonomyTerm::class, 'assignable', 'taxonomy_assignments')->withTimestamps();
    }

    public function termsOf(string $taxonomy)
    {
        return $this->terms->where('taxonomy', $taxonomy)->values();
    }

    public function scopeWithTerm($query, string $taxonomy, string|array $slugs)
    {
        $slugs = array_filter((array) $slugs);
        if ($slugs === []) {
            return $query;
        }

        return $query->whereHas('terms', fn ($q) => $q->where('taxonomy', $taxonomy)->whereIn('slug', $slugs));
    }
}
