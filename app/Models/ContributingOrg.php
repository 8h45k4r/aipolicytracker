<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContributingOrg extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'file_path',
        'url'
    ];


    public function getCreatedAtAttribute()
    {
        return $this->attributes['created_at']
            ? \Carbon\Carbon::parse($this->attributes['created_at'])->format('M d, Y')
            : null;
    }
}
