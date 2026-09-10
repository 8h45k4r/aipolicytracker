<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Status extends Model
{
    use SoftDeletes, HasUuids;

    public $timestamps = true;
    protected $fillable = [
        "name",
    ];

    public function news()
    {
        return $this->hasMany(News::class, 'status_id', 'id');
    }

    public function aiPolicies()
    {
        return $this->hasMany(AiPolicyTracker::class, 'status_id', 'id');
    }
}
