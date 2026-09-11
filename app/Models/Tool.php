<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A free tool on /guides (template, checklist, register or plan) managed in the admin.
 * Files live on the private local disk and are served only through signed, owner-bound links.
 */
class Tool extends Model
{
    public const TYPES = ['guide' => 'Guide', 'template' => 'Template', 'checklist' => 'Checklist', 'register' => 'Register'];

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];

    public const LABELS = ['xlsx' => 'XLSX', 'csv' => 'CSV', 'md' => 'Markdown', 'pdf' => 'PDF', 'docx' => 'DOCX', 'json' => 'JSON', 'txt' => 'Text'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fields' => 'array', 'instructions' => 'array', 'frameworks' => 'array', 'topics' => 'array', 'related_guides' => 'array', 'related_policies' => 'array', 'featured' => 'boolean', 'updated_on' => 'date'];
    }

    public function files(): HasMany
    {
        return $this->hasMany(ToolFile::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeFiles(): HasMany
    {
        return $this->files()->where('is_active', true);
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(ResourceDownload::class, 'resource_slug', 'slug');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function url(): string
    {
        return route('tools.show', $this->slug);
    }

    public function formatList(): string
    {
        return $this->activeFiles->pluck('label')->implode(', ');
    }

    /** Card shape shared with the editorial guides on /guides. */
    public function card(): array
    {
        return ['slug' => $this->slug, 'kind' => 'tool', 'type' => $this->type, 'title' => $this->title, 'short' => $this->short,
            'frameworks' => $this->frameworks ?? [], 'topics' => $this->topics ?? [], 'featured' => $this->featured, 'files' => $this->activeFiles->count()];
    }

    /** Editorial guides from config/content.php as cards with the same filter fields. */
    public static function guideCards(): Collection
    {
        $tags = config('resources.guide_tags', []);

        return collect(config('content.guides', []))->filter(fn ($g) => isset($g['summary']))->map(fn ($g, $slug) => [
            'slug' => $slug, 'kind' => 'guide', 'type' => 'guide', 'title' => $g['h1'], 'short' => $g['summary'],
            'frameworks' => $tags[$slug]['frameworks'] ?? array_values(array_filter([$g['framework'] ?? null])),
            'topics' => $tags[$slug]['topics'] ?? [], 'featured' => $tags[$slug]['featured'] ?? false, 'files' => 0,
        ])->values();
    }
}
