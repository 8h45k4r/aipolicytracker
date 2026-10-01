<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An owner's edit to what one role may do. No row for a role means its defaults apply. */
class AdminRolePermission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['capabilities' => 'array'];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
