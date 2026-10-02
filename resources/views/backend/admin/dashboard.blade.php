@extends('backend.layouts.app', ['title' => 'Dashboard'])
@section('content')
@php($user = auth()->user())
@php($scheduled = collect($jobs)->filter(fn ($run, $job) => \App\Models\JobRun::isScheduled($job)))
@php($healthy = $scheduled->filter(fn ($run) => $run && $run->finished_at && $run->succeeded())->count())
@php($verifiedPct = $stats['policies'] ? (int) round(100 * $stats['policies_verified'] / $stats['policies']) : 0)

<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="font-display text-2xl font-semibold text-brand-navy">Dashboard</h1>
        <p class="mt-1 meta">{{ now()->format('l j F Y') }} · signed in as {{ $user->name }}</p>
    </div>
    <div class="flex flex-wrap gap-2 text-sm" aria-label="Quick actions">
        @can('submissions.decide')<a href="{{ route('backend.review.index') }}" class="btn-primary !min-h-[38px] !py-1.5">Open review queue</a>@endcan
        @can('users.manage')<a href="{{ route('backend.admin.users.index') }}#invite" class="btn-secondary !min-h-[38px] !py-1.5">Invite a user</a>@endcan
        @can('jobs.run')<a href="{{ route('backend.admin.jobs') }}" class="btn-secondary !min-h-[38px] !py-1.5">Run a job</a>@endcan
    </div>
</div>

{{-- What needs someone now, most serious first. Everything below is reference. --}}
<section class="mt-6" aria-labelledby="attention-heading" data-attention>
    <h2 id="attention-heading" class="sr-only">Needs attention</h2>
    @if($attention === [])
    <p class="card-flat flex items-center gap-3 p-4 text-sm text-state-good"><svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 10.5 3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span><span class="font-semibold">All clear.</span> Nothing needs attention: jobs are healthy, the queues are empty and email is being delivered.</span></p>
    @else
    <ul class="card-flat divide-y divide-brand-line">
        @foreach($attention as $item)
        @php($tone = ['critical' => ['bg-state-bad', 'text-state-bad', 'Action needed'], 'warning' => ['bg-state-warn', 'text-state-warn', 'Review'], 'info' => ['bg-brand-blue', 'text-brand-blue', 'For information']][$item['severity']])
        <li class="flex flex-wrap items-center gap-x-4 gap-y-2 p-4" data-severity="{{ $item['severity'] }}">
            <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $tone[0] }}" aria-hidden="true"></span>
            <div class="min-w-0 flex-1 basis-[calc(100%-2rem)] sm:basis-0">
                <p class="text-sm font-semibold text-brand-navy"><span class="sr-only">{{ $tone[2] }}: </span>{{ $item['title'] }}</p>
                <p class="text-sm text-brand-muted">{{ $item['detail'] }}</p>
            </div>
            <a href="{{ $item['url'] }}" class="btn-secondary ml-6 sm:ml-0 !min-h-[36px] !py-1 text-sm">{{ $item['action'] }}</a>
        </li>
        @endforeach
    </ul>
    @endif
</section>

<dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="card-flat p-4">
        <dt class="meta">Instruments verified</dt>
        <dd class="mt-1 font-mono tabular-nums text-2xl text-brand-navy">{{ $stats['policies_verified'] }}<span class="text-base text-brand-muted"> / {{ $stats['policies'] }}</span></dd>
        <dd class="mt-2 h-1.5 overflow-hidden rounded-full bg-brand-line" aria-hidden="true"><span class="block h-full rounded-full bg-state-good" style="width: {{ $verifiedPct }}%"></span></dd>
        <dd class="mt-1.5 text-xs text-brand-muted">{{ $verifiedPct }}% · {{ $stale ?: 'none' }} unverified or past 180 days</dd>
    </div>
    <div class="card-flat p-4">
        <dt class="meta">Obligations and controls</dt>
        <dd class="mt-1 font-mono tabular-nums text-2xl text-brand-navy">{{ $stats['obligations'] }}<span class="text-base text-brand-muted"> · {{ $stats['controls'] }}</span></dd>
        <dd class="mt-1.5 text-xs text-brand-muted">across {{ $stats['jurisdictions'] }} jurisdictions · {{ $stats['changes_30d'] }} changes in 30 days</dd>
    </div>
    <div class="card-flat p-4">
        <dt class="meta">Active subscribers</dt>
        <dd class="mt-1 font-mono tabular-nums text-2xl text-brand-navy">{{ $stats['subscribers_active'] }}</dd>
        <dd class="mt-1.5 text-xs text-brand-muted">{{ $stats['subscribers_unconfirmed'] }} awaiting confirmation · <a href="{{ route('backend.admin.subscribers') }}">Open</a></dd>
    </div>
    <div class="card-flat p-4">
        <dt class="meta">Template downloads, 30 days</dt>
        <dd class="mt-1 font-mono tabular-nums text-2xl text-brand-navy">{{ $stats['downloads_30d'] }}</dd>
        <dd class="mt-1.5 text-xs text-brand-muted">{{ $stats['users'] }} registered users · <a href="{{ route('backend.admin.downloads') }}">Open</a></dd>
    </div>
</dl>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="card-flat p-5" aria-labelledby="health">
        <div class="flex items-baseline justify-between gap-3"><h2 id="health" class="section-title !text-lg">System health</h2>@can('jobs.run')<a href="{{ route('backend.admin.jobs') }}" class="text-sm">Jobs and schedule</a>@endcan</div>
        <dl class="mt-3 divide-y divide-brand-line text-sm">
            <div class="flex items-center justify-between gap-3 py-2"><dt>Scheduled jobs</dt><dd class="{{ $healthy === $scheduled->count() ? 'text-state-good' : 'text-state-bad' }}">{{ $healthy }} of {{ $scheduled->count() }} healthy</dd></div>
            <div class="flex items-center justify-between gap-3 py-2"><dt>Email</dt><dd class="{{ $mail['mailer'] === 'log' ? 'text-state-bad' : 'text-state-good' }}">{{ $mail['mailer'] === 'log' ? 'Not sending (log only)' : 'Sending via '.$mail['mailer'] }}</dd></div>
            <div class="flex items-center justify-between gap-3 py-2"><dt>Bot protection</dt><dd class="{{ $turnstile ? 'text-state-good' : 'text-state-warn' }}">{{ $turnstile ? 'Turnstile on' : 'Turnstile off' }}</dd></div>
            <div class="flex items-center justify-between gap-3 py-2"><dt>AI Incident Database snapshot</dt><dd class="font-mono">{{ $aiid['snapshot_date'] ?? '—' }}</dd></div>
        </dl>
        <details class="mt-3 text-sm">
            <summary class="text-brand-blue">Every scheduled job</summary>
            <ul class="mt-2 divide-y divide-brand-line">
                @foreach(\App\Models\JobRun::JOBS as $key => $job)
                @continue($job['schedule'] === 'On demand')
                @php($last = $jobs[$key])
                <li class="flex flex-wrap items-baseline justify-between gap-2 py-2">
                    <span>{{ $job['label'] }} <span class="meta">{{ $job['schedule'] }}</span></span>
                    <span class="text-xs">
                        @if(! \App\Models\JobRun::isScheduled($key))<span class="text-brand-muted">off: no list source set</span>
                        @elseif($last)<span class="badge {{ $last->finished_at && $last->succeeded() ? 'bg-state-goodbg text-state-good ring-state-good/20' : ($last->finished_at ? 'bg-state-badbg text-state-bad ring-state-bad/20' : 'bg-state-warnbg text-state-warn ring-state-warn/20') }}" @if($last->finished_at && ! $last->succeeded()) title="{{ \Illuminate\Support\Str::limit($last->output, 300) }}" @endif>{{ $last->finished_at ? ($last->succeeded() ? 'ok' : 'failed') : 'running' }}</span> {{ $last->started_at->diffForHumans() }}
                        @else<span class="text-brand-muted">never run</span>@endif
                    </span>
                </li>
                @endforeach
            </ul>
        </details>
    </section>

    <section class="card-flat p-5" aria-labelledby="recent">
        <div class="flex items-baseline justify-between gap-3"><h2 id="recent" class="section-title !text-lg">Latest submissions</h2>@can('submissions.decide')<a href="{{ route('backend.admin.submissions') }}" class="text-sm">All submissions</a>@endcan</div>
        @if($recentSubmissions->isEmpty())<p class="mt-3 text-sm text-brand-muted">No submissions yet. Readers' corrections and sources will appear here.</p>@else
        <ul class="mt-3 divide-y divide-brand-line text-sm">
            @foreach($recentSubmissions as $s)
            <li class="py-2"><a href="{{ route('backend.admin.submissions', ['type' => $s->type]) }}" class="font-medium">{{ \Illuminate\Support\Str::limit($s->summary, 80) }}</a><span class="block text-xs text-brand-muted">{{ $s->created_at->format('j M Y') }} · {{ \App\Models\ContributorSubmission::TYPES[$s->type] ?? $s->type }} · {{ str_replace('_', ' ', $s->status) }}</span></li>
            @endforeach
        </ul>
        @endif
    </section>
</div>
@endsection
