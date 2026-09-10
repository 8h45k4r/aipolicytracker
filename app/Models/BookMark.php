<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookMark extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'ai_policy_tracker_id'
    ];

    public static function authUserBookmarkCount()
    {
        if (Auth::check()) {
            return self::where('user_id', Auth::id())->count();
        }

        return 0; // Return 0 if the user is not authenticated
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function aipolicy()
    {
        return $this->hasOne(AiPolicyTracker::class, 'id', 'ai_policy_tracker_id');
    }

}
