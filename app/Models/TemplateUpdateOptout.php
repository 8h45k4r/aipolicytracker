<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An address that asked for no more template update emails, and when. */
class TemplateUpdateOptout extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['unsubscribed_at' => 'datetime'];
    }
}
