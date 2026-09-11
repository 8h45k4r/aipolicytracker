<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One gated download of a free tool: who, which resource, file and version, and the terms acceptance. */
class ResourceDownload extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['terms_accepted_at' => 'datetime', 'downloaded_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resource(): ?array
    {
        return FreeTool::find($this->resource_slug);
    }
}
