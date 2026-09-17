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
            'terms_accepted_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
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


    /**
     * Whether this user may access the admin area (configured via ADMIN_EMAILS).
     */
    public function resourceDownloads(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ResourceDownload::class);
    }

    public function applicabilityProfiles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ApplicabilityProfile::class)->orderBy('name');
    }

    public function follows(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Follow::class)->orderByDesc('id');
    }

    public function alertDeliveries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AlertDelivery::class);
    }

    public function billingCustomer(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BillingCustomer::class);
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class)->orderByDesc('id');
    }

    /** The subscription currently granting access, if any (decided by the Entitlements service). */
    public function activeSubscription(): ?Subscription
    {
        return app(\App\Services\Billing\Entitlements::class)->activeSubscription($this);
    }

    /** Plan key from config/billing.php ("free" when no paid plan is active). */
    public function planKey(): string
    {
        return $this->activeSubscription()?->plan_key ?? 'free';
    }

    /** Whether the user's plan grants a capability such as "alerts.daily". */
    public function entitled(string $capability): bool
    {
        return app(\App\Services\Billing\Entitlements::class)->allows($this, $capability);
    }

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
