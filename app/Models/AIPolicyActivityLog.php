<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AIPolicyActivityLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'ai_policy_tracker_id',
        'activity_name',
        'description',
    ];

    public function aiPolicyTracker(): BelongsTo
    {
        return $this->belongsTo(AiPolicyTracker::class, 'ai_policy_tracker_id');
    }
}
