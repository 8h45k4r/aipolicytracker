<?php

namespace App\Models;

use App\Models\Concerns\HasSourceQuality;
use App\Services\Hubs\HubCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jurisdiction extends Model
{
    use HasSourceQuality;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'how_to_use' => 'array',
            'regulators' => 'array',
            'official_sources' => 'array',
            'faq' => 'array',
            'related_jurisdictions' => 'array',
            'featured' => 'boolean',
            'source_document_date' => 'date',
            'last_checked_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_jurisdiction_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_jurisdiction_id');
    }

    public function policyInstruments(): HasMany
    {
        return $this->hasMany(PolicyInstrument::class);
    }

    public function changeEvents(): HasMany
    {
        return $this->hasMany(ChangeEvent::class);
    }

    public function enforcementEvents(): HasMany
    {
        return $this->hasMany(EnforcementEvent::class);
    }

    public function procurementRules(): HasMany
    {
        return $this->hasMany(ProcurementRule::class);
    }

    public function sourceDocuments(): HasMany
    {
        return $this->hasMany(SourceDocument::class);
    }

    /**
     * Indexation threshold: a jurisdiction page is indexable only when it has an
     * overview and at least one published policy instrument with an official source.
     */
    public function isIndexable(): bool
    {
        if (blank($this->overview) || blank($this->regulatory_status_summary)) {
            return false;
        }

        // Read the flag when the caller loaded it with withPublishedInstrument(),
        // so a list of jurisdictions costs one query rather than one per row.
        // Falling back to the query keeps a single hydrated model correct.
        if (isset($this->attributes['has_published_instrument'])) {
            return (bool) $this->attributes['has_published_instrument'];
        }

        return $this->policyInstruments()->published()->whereNotNull('official_source_url')->exists();
    }

    /**
     * Load the flag isIndexable() needs, as one aggregate rather than a query per
     * row. Use this anywhere a collection of jurisdictions is filtered by it.
     */
    public function scopeWithPublishedInstrument($query)
    {
        return $query->withExists(['policyInstruments as has_published_instrument' => fn ($q) => $q->published()->whereNotNull('official_source_url'),
        ]);
    }

    /** Name with a definite article where English requires one ("the United Kingdom"). */
    public function nameWithArticle(): string
    {
        $needsThe = '/^(United|European|Netherlands|Philippines|Council of Europe|African Union|Czech Republic|Dominican Republic|Central African Republic|Democratic Republic|Republic of|Gambia|Bahamas|Maldives|Marshall Islands|Solomon Islands|Comoros|Seychelles|Isle of Man|Cayman Islands|Holy See|Vatican|Organisation|Organization)\b/';

        return preg_match($needsThe, $this->name) ? 'the '.$this->name : $this->name;
    }

    /** nameWithArticle() for the start of a sentence ("The United Kingdom"). */
    public function nameWithArticleCapitalised(): string
    {
        return ucfirst($this->nameWithArticle());
    }

    /** The hub address for a country that has one (config/hubs.php), else null. */
    public function hubSlug(): ?string
    {
        return HubCatalog::hubSlug($this->slug);
    }

    /** Whether the page at url() may be indexed: the hub guard for a hub country, the record guard otherwise. */
    public function isPageIndexable(): bool
    {
        return $this->hubSlug() ? HubCatalog::isHubIndexable($this) : $this->isIndexable();
    }

    public function url(): string
    {
        $hub = $this->hubSlug();

        return $hub ? route('hubs.show', $hub) : route('jurisdictions.show', $this->slug);
    }
}
