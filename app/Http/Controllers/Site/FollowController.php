<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AlertDelivery;
use App\Models\ApplicabilityProfile;
use App\Models\Follow;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Server-side follows for Pro accounts (entitlement saved.server), the input
 * to the daily alert. Records are validated against the published tables so a
 * follow can only point at something that exists.
 */
class FollowController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $follows = $user->follows()->get()->map(function (Follow $f) {
            $subject = $f->subject();

            return ['follow' => $f, 'subject' => $subject, 'title' => $subject?->short_title ?? $subject?->title ?? $subject?->name ?? $f->subject_slug, 'url' => $subject?->url()];
        });
        $lastAlert = AlertDelivery::where('user_id', $user->id)->orderByDesc('sent_on')->first();
        $profiles = ApplicabilityProfile::where('user_id', $user->id)->orderBy('name')->get();
        $seo = Seo::make('Records you follow', 'Policies, jurisdictions and obligations you follow for daily alerts.', route('following.index'), false)
            ->withBreadcrumbs([['Home', route('home')], ['Your account', route('profile.edit')], ['Following', route('following.index')]]);

        return view('site.account.following', compact('seo', 'follows', 'lastAlert', 'user', 'profiles'));
    }

    /**
     * A return target is only honoured when it is a path on this site. A value
     * starting with `//` or `/\` is a protocol-relative URL and would send the
     * reader to another host.
     */
    private static function localPath(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' && str_starts_with($value, '/') && ! in_array(substr($value, 1, 1), ['/', '\\'], true) ? $value : null;
    }

    /** Toggles a follow and returns to the record. */
    public function toggle(Request $request, string $type, string $slug): RedirectResponse
    {
        abort_unless(in_array($type, Follow::TYPES, true), 404);
        $subject = match ($type) {
            'policy' => PolicyInstrument::published()->where('slug', $slug)->first(),
            'jurisdiction' => Jurisdiction::published()->where('slug', $slug)->first(),
            'obligation' => Obligation::published()->where('slug', $slug)->first(),
        };
        abort_unless($subject, 404);
        $user = $request->user();
        $existing = Follow::where('user_id', $user->id)->where('subject_type', $type)->where('subject_slug', $slug)->first();
        if ($existing) {
            $existing->delete();

            return redirect()->to(self::localPath($request->input('return')) ?? $subject->url())->with('status', 'unfollowed');
        }
        Follow::create(['user_id' => $user->id, 'subject_type' => $type, 'subject_slug' => $slug]);

        return redirect()->to($subject->url())->with('status', 'followed');
    }
}
