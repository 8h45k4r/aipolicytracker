<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A consent decision, written when it is made: what, granted or withdrawn, where, and any detail. No IP, no agent. */
class ConsentEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['granted' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(User|int $user, string $kind, bool $granted, string $source, ?string $detail = null): self
    {
        return self::create(['user_id' => $user instanceof User ? $user->id : $user, 'kind' => $kind, 'granted' => $granted, 'source' => $source, 'detail' => $detail]);
    }
}
