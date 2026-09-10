<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class News extends Model
{
    use SoftDeletes, HasUuids;

    protected $fillable = [
        'title',
        'policy_tracker_id',
        'status_id',
        'upload_date',
        'description',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'upload_date' => 'date',
    ];


    public function thumbnail()
    {
        return $this->hasOne(Thumbnail::class);
    }

    public function newsFutureImage()
    {
        return $this->hasOne(NewsFutureImage::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id', 'id');
    }

    public function policyTracker()
    {
        return $this->belongsTo(AiPolicyTracker::class, 'policy_tracker_id');
    }

    /**
     * Get the formatted upload date.
     *
     * @return string
     */
    public function getUploadDateAttribute()
    {
        return $this->attributes['upload_date']
            ? \Carbon\Carbon::parse($this->attributes['upload_date'])->format('M d, Y')
            : null;
    }


}
