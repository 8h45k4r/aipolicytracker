<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControlFrameworkReference extends Model
{
    protected $guarded = [];

    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class);
    }

    public function frameworkName(): string
    {
        return FrameworkMapping::FRAMEWORKS[$this->framework] ?? $this->framework;
    }

    public function frameworkShort(): string
    {
        return config('frameworks.'.$this->framework.'.short', $this->frameworkName());
    }
}
