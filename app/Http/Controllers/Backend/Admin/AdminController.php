<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Console\Commands\SyncAiidApiCommand;
use App\Http\Controllers\Backend\Review\ReviewController;
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
use App\Models\Follow;
use App\Models\JobRun;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PageView;
use App\Models\PolicyInstrument;
use App\Models\ResourceDownload;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\TemplateDownloadRequest;
use App\Models\Tool;
use App\Models\User;
use App\Services\Admin\Attention;
use App\Services\Billing\BillingConfig;
use App\Services\Billing\Entitlements;
use App\Services\ExternalData\ExternalDataset;
use App\Services\Security\Turnstile;
use App\Services\Verification\IndependentChecks;
use App\Support\Admin\BulkAction;
use App\Support\Admin\CsvStream;
use App\Support\Admin\ListFilters;
use App\Support\Admin\SubmissionQuery;
use App\Support\ContentCache;
use App\Support\DatasetCitation;
use App\Support\FundingDisclosure;
use Illuminate\Database\Eloquent\Builder;
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
    public function dashboard(Request $request, ExternalDataset $external, Attention $attentionService, Turnstile $turnstileService): View
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
        $stale = PolicyInstrument::published()->where(fn ($q) => $q->whereNull('last_verified_at')->orWhere('last_verified_at', '<', now()->subDays(ReviewController::staleAfter())))->count();
        $recentSubmissions = ContributorSubmission::orderByDesc('created_at')->limit(5)->get();
        $mail = ['mailer' => config('mail.default'), 'from' => config('mail.from.address'), 'resend_key_set' => (bool) config('services.resend.key')];
        $aiid = $external->aiid();
        $stats['controls'] = Control::published()->count();
        $jobs = JobRun::latest();
        $attention = $attentionService->items($request->user(), $jobs);
        $turnstile = $turnstileService->configured();
        $user = $request->user();
        // Thirty-day lines, only for what this account may open.
        $trends = array_filter([
            'submissions' => $user->can('submissions.decide') ? $this->dailyCounts(ContributorSubmission::query(), 'created_at', 30) : null,
            'subscribers' => $user->can('audience.view') ? $this->dailyCounts(Subscriber::query(), 'created_at', 30) : null,
            'requests' => $user->can('audience.view') ? $this->dailyCounts(TemplateDownloadRequest::query(), 'created_at', 30) : null,
            'changes' => $this->dailyCounts(ChangeEvent::published(), 'created_at', 30),
        ], fn ($t) => $t !== null);
        $stats['requests_30d'] = TemplateDownloadRequest::where('created_at', '>=', now()->subDays(30))->count();
        $stats['watches'] = Follow::count();
        $stats['watchers'] = Follow::distinct()->count('user_id');
        $stats['pro'] = Subscription::whereNotNull('plan_key')->get()->filter(fn ($s) => app(Entitlements::class)->covers($s))->count();
        $stats['selling'] = app(BillingConfig::class)->enabled();
        $checks = app(IndependentChecks::class)->summary();
        $stats['double_checked'] = $checks['n'];
        $stats['verified_total'] = $checks['verified'];

        return view('backend.admin.dashboard', compact('stats', 'stale', 'recentSubmissions', 'mail', 'aiid', 'jobs', 'attention', 'turnstile', 'trends'));
    }

    /** The four views of the downloads page; each has its own filters, sort and export. */
    private const DOWNLOAD_VIEWS = ['overview', 'requests', 'downloads', 'users'];

    private const DOWNLOAD_SORTS = [
        'requests' => ['requested' => 'created_at', 'template' => 'template_slug', 'company' => 'company', 'downloads' => 'downloads'],
        'downloads' => ['when' => 'id', 'resource' => 'resource_slug', 'version' => 'version'],
        'users' => ['joined' => 'id', 'name' => 'name', 'email' => 'email', 'downloads' => 'resource_downloads_count'],
    ];

    /** Guides & downloads: an overview, then template requests, downloads and registered users, each filterable and exportable. */
    public function downloads(Request $request): View
    {
        $view = in_array($request->query('view'), self::DOWNLOAD_VIEWS, true) ? (string) $request->query('view') : 'overview';
        $data = ['view' => $view, 'counts' => [
            'requests' => TemplateDownloadRequest::count(),
            'downloads' => ResourceDownload::count(),
            'users' => User::count(),
        ]];

        if ($view === 'overview') {
            $now = now();
            $data['metrics'] = [
                'users_30d' => User::where('created_at', '>=', $now->copy()->subDays(30))->count(),
                'verified_pct' => ($t = User::count()) ? (int) round(100 * User::whereNotNull('email_verified_at')->count() / $t) : null,
                'consent' => User::whereNotNull('marketing_consent_at')->count(),
                'downloads_30d' => ResourceDownload::where('created_at', '>=', $now->copy()->subDays(30))->count(),
                'requests_30d' => TemplateDownloadRequest::where('created_at', '>=', $now->copy()->subDays(30))->count(),
                'requests_downloaded' => TemplateDownloadRequest::whereNotNull('first_downloaded_at')->count(),
                'requests_opted_in' => TemplateDownloadRequest::whereNotNull('marketing_consent_at')->count(),
            ];
            $data['trends'] = [
                'requests' => $this->dailyCounts(TemplateDownloadRequest::query(), 'created_at', 30),
                'downloads' => $this->dailyCounts(ResourceDownload::query(), 'created_at', 30),
                'users' => $this->dailyCounts(User::query(), 'created_at', 30),
            ];
            $data['byTemplate'] = TemplateDownloadRequest::selectRaw('template_slug, COUNT(*) as n, SUM(CASE WHEN first_downloaded_at IS NOT NULL THEN 1 ELSE 0 END) as used')
                ->groupBy('template_slug')->orderByDesc('n')->limit(10)->get();
            $titles = Tool::pluck('title', 'slug');
            $data['byResource'] = ResourceDownload::selectRaw('resource_slug, COUNT(*) as n, COUNT(DISTINCT user_id) as users')->groupBy('resource_slug')->orderByDesc('n')->limit(10)->get()
                ->map(fn ($r) => ['slug' => $r->resource_slug, 'title' => $titles[$r->resource_slug] ?? $r->resource_slug, 'n' => $r->n, 'users' => $r->users]);
            $data['bySource'] = User::selectRaw("COALESCE(signup_source, 'legacy') as source, COUNT(*) as n")->groupBy('source')->orderByDesc('n')->pluck('n', 'source');
            $since = $now->copy()->subDays(30)->toDateString();
            $views = PageView::where('day', '>=', $since)->selectRaw('path, SUM(views) as n')->groupBy('path')->pluck('n', 'path');
            $data['funnel'] = [
                'library_views' => (int) ($views['/guides'] ?? 0),
                'tool_views' => (int) $views->filter(fn ($n, $p) => str_starts_with($p, '/guides/tools/') && ! str_contains($p, '/download') && ! str_contains($p, '/ready/'))->sum(),
                'gate_views' => (int) $views->filter(fn ($n, $p) => str_ends_with($p, '/download'))->sum(),
                'signups_from_tools' => User::where('signup_source', 'free-tool')->where('created_at', '>=', $since)->count(),
                'downloads' => ResourceDownload::where('created_at', '>=', $since)->count(),
                'second_downloads' => ResourceDownload::where('created_at', '>=', $since)->selectRaw('user_id, COUNT(DISTINCT resource_slug) as n')->groupBy('user_id')->havingRaw('COUNT(DISTINCT resource_slug) > 1')->get()->count(),
            ];
            $data['topPages'] = $views->sortDesc()->take(10);
            $data['turnstile'] = app(Turnstile::class)->configured();
        } else {
            $filters = ListFilters::from($request, self::DOWNLOAD_SORTS[$view]);
            $data['filters'] = $filters;
            $data['rows'] = $this->downloadsQuery($view, $request, $filters)->paginate(25)->withQueryString();
            $data['templates'] = $view === 'requests' ? TemplateDownloadRequest::distinct()->orderBy('template_slug')->pluck('template_slug') : collect();
            $data['resources'] = $view === 'downloads' ? ResourceDownload::distinct()->orderBy('resource_slug')->pluck('resource_slug') : collect();
            $data['sources'] = $view === 'users' ? User::whereNotNull('signup_source')->distinct()->orderBy('signup_source')->pluck('signup_source') : collect();
        }

        return view('backend.admin.downloads', $data);
    }

    private function downloadsQuery(string $view, Request $request, ListFilters $filters): Builder
    {
        $yes = fn (string $key) => $request->query($key) === 'yes';
        $no = fn (string $key) => $request->query($key) === 'no';

        $query = match ($view) {
            'requests' => TemplateDownloadRequest::query()
                ->when($request->filled('template'), fn ($q) => $q->where('template_slug', (string) $request->query('template')))
                ->when($yes('used'), fn ($q) => $q->whereNotNull('first_downloaded_at'))->when($no('used'), fn ($q) => $q->whereNull('first_downloaded_at'))
                ->when($yes('updates'), fn ($q) => $q->whereNotNull('marketing_consent_at'))->when($no('updates'), fn ($q) => $q->whereNull('marketing_consent_at')),
            'downloads' => ResourceDownload::query()->with(['user', 'tool'])
                ->when($request->filled('resource'), fn ($q) => $q->where('resource_slug', (string) $request->query('resource')))
                ->when($request->filled('user'), fn ($q) => $q->where('user_id', (int) $request->query('user'))),
            default => User::query()->withCount('resourceDownloads')
                ->when($request->filled('source'), fn ($q) => $request->query('source') === 'legacy' ? $q->whereNull('signup_source') : $q->where('signup_source', (string) $request->query('source')))
                ->when($yes('verified'), fn ($q) => $q->whereNotNull('email_verified_at'))->when($no('verified'), fn ($q) => $q->whereNull('email_verified_at'))
                ->when($yes('updates'), fn ($q) => $q->whereNotNull('marketing_consent_at'))->when($no('updates'), fn ($q) => $q->whereNull('marketing_consent_at')),
        };
        match ($view) {
            'requests' => $filters->search($query, ['name', 'email', 'company', 'job_title', 'country']),
            'downloads' => $query->when($filters->q !== '', fn ($q) => $q->where(fn ($w) => $filters->search($w, ['resource_slug', 'file_name', 'referrer'])
                ->orWhereHas('user', fn ($u) => $filters->search($u, ['name', 'email', 'organization_name'])))),
            default => $filters->search($query, ['name', 'email', 'organization_name']),
        };
        $filters->dateRange($query, ($view === 'users' ? 'users.' : '').'created_at');

        return $filters->order($query);
    }

    /** Who did what in the admin: every state-changing request, filterable by person, action, outcome and date. */
    public function audit(Request $request): View
    {
        $filters = ListFilters::from($request, ['when' => 'id', 'who' => 'user_email', 'action' => 'route_name', 'status' => 'status']);
        $entries = $this->auditQuery($request, $filters)->with('user')->paginate(50)->withQueryString();

        // Whole days, from the same date the "last 7 days" links filter from, so a figure and
        // the rows behind it always agree.
        $since = now()->subDays(6)->startOfDay();
        $summary = [
            'week' => AdminAuditLog::where('created_at', '>=', $since)->count(),
            'failed' => AdminAuditLog::where('created_at', '>=', $since)->where('status', '>=', 400)->count(),
            'people' => AdminAuditLog::where('created_at', '>=', $since)->distinct()->count('user_email'),
            'trend' => $this->dailyCounts(AdminAuditLog::query(), 'created_at', 14),
        ];
        $people = AdminAuditLog::whereNotNull('user_email')->distinct()->orderBy('user_email')->pluck('user_email');
        $actions = AdminAuditLog::whereNotNull('route_name')->distinct()->orderBy('route_name')->pluck('route_name');

        return view('backend.admin.audit', compact('entries', 'filters', 'summary', 'people', 'actions'));
    }

    public function auditExport(Request $request): StreamedResponse
    {
        $filters = ListFilters::from($request, ['when' => 'id', 'who' => 'user_email', 'action' => 'route_name', 'status' => 'status']);

        return CsvStream::from($this->auditQuery($request, $filters), 'admin-audit-log',
            ['at', 'user', 'method', 'action', 'path', 'record', 'status'],
            fn (AdminAuditLog $e) => [$e->created_at, $e->user_email, $e->method, $e->route_name, $e->path, $e->route_params ? collect($e->route_params)->map(fn ($v, $k) => $k.'='.$v)->implode(' ') : null, $e->status]);
    }

    private function auditQuery(Request $request, ListFilters $filters): Builder
    {
        $query = AdminAuditLog::query();
        $filters->search($query, ['path', 'route_name', 'user_email']);
        $filters->dateRange($query, 'created_at');
        $query->when($request->filled('user'), fn ($q) => $q->where('user_email', (string) $request->query('user')))
            ->when($request->filled('action'), fn ($q) => $q->where('route_name', (string) $request->query('action')))
            ->when(in_array($request->query('method'), ['POST', 'PUT', 'PATCH', 'DELETE'], true), fn ($q) => $q->where('method', $request->query('method')))
            ->when($request->query('result') === 'failed', fn ($q) => $q->where('status', '>=', 400))
            ->when($request->query('result') === 'ok', fn ($q) => $q->where('status', '<', 400));

        return $filters->order($query);
    }

    /**
     * Counts per day for the last $days days, oldest first, zero-filled, for a trend line.
     *
     * @return list<int>
     */
    private function dailyCounts(Builder $query, string $column, int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $rows = (clone $query)->where($column, '>=', $start)->get([$column])
            ->groupBy(fn ($m) => $m->{$column}->toDateString())->map->count();

        return collect(range(0, $days - 1))->map(fn ($i) => (int) ($rows[$start->copy()->addDays($i)->toDateString()] ?? 0))->all();
    }

    public function downloadsExport(Request $request): StreamedResponse
    {
        // `rows` is the older name for the view and is still accepted for saved links.
        $view = match ($request->query('view', $request->query('rows'))) {
            'requests', 'template-requests' => 'requests',
            'downloads' => 'downloads',
            default => 'users',
        };
        $query = $this->downloadsQuery($view, $request, ListFilters::from($request, self::DOWNLOAD_SORTS[$view]));

        return match ($view) {
            'requests' => CsvStream::from($query, 'template-requests',
                ['requested_at', 'template', 'name', 'email', 'company', 'job_title', 'country', 'updates_opt_in', 'emailed_at', 'first_downloaded_at', 'downloads'],
                fn (TemplateDownloadRequest $r) => [$r->created_at, $r->template_slug, $r->name, $r->email, $r->company, $r->job_title, $r->country, $r->marketing_consent_at, $r->emailed_at, $r->first_downloaded_at, $r->downloads]),
            // One row per download, for following up leads: who took which tool, when, and
            // the organisation they gave at the time.
            'downloads' => CsvStream::from($query, 'downloads',
                ['downloaded_at', 'tool', 'version', 'file', 'name', 'email', 'organization', 'signup_source', 'marketing_consent', 'referrer'],
                fn (ResourceDownload $d) => [$d->created_at, $d->resource_slug, $d->version, $d->file_name, $d->user?->name, $d->user?->email, $d->user?->organization_name, $d->user?->signup_source, $d->user?->marketing_consent_at, $d->referrer]),
            default => CsvStream::from($query, 'users-and-downloads',
                ['user_id', 'name', 'email', 'organization', 'signed_up', 'verified', 'terms_accepted', 'marketing_consent', 'signup_source', 'downloads'],
                fn (User $u) => [$u->id, $u->name, $u->email, $u->organization_name, $u->created_at, $u->email_verified_at, $u->terms_accepted_at, $u->marketing_consent_at, $u->signup_source, $u->resource_downloads_count]),
        };
    }

    /** Every contribution, by type and status, searchable and dated, with an export of what is filtered. */
    public function submissions(Request $request): View
    {
        $filters = SubmissionQuery::filters($request);
        [$type, $status] = SubmissionQuery::scope($request);
        $submissions = SubmissionQuery::query($request, $filters)->with('decisions.reviewer')->paginate(25)->withQueryString();
        $byType = ContributorSubmission::selectRaw('type, COUNT(*) as n')->groupBy('type')->pluck('n', 'type');
        $byStatus = ContributorSubmission::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $trend = $this->dailyCounts(ContributorSubmission::query(), 'created_at', 30);

        return view('backend.admin.submissions', compact('submissions', 'byType', 'byStatus', 'type', 'status', 'filters', 'trend'));
    }

    public function submissionsExport(Request $request): StreamedResponse
    {
        return CsvStream::from(SubmissionQuery::query($request), 'submissions',
            ['received_at', 'type', 'status', 'summary', 'subject_type', 'subject_slug', 'proposed_source_url', 'submitter_name', 'submitter_email', 'submitter_affiliation', 'source_page', 'details'],
            fn (ContributorSubmission $s) => [$s->created_at, ContributorSubmission::TYPES[$s->type] ?? $s->type, $s->status, $s->summary, $s->subject_type, $s->subject_slug, $s->proposed_source_url, $s->submitter_name, $s->submitter_email, $s->submitter_affiliation, $s->source_page, $s->details]);
    }

    private const SUBSCRIBER_SORTS = ['joined' => 'created_at', 'email' => 'email', 'confirmed' => 'confirmed_at', 'sent' => 'last_sent_at'];

    public function subscribers(Request $request): View
    {
        $filters = ListFilters::from($request, self::SUBSCRIBER_SORTS);
        $state = $this->subscriberState($request);
        $subscribers = $this->subscriberQuery($request, $filters)->paginate(50)->withQueryString();
        $counts = ['active' => Subscriber::active()->count(), 'unconfirmed' => Subscriber::whereNull('confirmed_at')->whereNull('unsubscribed_at')->count(), 'unsubscribed' => Subscriber::whereNotNull('unsubscribed_at')->count(), 'all' => Subscriber::count()];
        $sources = Subscriber::whereNotNull('source')->distinct()->orderBy('source')->pluck('source');
        $trend = $this->dailyCounts(Subscriber::query(), 'created_at', 30);
        $confirmRate = ($counts['all'] - $counts['unsubscribed']) > 0 ? (int) round(100 * $counts['active'] / ($counts['all'] - $counts['unsubscribed'])) : null;

        return view('backend.admin.subscribers', compact('subscribers', 'counts', 'state', 'filters', 'sources', 'trend', 'confirmRate'));
    }

    public function subscribersExport(Request $request): StreamedResponse
    {
        return CsvStream::from($this->subscriberQuery($request, ListFilters::from($request, self::SUBSCRIBER_SORTS)), 'subscribers',
            ['email', 'topics', 'confirmed_at', 'unsubscribed_at', 'last_sent_at', 'source', 'has_account', 'created_at'],
            fn (Subscriber $s) => [$s->email, implode('|', $s->topics ?? []), $s->confirmed_at, $s->unsubscribed_at, $s->last_sent_at, $s->source, $s->account_id !== null, $s->created_at]);
    }

    private function subscriberState(Request $request): string
    {
        return in_array($request->query('state'), ['active', 'unconfirmed', 'unsubscribed', 'all'], true) ? (string) $request->query('state') : 'active';
    }

    private function subscriberQuery(Request $request, ListFilters $filters): Builder
    {
        $query = Subscriber::query();
        match ($this->subscriberState($request)) {
            'unconfirmed' => $query->whereNull('confirmed_at')->whereNull('unsubscribed_at'),
            'unsubscribed' => $query->whereNotNull('unsubscribed_at'),
            'all' => $query,
            default => $query->active(),
        };
        $topic = (string) $request->query('topic', '');
        $source = (string) $request->query('source', '');
        $query->when(preg_match('/^[a-z0-9-]{1,64}$/', $topic), fn ($q) => $q->whereJsonContains('topics', $topic))
            ->when($source === '(none)', fn ($q) => $q->whereNull('source'))
            ->when($source !== '' && $source !== '(none)', fn ($q) => $q->where('source', $source));
        // The verified account behind the address, if any: shown as a badge and exported.
        // Subscriber addresses are stored lower-cased; account addresses may not be.
        $account = fn () => User::query()->select('users.id')->whereNotNull('users.email_verified_at')->whereRaw('LOWER(users.email) = subscribers.email')->limit(1);
        $query->addSelect(['account_id' => $account()]);
        match ($request->query('account')) {
            'yes' => $query->whereExists($account()),
            'no' => $query->whereNotExists($account()),
            default => null,
        };
        $filters->search($query, ['email']);
        $filters->dateRange($query, 'created_at');

        return $filters->order($query);
    }

    public function subscriberResend(Subscriber $subscriber): RedirectResponse
    {
        Mail::to($subscriber->email)->send(new SubscriptionConfirmMail($subscriber));

        return back()->with('success', "Confirmation email re-sent to {$subscriber->email}.");
    }

    public function subscriberDelete(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return BulkAction::back('subscribers-list')->with('success', 'Subscriber removed.');
    }

    /** The most subscribers one bulk action may touch. */
    private const SUBSCRIBER_BULK_LIMIT = 1000;

    /** Re-sends the confirmation to every selected address that is still waiting for one. */
    public function subscribersResendMany(Request $request): RedirectResponse
    {
        [$ids, $total] = $this->subscriberSelection($request);
        $waiting = Subscriber::whereIn('id', $ids)->whereNull('confirmed_at')->whereNull('unsubscribed_at')->get();
        foreach ($waiting as $subscriber) {
            Mail::to($subscriber->email)->send(new SubscriptionConfirmMail($subscriber));
        }
        $skipped = count($ids) - $waiting->count();

        return BulkAction::back('subscribers-list')->with('success', 'Confirmation re-sent to '.$waiting->count().' '.Str::plural('address', $waiting->count()).'.'
            .($skipped ? ' '.$skipped.' already confirmed or unsubscribed, not sent.' : '')
            .BulkAction::capNote(count($ids), $total, self::SUBSCRIBER_BULK_LIMIT));
    }

    public function subscribersDeleteMany(Request $request): RedirectResponse
    {
        [$ids, $total] = $this->subscriberSelection($request);
        $n = Subscriber::whereIn('id', $ids)->delete();

        return BulkAction::back('subscribers-list')->with('success', $n.' '.Str::plural('subscriber', $n).' removed.'.BulkAction::capNote(count($ids), $total, self::SUBSCRIBER_BULK_LIMIT));
    }

    /**
     * The ticked ids, or, with scope=filtered, every subscriber the list's filters match
     * (read from the query string the bulk form posts to), up to the cap.
     *
     * @return array{0: list<int>, 1: int} the ids and how many the selection matched in all
     */
    private function subscriberSelection(Request $request): array
    {
        $data = $request->validate([
            'scope' => ['nullable', 'in:filtered'],
            'ids' => ['required_without:scope', 'array', 'max:'.self::SUBSCRIBER_BULK_LIMIT],
            'ids.*' => ['integer'],
        ]);
        if (($data['scope'] ?? null) === 'filtered') {
            $query = $this->subscriberQuery($request, ListFilters::from($request, self::SUBSCRIBER_SORTS));
            $ids = (clone $query)->limit(self::SUBSCRIBER_BULK_LIMIT)->pluck('subscribers.id')->map(fn ($id) => (int) $id)->all();

            return [$ids, (clone $query)->reorder()->count()];
        }
        $ids = array_values(array_unique(array_map('intval', $data['ids'])));

        return [$ids, count($ids)];
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
        // A web request's cap. On the command line (PHPUnit included) set_time_limit
        // bounds the whole process, so it is left alone there (debt #48).
        if (PHP_SAPI !== 'cli') {
            @set_time_limit(280);
        }
        $code = Artisan::call('external:sync-aiid-api', ['--max' => 300]);
        $out = trim(Artisan::output());

        return back()->with($code === 0 ? 'success' : 'error', $out ?: ($code === 0 ? 'Sync finished.' : 'Sync failed.'));
    }

    /** The timetable: every job, when it runs, how its last run ended, and a button to run it now. */
    public function jobs(Request $request): View
    {
        $latest = JobRun::latest();
        $filters = ListFilters::from($request, ['started' => 'started_at', 'job' => 'job']);
        $history = $this->jobRunQuery($request, $filters)->with('user')->paginate(30, ['*'], 'runs')->withQueryString();
        $scheduler = ['last_tick' => Cache::get('scheduler.last_tick')];
        $week = JobRun::where('started_at', '>=', now()->subDays(6)->startOfDay());
        $summary = [
            'runs' => (clone $week)->count(),
            'failed' => (clone $week)->whereNotNull('finished_at')->where('exit_code', '!=', 0)->where('exit_code', '!=', JobRun::SKIPPED)->count(),
        ];

        return view('backend.admin.jobs', ['jobs' => JobRun::JOBS, 'latest' => $latest, 'history' => $history, 'scheduler' => $scheduler, 'filters' => $filters, 'summary' => $summary]);
    }

    public function jobsExport(Request $request): StreamedResponse
    {
        return CsvStream::from($this->jobRunQuery($request, ListFilters::from($request, ['started' => 'started_at', 'job' => 'job']))->with('user'), 'job-runs',
            ['started_at', 'finished_at', 'job', 'trigger', 'started_by', 'exit_code', 'result', 'output'],
            fn (JobRun $r) => [$r->started_at, $r->finished_at, $r->job, $r->trigger, $r->user?->email, $r->exit_code, $this->runResult($r), mb_substr((string) $r->output, 0, 2000)]);
    }

    private function runResult(JobRun $run): string
    {
        return match (true) {
            $run->finished_at === null => 'running',
            $run->skipped() => 'skipped',
            $run->succeeded() => 'ok',
            default => 'failed',
        };
    }

    private function jobRunQuery(Request $request, ListFilters $filters): Builder
    {
        $query = JobRun::query()
            ->when(array_key_exists((string) $request->query('job'), JobRun::JOBS), fn ($q) => $q->where('job', (string) $request->query('job')))
            ->when(in_array($request->query('trigger'), ['schedule', 'admin', 'cron'], true), fn ($q) => $q->where('trigger', (string) $request->query('trigger')));
        match ($request->query('result')) {
            'ok' => $query->where('exit_code', 0),
            'failed' => $query->whereNotNull('finished_at')->where('exit_code', '!=', 0)->where('exit_code', '!=', JobRun::SKIPPED),
            'skipped' => $query->where('exit_code', JobRun::SKIPPED),
            'running' => $query->whereNull('finished_at'),
            default => null,
        };
        $filters->search($query, ['output']);
        $filters->dateRange($query, 'started_at');

        return $filters->order($query);
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
        ContentCache::flush();

        return redirect()->route('backend.admin.jobs')->with($run->succeeded() ? 'success' : 'error', $meta['label'].($run->succeeded() ? ' finished' : ' failed').($run->output ? ': '.Str::limit($run->output, 300) : '.'));
    }

    /**
     * The environment variable each setting overrides. Shown beside the field so the
     * owner can tell a value the host set from one stored here.
     */
    private const ENV_NAMES = [
        'mail_mailer' => 'MAIL_MAILER', 'resend_key' => 'RESEND_KEY', 'mail_from_address' => 'MAIL_FROM_ADDRESS', 'mail_from_name' => 'MAIL_FROM_NAME', 'cron_token' => 'CRON_TOKEN',
        'billing_enabled' => 'BILLING_ENABLED', 'dodo_environment' => 'DODO_PAYMENTS_ENVIRONMENT', 'dodo_api_key' => 'DODO_PAYMENTS_API_KEY', 'dodo_webhook_secret' => 'DODO_PAYMENTS_WEBHOOK_KEY',
        'turnstile_site_key' => 'TURNSTILE_SITE_KEY', 'turnstile_secret_key' => 'TURNSTILE_SECRET_KEY',
        'dodo_product_pro_monthly' => 'DODO_PRODUCT_PRO_MONTHLY', 'dodo_product_pro_yearly' => 'DODO_PRODUCT_PRO_YEARLY',
        'contact_email' => 'CONTACT_EMAIL', 'google_analytics_id' => 'GOOGLE_ANALYTICS_ID', 'cloudflare_analytics_token' => 'CLOUDFLARE_ANALYTICS_TOKEN',
        'analytics_require_consent' => 'ANALYTICS_REQUIRE_CONSENT', 'social_cards_enabled' => 'SOCIAL_CARDS_ENABLED', 'email_domain_enforcement' => 'EMAIL_DOMAIN_ENFORCEMENT',
        'google_site_verification' => 'GOOGLE_SITE_VERIFICATION', 'bing_site_verification' => 'BING_SITE_VERIFICATION', 'x_handle' => 'SITE_X_HANDLE', 'newsletter_url' => 'SITE_NEWSLETTER_URL',
        'dataset_doi' => 'DATASET_DOI', 'sponsor_url' => 'SPONSOR_URL',
    ];

    public function settings(): View
    {
        // With the configuration cached (every production release), .env is never read,
        // so env() answers null for everything and the page used to imply nothing was
        // set on the host. Say so instead of guessing.
        $envReadable = ! app()->configurationIsCached();
        $values = [];
        foreach (AppSetting::KEYS as $key => $meta) {
            $current = AppSetting::get($key);
            $values[$key] = ['meta' => $meta, 'set' => $current !== null && $current !== '', 'display' => $meta['secret'] ? AppSetting::mask($current) : ($current ?? ''), 'env' => $envReadable ? $this->envHint($key, (bool) $meta['secret']) : null];
        }

        return view('backend.admin.settings', ['values' => $values, 'envReadable' => $envReadable, 'turnstile' => app(Turnstile::class), 'effective' => ['mailer' => config('mail.default'), 'from' => config('mail.from.address').' ('.config('mail.from.name').')', 'resend' => (bool) config('services.resend.key')],
            'citation' => ['doi' => DatasetCitation::doi(), 'sponsor' => FundingDisclosure::sponsorUrl(), 'threshold' => FundingDisclosure::threshold()]]);
    }

    private function envHint(string $key, bool $secret): ?string
    {
        $name = self::ENV_NAMES[$key] ?? null;
        $value = $name ? env($name) : null;
        if ($value === null || $value === '') {
            return null;
        }

        return match (true) {
            $secret => 'set in environment',
            in_array($key, ['billing_enabled', 'analytics_require_consent', 'social_cards_enabled', 'email_domain_enforcement'], true) => filter_var($value, FILTER_VALIDATE_BOOL) ? 'on' : 'off',
            default => (string) $value,
        };
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
            // Cloudflare keys look like 0x4AAAAAAA…; the test keys start 1x/2x/3x.
            'turnstile_site_key' => ['nullable', 'string', 'max:120', 'regex:/^[0-9A-Za-z_-]{10,120}$/'],
            'turnstile_secret_key' => ['nullable', 'string', 'max:120', 'regex:/^[0-9A-Za-z_-]{10,120}$/'],
            'newsletter_url' => ['nullable', 'url:https', 'max:512'],
            // Parsed the way the citation code reads it, so a value accepted here is one it prints.
            'dataset_doi' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) {
                if (DatasetCitation::parse((string) $value) === null) {
                    $fail('Enter a DOI such as 10.5281/zenodo.1234567, or its https://doi.org/ URL.');
                }
            }],
            'sponsor_url' => ['nullable', 'url:https', 'max:512'],
            'funding_threshold' => ['nullable', 'integer', 'min:0', 'max:10000000'],
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

    public function settingsTurnstileCheck(Turnstile $turnstile): RedirectResponse
    {
        $result = $turnstile->checkSecret();

        return back()->with($result['ok'] ? 'success' : 'error', 'Turnstile: '.$result['message']);
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
