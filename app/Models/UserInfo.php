<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserInfo extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id', // required
        'phone_no', // required
        'organization_name',
        'organization_email',
        'ip_address', // required
        'user_agent', // required
        'last_activity', // required
        'terms_condition', // required
        'status',
    ];

    /**
     * Never serialize these to the frontend (Inertia props / JSON).
     */
    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'last_activity' => 'integer',
    ];

    /**
     * Get the user that owns the UserInfo.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
