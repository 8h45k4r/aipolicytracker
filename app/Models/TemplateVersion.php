<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One built version of a template: which dataset it came from, the hash of
 * the content that made it, the files on disk and what changed since the
 * version before. A version is never rewritten; a rebuild that changes the
 * content adds the next one.
 */
class TemplateVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['files' => 'array', 'stats' => 'array', 'preview' => 'array', 'generated_at' => 'datetime', 'version' => 'integer', 'downloads' => 'integer'];
    }

    public static function latestFor(string $slug): ?self
    {
        return self::where('slug', $slug)->orderByDesc('version')->first();
    }

    /** @return array<string, self> slug => latest version */
    public static function latestAll(): array
    {
        $out = [];
        foreach (self::orderBy('slug')->orderByDesc('version')->get() as $v) {
            $out[$v->slug] ??= $v;
        }

        return $out;
    }

    public function scopeFor(Builder $q, string $slug): Builder
    {
        return $q->where('slug', $slug);
    }

    public function label(): string
    {
        return 'v'.$this->version;
    }

    public function file(string $format): ?array
    {
        foreach ($this->files ?? [] as $f) {
            if ($f['format'] === $format) {
                return $f;
            }
        }

        return null;
    }

    public function formats(): array
    {
        return array_column($this->files ?? [], 'format');
    }

    public function url(): string
    {
        return route('templates.show', $this->slug);
    }

    public function downloadUrl(string $format): string
    {
        return route('templates.download', ['slug' => $this->slug, 'format' => $format]);
    }
}
