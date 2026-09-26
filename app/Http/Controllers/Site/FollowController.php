<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AlertChannel;
use App\Models\AlertDelivery;
use App\Models\ApplicabilityProfile;
use App\Models\Follow;
use App\Models\Jurisdiction;
use App\Models\TaxonomyTerm;
use App\Services\Alerts\WatchTypes;
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
            $resolved = WatchTypes::resolve($f->subject_type, $f->subject_slug, (array) ($f->params ?? []));

            return ['follow' => $f, 'title' => $f->label ?? $resolved['label'] ?? $f->subject_slug, 'url' => $resolved['url'] ?? null];
        });
        $channels = AlertChannel::where('user_id', $user->id)->orderBy('kind')->get();
        $emailOn = AlertChannel::where('user_id', $user->id)->where('kind', 'email')->value('enabled') ?? true;
        $options = ['sectors' => TaxonomyTerm::where('taxonomy', 'sector')->orderBy('name')->pluck('name', 'slug')->all(), 'use_cases' => TaxonomyTerm::where('taxonomy', 'use_case')->orderBy('name')->pluck('name', 'slug')->all(), 'frameworks' => collect(config('frameworks'))->mapWithKeys(fn ($f, $k) => [$f['slug'] ?? $k => $f['name'] ?? $k])->all(), 'change_types' => WatchTypes::CHANGE_TYPES, 'jurisdictions' => Jurisdiction::published()->orderBy('name')->pluck('name', 'slug')->all()];
        $lastAlert = AlertDelivery::where('user_id', $user->id)->orderByDesc('sent_on')->first();
        $profiles = ApplicabilityProfile::where('user_id', $user->id)->orderBy('name')->get();
        $seo = Seo::make('Records you follow', 'Policies, jurisdictions and obligations you follow for daily alerts.', route('following.index'), false)
            ->withBreadcrumbs([['Home', route('home')], ['Your account', route('profile.edit')], ['Following', route('following.index')]]);

        return view('site.account.following', compact('seo', 'follows', 'lastAlert', 'user', 'profiles', 'channels', 'emailOn', 'options'));
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
        abort_unless(in_array($type, WatchTypes::all(), true), 404);
        // The select-and-watch forms post to /follow/<type>/- with the choice in a field.
        if ($slug === '-') {
            $slug = (string) $request->input('slug', '');
            abort_unless(preg_match('/^[A-Za-z0-9._-]{1,160}$/', $slug) === 1, 404);
        }
        $resolved = WatchTypes::resolve($type, $slug, (array) $request->input('params', []));
        abort_unless($resolved, 404);
        $user = $request->user();
        $existing = Follow::where('user_id', $user->id)->where('subject_type', $type)->where('subject_slug', $resolved['slug'])->first();
        $back = in_array($type, Follow::TYPES, true) ? $resolved['url'] : route('following.index');
        if ($existing) {
            $existing->delete();

            return redirect()->to(self::localPath($request->input('return')) ?? $back)->with('status', 'unfollowed');
        }
        Follow::create(['user_id' => $user->id, 'subject_type' => $type, 'subject_slug' => $resolved['slug'], 'label' => $resolved['label'], 'params' => $resolved['params']]);

        return redirect()->to(self::localPath($request->input('return')) ?? $back)->with('status', 'followed');
    }
}
