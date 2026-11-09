<?php

namespace App\Services\Admin;

use App\Http\Controllers\Backend\Review\ReviewController;
use App\Models\ChannelDelivery;
use App\Models\ContributorSubmission;
use App\Models\LicenceEnquiry;
use App\Models\PolicyInstrument;
use App\Models\SocialPost;
use Illuminate\Support\Facades\Cache;

/**
 * The figures beside sidebar entries: how much is waiting on each page, so the menu
 * says where the work is before anything is opened. Each is one indexed count, cached
 * for a minute because the sidebar renders on every admin request. The layout shows a
 * figure only beside an entry the account may open, and only when it is above zero.
 */
class NavCounts
{
    public const TTL = 60;

    /** @return array<string, array{count: int, tone: string, label: string}> keyed by route name */
    public function all(): array
    {
        return Cache::remember('admin.nav-counts', self::TTL, function () {
            $days = ReviewController::staleAfter();

            return array_filter([
                'backend.review.index' => [
                    'count' => PolicyInstrument::published()->where(fn ($q) => $q->whereNull('last_verified_at')->orWhere('last_verified_at', '<', now()->subDays($days)))->count(),
                    'tone' => 'warn',
                    'label' => 'instruments unverified or past '.$days.' days',
                ],
                'backend.admin.submissions' => [
                    'count' => ContributorSubmission::where('status', 'pending_review')->count(),
                    'tone' => 'warn',
                    'label' => 'submissions waiting for review',
                ],
                'backend.admin.enquiries.index' => [
                    'count' => LicenceEnquiry::where('created_at', '>=', now()->subDays(7))->count(),
                    'tone' => 'info',
                    'label' => 'licence enquiries in the last 7 days',
                ],
                'backend.admin.social.index' => [
                    'count' => SocialPost::where('status', 'draft')->count(),
                    'tone' => 'info',
                    'label' => 'posts awaiting approval',
                ],
                'backend.admin.alerts.index' => [
                    'count' => ChannelDelivery::where('status', 'failed')->where('updated_at', '>=', now()->subDays(7))->count(),
                    'tone' => 'bad',
                    'label' => 'alert deliveries failed in the last 7 days',
                ],
            ], fn ($c) => $c['count'] > 0);
        });
    }

    /** Called after an action that changes one of the counts, so the menu does not lag. */
    public static function forget(): void
    {
        Cache::forget('admin.nav-counts');
    }
}
