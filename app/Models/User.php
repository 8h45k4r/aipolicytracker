<?php

namespace App\Models;

use App\Notifications\CustomVerifyEmailNotification;
use App\Services\Billing\Entitlements;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            // Encrypted at rest: the authenticator secret is equivalent to a second password,
            // and the recovery codes (already bcrypt-hashed) are wrapped again so a database
            // dump alone reveals neither.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_step' => 'integer',
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
        $this->notify(new CustomVerifyEmailNotification);
    }

    /**
     * Whether this user may access the admin area (configured via ADMIN_EMAILS).
     */
    public function resourceDownloads(): HasMany
    {
        return $this->hasMany(ResourceDownload::class);
    }

    public function applicabilityProfiles(): HasMany
    {
        return $this->hasMany(ApplicabilityProfile::class)->orderBy('name');
    }

    public function follows(): HasMany
    {
        return $this->hasMany(Follow::class)->orderByDesc('id');
    }

    public function alertDeliveries(): HasMany
    {
        return $this->hasMany(AlertDelivery::class);
    }

    public function billingCustomer(): HasOne
    {
        return $this->hasOne(BillingCustomer::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->orderByDesc('id');
    }

    /** The subscription currently granting access, if any (decided by the Entitlements service). */
    public function activeSubscription(): ?Subscription
    {
        return app(Entitlements::class)->activeSubscription($this);
    }

    /** Plan key from config/billing.php ("free" when no paid plan is active). */
    public function planKey(): string
    {
        return $this->activeSubscription()?->plan_key ?? 'free';
    }

    /** Whether the user's plan grants a capability such as "alerts.daily". */
    public function entitled(string $capability): bool
    {
        return app(Entitlements::class)->allows($this, $capability);
    }

    /** An admin who has scanned a secret and proved one code from it. */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
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
