<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    use HasUuids, SoftDeletes;

    protected $appends = ['formatted_created_at'];

    protected $fillable = [
        'symbol',
        'name',
        'status',
    ];

    public function aiPolicyTrackers()
    {
        return $this->hasMany(AiPolicyTracker::class, 'country_id', 'id');
    }

    public function getFormattedCreatedAtAttribute()
    {
        return Carbon::parse($this->created_at)->format('d M Y');
    }
}
