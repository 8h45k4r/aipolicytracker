<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A quarter's state-of-AI-regulation figures, frozen so a past report reads the same later. */
class ReportSnapshot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'frozen_at' => 'datetime'];
    }
}
