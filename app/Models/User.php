<?php

namespace App\Models;

use App\Enums\AdminCapability;
use App\Enums\AdminRole;
use App\Notifications\CustomVerifyEmailNotification;
use App\Services\Billing\Entitlements;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
            'admin_role' => AdminRole::class,
            'admin_role_granted_at' => 'datetime',
            'suspended_at' => 'datetime',
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

    /**
     * Ownership comes from the ADMIN_EMAILS environment list and from nowhere else, so it
     * cannot be granted, revoked or lost through the database or the admin UI.
     */
    public function isOwner(): bool
    {
        return in_array(strtolower((string) $this->email), array_map('strtolower', config('aipolicytracker.admin_emails', [])), true);
    }

    /** The granted role, or null for an account with no stored role. Owners need none. */
    public function adminRole(): ?AdminRole
    {
        return $this->admin_role;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Any administrative access at all, which is what gates /backend and what requires a
     * second factor. A suspended account has none, whatever it was granted, and an owner is
     * not exempt from that: suspension is the stop button, so it has to stop everyone.
     */
    public function isAdmin(): bool
    {
        return ! $this->isSuspended() && ($this->isOwner() || $this->admin_role !== null);
    }

    /**
     * Whether this account may do one named thing. Owners hold every capability; everyone else
     * holds exactly what their role declares, which is why settings, billing and user
     * management are unreachable for a granted role rather than merely hidden from it.
     */
    public function hasCapability(AdminCapability $capability): bool
    {
        if (! $this->isAdmin()) {
            return false;
        }

        return $this->isOwner() || (bool) $this->admin_role?->has($capability);
    }

    /** For display: what this account is, in one word. */
    public function adminRoleLabel(): string
    {
        return match (true) {
            $this->isOwner() => 'Owner',
            $this->admin_role !== null => $this->admin_role->label(),
            default => 'None',
        };
    }

    /** Who granted this account's role, for the "who let them in" question. */
    public function adminRoleGrantedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'admin_role_granted_by');
    }

    /** Query scope excluding every account with administrative access. */
    public function scopeNonAdmin($query)
    {
        $admins = config('aipolicytracker.admin_emails', []);

        return $query->whereNull('admin_role')
            ->when($admins !== [], fn ($q) => $q->whereNotIn('email', $admins));
    }
}
