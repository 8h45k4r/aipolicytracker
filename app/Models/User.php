<?php

namespace App\Models;

use App\Notifications\CustomVerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $appends = ['formatted_created_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function userInfo()
    {
        return $this->hasOne(UserInfo::class);
    }

    public function getFormattedCreatedAtAttribute()
    {
        return Carbon::parse($this->created_at)->format('d M Y');
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmailNotification());
    }

    public function bookMarks()
    {
        return $this->hasMany(BookMark::class, 'user_id', 'id');
    }

    /**
     * Whether this user may access the admin area (configured via ADMIN_EMAILS).
     */
    public function isAdmin(): bool
    {
        return in_array(strtolower((string) $this->email), array_map('strtolower', config('aipolicytracker.admin_emails', [])), true);
    }

    /**
     * Query scope excluding configured admin accounts.
     */
    public function scopeNonAdmin($query)
    {
        $admins = config('aipolicytracker.admin_emails', []);

        return $admins ? $query->whereNotIn('email', $admins) : $query;
    }
}
