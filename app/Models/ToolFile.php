<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ToolFile extends Model
{
    public const DISK = 'local';

    public const ALLOWED = ['xlsx', 'csv', 'md', 'pdf', 'docx', 'json', 'txt'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function exists(): bool
    {
        return Storage::disk(self::DISK)->exists($this->disk_path);
    }

    public function absolutePath(): string
    {
        return Storage::disk(self::DISK)->path($this->disk_path);
    }
}
