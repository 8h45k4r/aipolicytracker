<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A headline from an official source's own feed. See config/news.php and docs/modules/news.md. */
class NewsItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['topics' => 'array', 'published_at' => 'datetime', 'fetched_at' => 'datetime'];
    }

    public function source(): array
    {
        return config('news.sources.'.$this->source, ['name' => $this->source, 'publisher' => $this->source]);
    }
}
