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

    /** Copy bundled with the repository (seeded tools only), used to restore or serve a file missing from the disk. */
    public function bundledPath(): ?string
    {
        $path = resource_path('downloads/'.$this->tool->slug.'/'.$this->file_name);

        return preg_match('/^[a-z0-9.-]+$/', $this->file_name) && is_file($path) ? $path : null;
    }

    /** Absolute path to serve: the private disk, else the bundled copy (restored to the disk on the way). */
    public function servablePath(): ?string
    {
        if ($this->exists()) {
            return $this->absolutePath();
        }
        if ($bundled = $this->bundledPath()) {
            Storage::disk(self::DISK)->put($this->disk_path, file_get_contents($bundled));

            return $this->exists() ? $this->absolutePath() : $bundled;
        }

        return null;
    }
}
