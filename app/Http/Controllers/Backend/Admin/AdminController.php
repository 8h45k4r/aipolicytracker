<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Console\Commands\SyncAiidApiCommand;
use App\Http\Controllers\Controller;
use App\Mail\SubscriptionConfirmMail;
use App\Mail\TestMail;
use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\ChangeEvent;
use App\Models\ContributorSubmission;
use App\Models\Control;
use App\Models\ExternalIncident;
use App\Models\ExternalIncidentReport;
use App\Models\JobRun;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PageView;
use App\Models\PolicyInstrument;
use App\Models\ResourceDownload;
use App\Models\Subscriber;
use App\Models\Tool;
use App\Models\User;
use App\Services\ExternalData\ExternalDataset;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
            'users' => User::count(),
            'downloads_30d' => ResourceDownload::where('created_at', '>=', now()->subDays(30))->count(),
        ];
        $stale = PolicyInstrument::published()->where(fn ($q) => $q->whereNull('last_verified_at')->orWhere('last_verified_at', '<', now()->subDays(180)))->count();
        $recentSubmissions = ContributorSubmission::orderByDesc('created_at')->limit(5)->get();
        $mail = ['mailer' => config('mail.default'), 'from' => config('mail.from.address'), 'resend_key_set' => (bool) config('services.resend.key')];
        $aiid = $external->aiid();
        $stats['controls'] = Control::published()->count();
        $jobs = JobRun::latest();

        return view('backend.admin.dashboard', compact('stats', 'stale', 'recentSubmissions', 'mail', 'aiid', 'jobs'));
    }

    /** Guides & downloads: registered users, download activity and the most requested free tools. */
    public function downloads(Request $request): View
    {
        $now = now();
        $metrics = [
            'users_total' => User::count(),
            'users_today' => User::where('created_at', '>=', $now->copy()->startOfDay())->count(),
            'users_7d' => User::where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'users_30d' => User::where('created_at', '>=', $now->copy()->subDays(30))->count(),
            'verified_pct' => ($t = User::count()) ? (int) round(100 * User::whereNotNull('email_verified_at')->count() / $t) : null,
            'consent' => User::whereNotNull('marketing_consent_at')->count(),
            'downloads_today' => ResourceDownload::where('created_at', '>=', $now->copy()->startOfDay())->count(),
            'downloads_7d' => ResourceDownload::where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'downloads_30d' => ResourceDownload::where('created_at', '>=', $now->copy()->subDays(30))->count(),
            'downloads_total' => ResourceDownload::count(),
            'repeat' => ResourceDownload::selectRaw('user_id, COUNT(DISTINCT resource_slug) as n')->groupBy('user_id')->havingRaw('COUNT(DISTINCT resource_slug) > 1')->get()->count(),
        ];
        $byResource = ResourceDownload::selectRaw('resource_slug, COUNT(*) as n, COUNT(DISTINCT user_id) as users')->groupBy('resource_slug')->orderByDesc('n')->get()
            ->map(fn ($r) => ['slug' => $r->resource_slug, 'title' => Tool::where('slug', $r->resource_slug)->value('title') ?? $r->resource_slug, 'n' => $r->n, 'users' => $r->users]);
        $since = $now->copy()->subDays(30)->toDateString();
        $views = PageView::where('day', '>=', $since)->selectRaw('path, SUM(views) as n')->groupBy('path')->pluck('n', 'path');
        $funnel = [
            'library_views' => (int) ($views['/guides'] ?? 0),
            'tool_views' => (int) $views->filter(fn ($n, $p) => str_starts_with($p, '/guides/tools/') && ! str_contains($p, '/download') && ! str_contains($p, '/ready/'))->sum(),
            'gate_views' => (int) $views->filter(fn ($n, $p) => str_ends_with($p, '/download'))->sum(),
            'signups_from_tools' => User::where('signup_source', 'free-tool')->where('created_at', '>=', $since)->count(),
            'downloads' => ResourceDownload::where('created_at', '>=', $since)->count(),
            'second_downloads' => ResourceDownload::where('created_at', '>=', $since)->selectRaw('user_id, COUNT(DISTINCT resource_slug) as n')->groupBy('user_id')->havingRaw('COUNT(DISTINCT resource_slug) > 1')->get()->count(),
        ];
        $topPages = $views->sortDesc()->take(10);
        $bySource = User::selectRaw("COALESCE(signup_source, 'legacy') as source, COUNT(*) as n")->groupBy('source')->orderByDesc('n')->pluck('n', 'source');
        $recent = ResourceDownload::with(['user', 'tool'])->orderByDesc('id')->paginate(25, ['*'], 'downloads')->withQueryString();
        $users = User::withCount('resourceDownloads')->orderByDesc('id')->paginate(25, ['*'], 'users')->withQueryString();

        return view('backend.admin.downloads', compact('metrics', 'byResource', 'bySource', 'recent', 'users', 'funnel', 'topPages'));
    }

    /** Who did what in the admin: every state-changing request, newest first. */
    public function audit(Request $request): View
    {
        $entries = AdminAuditLog::with('user')->orderByDesc('id')->paginate(100)->withQueryString();

        return view('backend.admin.audit', compact('entries'));
    }

    /**
     * Cells that a spreadsheet would run as a formula are prefixed so they open
     * as text. Names, organisations and referrers are typed by readers, and an
     * export is opened in exactly the program that executes `=`, `+`, `-` and `@`.
     *
     * @param  list<mixed>  $cells
     * @return list<mixed>
     */
    private static function csvRow(array $cells): array
    {
        return array_map(function ($cell) {
            return Csv::cell($cell instanceof \DateTimeInterface ? $cell->format('Y-m-d H:i:s') : $cell);
        }, $cells);
    }

    public function downloadsExport(Request $request): StreamedResponse
    {
        // One row per download, for following up leads: who took which template, when,
        // and the organisation they gave at the time.
        if ($request->query('rows') === 'downloads') {
            return response()->streamDownload(function () {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['downloaded_at', 'tool', 'version', 'file', 'name', 'email', 'organization', 'signup_source', 'marketing_consent', 'referrer']);
                ResourceDownload::with('user')->orderByDesc('id')->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $d) {
                        fputcsv($out, self::csvRow([$d->created_at?->toDateTimeString(), $d->resource_slug, $d->version, $d->file_name, $d->user?->name, $d->user?->email, $d->user?->organization_name, $d->user?->signup_source, $d->user?->marketing_consent_at?->toDateString(), $d->referrer]));
                    }
                });
                fclose($out);
            }, 'downloads-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['user_id', 'name', 'email', 'organization', 'signed_up', 'verified', 'terms_accepted', 'marketing_consent', 'signup_source', 'downloads']);
            User::withCount('resourceDownloads')->orderBy('id')->chunk(500, function ($users) use ($out) {
                foreach ($users as $u) {
                    fputcsv($out, self::csvRow([$u->id, $u->name, $u->email, $u->organization_name, $u->created_at?->toDateString(), $u->email_verified_at?->toDateString(), $u->terms_accepted_at?->toDateString(), $u->marketing_consent_at?->toDateString(), $u->signup_source, $u->resource_downloads_count]));
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
                    fputcsv($out, self::csvRow([$s->email, implode('|', $s->topics ?? []), $s->confirmed_at, $s->unsubscribed_at, $s->last_sent_at, $s->source, $s->created_at]));
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
            'reports' => ExternalIncidentReport::count(), 'last_run' => Cache::get(SyncAiidApiCommand::LAST_RUN_KEY),
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

    /** The timetable: every job, when it runs, how its last run ended, and a button to run it now. */
    public function jobs(): View
    {
        $latest = JobRun::latest();
        $history = JobRun::with('user')->orderByDesc('started_at')->limit(40)->get();
        $scheduler = ['last_tick' => Cache::get('scheduler.last_tick')];

        return view('backend.admin.jobs', ['jobs' => JobRun::JOBS, 'latest' => $latest, 'history' => $history, 'scheduler' => $scheduler]);
    }

    public function runJob(Request $request, string $job): RedirectResponse
    {
        $meta = JobRun::JOBS[$job] ?? null;
        abort_unless($meta, 404);
        // A job that sends mail or rewrites data only runs through the confirmed route.
        if ($meta['confirm'] && ! $request->routeIs('backend.admin.jobs.run.confirmed')) {
            return redirect()->route('password.confirm')->with('url.intended', route('backend.admin.jobs'));
        }
        $run = JobRun::run($job, 'admin', $request->user()->id);
        Cache::flush();

        return redirect()->route('backend.admin.jobs')->with($run->succeeded() ? 'success' : 'error', $meta['label'].($run->succeeded() ? ' finished' : ' failed').($run->output ? ': '.Str::limit($run->output, 300) : '.'));
    }

    public function settings(): View
    {
        $values = [];
        foreach (AppSetting::KEYS as $key => $meta) {
            $current = AppSetting::get($key);
            $values[$key] = ['meta' => $meta, 'set' => $current !== null && $current !== '', 'display' => $meta['secret'] ? AppSetting::mask($current) : ($current ?? ''), 'env' => match ($key) {
                'mail_mailer' => env('MAIL_MAILER'), 'resend_key' => env('RESEND_KEY') ? 'set in environment' : null, 'mail_from_address' => env('MAIL_FROM_ADDRESS'), 'mail_from_name' => env('MAIL_FROM_NAME'), 'cron_token' => env('CRON_TOKEN') ? 'set in environment' : null,
                'billing_enabled' => env('BILLING_ENABLED') !== null ? (filter_var(env('BILLING_ENABLED'), FILTER_VALIDATE_BOOL) ? 'on' : 'off') : null, 'dodo_environment' => env('DODO_PAYMENTS_ENVIRONMENT'), 'dodo_api_key' => env('DODO_PAYMENTS_API_KEY') ? 'set in environment' : null, 'dodo_webhook_secret' => env('DODO_PAYMENTS_WEBHOOK_KEY') ? 'set in environment' : null,
                'dodo_product_pro_monthly' => env('DODO_PRODUCT_PRO_MONTHLY'), 'dodo_product_pro_yearly' => env('DODO_PRODUCT_PRO_YEARLY'),
                'contact_email' => env('CONTACT_EMAIL'), 'google_analytics_id' => env('GOOGLE_ANALYTICS_ID'), 'cloudflare_analytics_token' => env('CLOUDFLARE_ANALYTICS_TOKEN') ? 'set in environment' : null,
                'analytics_require_consent' => env('ANALYTICS_REQUIRE_CONSENT'), 'social_cards_enabled' => env('SOCIAL_CARDS_ENABLED'), 'email_domain_enforcement' => env('EMAIL_DOMAIN_ENFORCEMENT'),
                'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'), 'bing_site_verification' => env('BING_SITE_VERIFICATION'), 'x_handle' => env('SITE_X_HANDLE'), 'stale_after_days' => null, 'newsletter_url' => env('SITE_NEWSLETTER_URL'),
                default => null,
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
            'billing_enabled' => ['nullable', 'in:on,off'],
            'dodo_environment' => ['nullable', 'in:test_mode,live_mode'],
            'dodo_api_key' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_.-]{16,}$/'],
            'dodo_webhook_secret' => ['nullable', 'string', 'max:200', 'regex:/^(whsec_)?[A-Za-z0-9+\/=_-]{16,}$/'],
            'dodo_product_pro_monthly' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'dodo_product_pro_yearly' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'google_analytics_id' => ['nullable', 'string', 'max:32', 'regex:/^G-[A-Z0-9]+$/'],
            'cloudflare_analytics_token' => ['nullable', 'string', 'max:64', 'regex:/^[a-f0-9]{16,}$/'],
            'analytics_require_consent' => ['nullable', 'in:on,off'],
            'social_cards_enabled' => ['nullable', 'in:on,off'],
            'email_domain_enforcement' => ['nullable', 'in:on,off'],
            'google_site_verification' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
            'bing_site_verification' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9]+$/'],
            'x_handle' => ['nullable', 'string', 'max:32', 'regex:/^@[A-Za-z0-9_]{1,15}$/'],
            'stale_after_days' => ['nullable', 'integer', 'min:30', 'max:730'],
            'newsletter_url' => ['nullable', 'url:https', 'max:512'],
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
            Mail::to($to)->send(new TestMail((string) config('mail.default')));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['to' => 'Send failed: '.mb_substr($e->getMessage(), 0, 200)]);
        }

        return back()->with('success', "Test message sent to {$to} via ".config('mail.default').'.');
    }
}
