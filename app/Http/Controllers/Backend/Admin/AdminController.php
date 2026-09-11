<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SubscriptionConfirmMail;
use App\Models\AppSetting;
use App\Models\ChangeEvent;
use App\Models\ContributorSubmission;
use App\Models\ExternalIncident;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\Subscriber;
use App\Services\ExternalData\ExternalDataset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function dashboard(ExternalDataset $external): View
    {
        $stats = [
            'jurisdictions' => Jurisdiction::published()->count(),
            'policies' => PolicyInstrument::published()->count(),
            'policies_verified' => PolicyInstrument::published()->where('review_status', 'verified')->count(),
            'obligations' => Obligation::published()->count(),
            'changes_30d' => ChangeEvent::published()->where('occurred_on', '>=', now()->subDays(30)->toDateString())->count(),
            'submissions_pending' => ContributorSubmission::where('status', 'pending_review')->count(),
            'subscribers_active' => Subscriber::active()->count(),
            'subscribers_unconfirmed' => Subscriber::whereNull('confirmed_at')->whereNull('unsubscribed_at')->count(),
            'users' => \App\Models\User::count(),
            'downloads_30d' => \App\Models\ResourceDownload::where('created_at', '>=', now()->subDays(30))->count(),
        ];
        $stale = PolicyInstrument::published()->where(fn ($q) => $q->whereNull('last_verified_at')->orWhere('last_verified_at', '<', now()->subDays(180)))->count();
        $recentSubmissions = ContributorSubmission::orderByDesc('created_at')->limit(5)->get();
        $mail = ['mailer' => config('mail.default'), 'from' => config('mail.from.address'), 'resend_key_set' => (bool) config('services.resend.key')];
        $aiid = $external->aiid();

        return view('backend.admin.dashboard', compact('stats', 'stale', 'recentSubmissions', 'mail', 'aiid'));
    }

    /** Guides & downloads: registered users, download activity and the most requested free tools. */
    public function downloads(Request $request): View
    {
        $now = now();
        $metrics = [
            'users_total' => \App\Models\User::count(),
            'users_today' => \App\Models\User::where('created_at', '>=', $now->copy()->startOfDay())->count(),
            'users_7d' => \App\Models\User::where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'users_30d' => \App\Models\User::where('created_at', '>=', $now->copy()->subDays(30))->count(),
            'verified_pct' => ($t = \App\Models\User::count()) ? (int) round(100 * \App\Models\User::whereNotNull('email_verified_at')->count() / $t) : null,
            'consent' => \App\Models\User::whereNotNull('marketing_consent_at')->count(),
            'downloads_today' => \App\Models\ResourceDownload::where('created_at', '>=', $now->copy()->startOfDay())->count(),
            'downloads_7d' => \App\Models\ResourceDownload::where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'downloads_30d' => \App\Models\ResourceDownload::where('created_at', '>=', $now->copy()->subDays(30))->count(),
            'downloads_total' => \App\Models\ResourceDownload::count(),
            'repeat' => \App\Models\ResourceDownload::selectRaw('user_id, COUNT(DISTINCT resource_slug) as n')->groupBy('user_id')->havingRaw('COUNT(DISTINCT resource_slug) > 1')->get()->count(),
        ];
        $byResource = \App\Models\ResourceDownload::selectRaw('resource_slug, COUNT(*) as n, COUNT(DISTINCT user_id) as users')->groupBy('resource_slug')->orderByDesc('n')->get()
            ->map(fn ($r) => ['slug' => $r->resource_slug, 'title' => \App\Models\Tool::where('slug', $r->resource_slug)->value('title') ?? $r->resource_slug, 'n' => $r->n, 'users' => $r->users]);
        $since = $now->copy()->subDays(30)->toDateString();
        $views = \App\Models\PageView::where('day', '>=', $since)->selectRaw('path, SUM(views) as n')->groupBy('path')->pluck('n', 'path');
        $funnel = [
            'library_views' => (int) ($views['/guides'] ?? 0),
            'tool_views' => (int) $views->filter(fn ($n, $p) => str_starts_with($p, '/guides/tools/') && ! str_contains($p, '/download') && ! str_contains($p, '/ready/'))->sum(),
            'gate_views' => (int) $views->filter(fn ($n, $p) => str_ends_with($p, '/download'))->sum(),
            'signups_from_tools' => \App\Models\User::where('signup_source', 'free-tool')->where('created_at', '>=', $since)->count(),
            'downloads' => \App\Models\ResourceDownload::where('created_at', '>=', $since)->count(),
            'second_downloads' => \App\Models\ResourceDownload::where('created_at', '>=', $since)->selectRaw('user_id, COUNT(DISTINCT resource_slug) as n')->groupBy('user_id')->havingRaw('COUNT(DISTINCT resource_slug) > 1')->get()->count(),
        ];
        $topPages = $views->sortDesc()->take(10);
        $bySource = \App\Models\User::selectRaw("COALESCE(signup_source, 'legacy') as source, COUNT(*) as n")->groupBy('source')->orderByDesc('n')->pluck('n', 'source');
        $recent = \App\Models\ResourceDownload::with(['user', 'tool'])->orderByDesc('id')->paginate(25, ['*'], 'downloads')->withQueryString();
        $users = \App\Models\User::withCount('resourceDownloads')->orderByDesc('id')->paginate(25, ['*'], 'users')->withQueryString();

        return view('backend.admin.downloads', compact('metrics', 'byResource', 'bySource', 'recent', 'users', 'funnel', 'topPages'));
    }

    public function downloadsExport(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['user_id', 'name', 'email', 'organization', 'signed_up', 'verified', 'terms_accepted', 'marketing_consent', 'signup_source', 'downloads']);
            \App\Models\User::withCount('resourceDownloads')->orderBy('id')->chunk(500, function ($users) use ($out) {
                foreach ($users as $u) {
                    fputcsv($out, [$u->id, $u->name, $u->email, $u->organization_name, $u->created_at?->toDateString(), $u->email_verified_at?->toDateString(), $u->terms_accepted_at?->toDateString(), $u->marketing_consent_at?->toDateString(), $u->signup_source, $u->resource_downloads_count]);
                }
            });
            fclose($out);
        }, 'users-and-downloads-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function submissions(Request $request): View
    {
        $type = $request->query('type');
        $status = $request->query('status');
        $q = ContributorSubmission::with('decisions.reviewer')->orderByDesc('created_at');
        if ($type && array_key_exists($type, ContributorSubmission::TYPES)) {
            $q->where('type', $type);
        }
        if ($status) {
            $q->where('status', $status);
        }
        $submissions = $q->paginate(25)->withQueryString();
        $byType = ContributorSubmission::selectRaw('type, COUNT(*) as n')->groupBy('type')->pluck('n', 'type');
        $byStatus = ContributorSubmission::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('backend.admin.submissions', compact('submissions', 'byType', 'byStatus', 'type', 'status'));
    }

    public function subscribers(Request $request): View
    {
        $state = $request->query('state', 'active');
        $q = Subscriber::orderByDesc('created_at');
        match ($state) {
            'unconfirmed' => $q->whereNull('confirmed_at')->whereNull('unsubscribed_at'),
            'unsubscribed' => $q->whereNotNull('unsubscribed_at'),
            'all' => $q,
            default => $q->active(),
        };
        $subscribers = $q->paginate(50)->withQueryString();
        $counts = ['active' => Subscriber::active()->count(), 'unconfirmed' => Subscriber::whereNull('confirmed_at')->whereNull('unsubscribed_at')->count(), 'unsubscribed' => Subscriber::whereNotNull('unsubscribed_at')->count(), 'all' => Subscriber::count()];

        return view('backend.admin.subscribers', compact('subscribers', 'counts', 'state'));
    }

    public function subscribersExport(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'topics', 'confirmed_at', 'unsubscribed_at', 'last_sent_at', 'source', 'created_at']);
            Subscriber::orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $s) {
                    fputcsv($out, [$s->email, implode('|', $s->topics ?? []), $s->confirmed_at, $s->unsubscribed_at, $s->last_sent_at, $s->source, $s->created_at]);
                }
            });
            fclose($out);
        }, 'subscribers-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function subscriberResend(Subscriber $subscriber): RedirectResponse
    {
        Mail::to($subscriber->email)->send(new SubscriptionConfirmMail($subscriber));

        return back()->with('success', "Confirmation email re-sent to {$subscriber->email}.");
    }

    public function subscriberDelete(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('success', 'Subscriber removed.');
    }

    public function external(ExternalDataset $external): View
    {
        $live = [
            'rows' => ExternalIncident::count(), 'synced_rows' => ExternalIncident::whereNotNull('synced_at')->count(), 'synced_at' => ExternalIncident::max('synced_at'), 'latest_id' => ExternalIncident::max('incident_id'),
            'reports' => \App\Models\ExternalIncidentReport::count(), 'last_run' => \Illuminate\Support\Facades\Cache::get(\App\Console\Commands\SyncAiidApiCommand::LAST_RUN_KEY),
        ];

        return view('backend.admin.external', ['aiid' => $external->aiid(), 'mit' => $external->mitRisk(), 'live' => $live]);
    }

    /** Runs one incremental pull from the AI Incident Database API (same command as the cron trigger). */
    public function externalSync(): RedirectResponse
    {
        @set_time_limit(280);
        $code = Artisan::call('external:sync-aiid-api', ['--max' => 300]);
        $out = trim(Artisan::output());

        return back()->with($code === 0 ? 'success' : 'error', $out ?: ($code === 0 ? 'Sync finished.' : 'Sync failed.'));
    }

    public function settings(): View
    {
        $values = [];
        foreach (AppSetting::KEYS as $key => $meta) {
            $current = AppSetting::get($key);
            $values[$key] = ['meta' => $meta, 'set' => $current !== null && $current !== '', 'display' => $meta['secret'] ? AppSetting::mask($current) : ($current ?? ''), 'env' => match ($key) {
                'mail_mailer' => env('MAIL_MAILER'), 'resend_key' => env('RESEND_KEY') ? 'set in environment' : null, 'mail_from_address' => env('MAIL_FROM_ADDRESS'), 'mail_from_name' => env('MAIL_FROM_NAME'), 'cron_token' => env('CRON_TOKEN') ? 'set in environment' : null, default => null,
            }];
        }

        return view('backend.admin.settings', ['values' => $values, 'effective' => ['mailer' => config('mail.default'), 'from' => config('mail.from.address').' ('.config('mail.from.name').')', 'resend' => (bool) config('services.resend.key')]]);
    }

    public function settingsSave(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_mailer' => ['nullable', 'in:log,resend,smtp,array'],
            'resend_key' => ['nullable', 'string', 'max:200', 'regex:/^re_[A-Za-z0-9_]+$/'],
            'mail_from_address' => ['nullable', 'email', 'max:190'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
            'cron_token' => ['nullable', 'string', 'min:24', 'max:128'],
            'clear' => ['nullable', 'array'],
            'clear.*' => ['in:'.implode(',', array_keys(AppSetting::KEYS))],
        ]);
        foreach (AppSetting::KEYS as $key => $meta) {
            if (in_array($key, $data['clear'] ?? [], true)) {
                AppSetting::put($key, null, $request->user()->id);

                continue;
            }
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                AppSetting::put($key, $data[$key], $request->user()->id);
            }
        }

        return back()->with('success', 'Settings saved. Secrets are stored encrypted and only shown masked.');
    }

    public function settingsTestMail(Request $request): RedirectResponse
    {
        $to = $request->validate(['to' => ['required', 'email']])['to'];
        try {
            Mail::to($to)->send(new \App\Mail\TestMail((string) config('mail.default')));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['to' => 'Send failed: '.mb_substr($e->getMessage(), 0, 200)]);
        }

        return back()->with('success', "Test message sent to {$to} via ".config('mail.default').'.');
    }
}
