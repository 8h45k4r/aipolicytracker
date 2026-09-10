<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AiPolicyTracker extends Model
{
    use SoftDeletes, HasUuids;
    use HasUuids;

    protected $appends = ['formatted_created_at', 'created_month'];

    protected $fillable = [
        'gov_ai_index',
        'country_id',
        'status_id',
        'ai_policy_name',
        'governing_body',
        'announcement_year',
        'whitepaper_document_link',
        'technology_partners',
        'governance_structure',
        'main_motivation',
        'description',
    ];

    public function scopeAuthLimitData($query)
    {
        if (Auth::check()) {
            // If the user is authenticated, paginate with 10 records per page
            return $query->paginate(10);
        }
        // If the user is not authenticated, limit the result to 3 records
        return $query->paginate(3, ['*'], 'page', 1);

    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }


    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id', 'id');
    }

    public function getFormattedCreatedAtAttribute()
    {
        return Carbon::parse($this->announcement_year)->format('d M Y');
    }

    public function news(): HasMany
    {
        return $this->hasMany(News::class, 'policy_tracker_id');
    }

    public function aIPolicyActivityLogs(): HasMany
    {
        return $this->hasMany(AIPolicyActivityLog::class, 'ai_policy_tracker_id');
    }

    public function bookmark()
    {
        return $this->hasOne(BookMark::class, 'ai_policy_tracker_id', 'id')
            ->where('user_id', Auth::id());
    }

    public function bookmarks()
    {
        return $this->hasMany(BookMark::class, 'ai_policy_tracker_id', 'id');
    }
    public function getCreatedMonthAttribute()
    {
        return Carbon::parse($this->created_at)->format('M');
    }


}
