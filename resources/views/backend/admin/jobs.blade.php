@extends('backend.layouts.app', ['title' => 'Jobs and schedule'])
@section('content')
<x-backend.page-header title="Jobs and schedule">
    <x-slot:description>Every recurring job the platform runs, when the scheduler runs it, how the last run ended, and a button to run it now. Jobs that send e-mail or rewrite data ask for your password first. Runs started by the scheduler, by <code>/cron/*</code> and from this page are all recorded.</x-slot:description>
</x-backend.page-header>
<div class="mt-5 grid gap-4 sm:grid-cols-3">
    <x-backend.stat label="Runs, last 7 days" :value="number_format($summary['runs'])" :href="route('backend.admin.jobs', ['from' => now()->subDays(6)->toDateString()]).'#history'" />
    <x-backend.stat label="Failed runs, last 7 days" :value="number_format($summary['failed'])" :tone="$summary['failed'] ? 'text-state-bad' : null" :href="route('backend.admin.jobs', ['result' => 'failed', 'from' => now()->subDays(6)->toDateString()]).'#history'" />
    <x-backend.stat label="Scheduler" :value="$scheduler['last_tick'] ? 'Running' : 'No tick yet'" :tone="$scheduler['last_tick'] ? 'text-state-good' : 'text-state-warn'" :hint="$scheduler['last_tick'] ? 'Last tick '.\Illuminate\Support\Carbon::parse($scheduler['last_tick'])->diffForHumans() : 'Runs only from here and /cron/* until it ticks'" />
</div>
@if(! $scheduler['last_tick'])<p class="mt-3 rounded-sm border border-state-warn/30 bg-state-warnbg px-3 py-2 text-sm text-state-warn">The scheduler has not reported a tick yet. On a server, add <code>* * * * * php artisan schedule:run</code> to cron; the container runs <code>schedule:work</code> itself. Until then, run jobs from here or through the cron endpoints.</p>@else<p class="mt-3 text-sm text-brand-body">Scheduler last ticked {{ \Illuminate\Support\Carbon::parse($scheduler['last_tick'])->diffForHumans() }}.</p>@endif
<div class="table-wrap mt-6"><table><caption class="sr-only">Jobs</caption>
    <thead><tr><th scope="col">Job</th><th scope="col">Schedule</th><th scope="col">Last run</th><th scope="col">Result</th><th scope="col">Run</th></tr></thead>
    <tbody>
    @foreach($jobs as $key => $job)
    @php($last = $latest[$key])
    <tr>
        <td><a href="{{ route('backend.admin.jobs', ['job' => $key]) }}#history" class="font-medium text-brand-navy no-underline hover:underline" title="This job's run history">{{ $job['label'] }}</a><div class="meta">{{ $job['what'] }} <code class="text-[11px]">{{ $job['command'] }}</code></div></td>
        <td class="whitespace-nowrap text-xs">{{ $job['schedule'] }}</td>
        <td class="whitespace-nowrap text-xs">@if($last){{ $last->started_at->format('j M Y H:i') }}<div class="meta">{{ $last->trigger }}@if($last->user) · {{ $last->user->name }}@endif</div>@else<span class="text-brand-muted">never</span>@endif</td>
        <td>@if($last)<span class="badge {{ $last->finished_at === null ? 'bg-state-warnbg text-state-warn ring-state-warn/20' : ($last->succeeded() ? 'bg-state-goodbg text-state-good ring-state-good/20' : 'bg-state-badbg text-state-bad ring-state-bad/20') }}">{{ $last->finished_at === null ? 'running' : ($last->succeeded() ? 'ok' : 'failed') }}</span>@if($last->output)<details class="mt-1 text-xs"><summary class="cursor-pointer text-brand-muted">output</summary><pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap rounded-sm bg-brand-paper p-2 text-[11px]">{{ $last->output }}</pre></details>@endif@else<span class="text-brand-muted">—</span>@endif</td>
        <td><form method="post" action="{{ $job['confirm'] ? route('backend.admin.jobs.run.confirmed', $key) : route('backend.admin.jobs.run', $key) }}">@csrf<button type="submit" class="btn-secondary !min-h-0 !py-1 text-xs">Run now{{ $job['confirm'] ? ' (confirm)' : '' }}</button></form></td>
    </tr>
    @endforeach
    </tbody></table></div>
<section class="mt-8" aria-labelledby="history">
    <h2 id="history" class="text-lg font-semibold text-brand-navy">Run history</h2>
    <x-backend.filters :action="route('backend.admin.jobs')" :filters="$filters" :export="route('backend.admin.jobs.export')" placeholder="Text in the output" :total="$history->total()" noun="run">
        <div><label for="f-job" class="adm-label">Job</label><select id="f-job" name="job" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Any</option>@foreach($jobs as $k => $j)<option value="{{ $k }}" @selected(request('job') === $k)>{{ $j['label'] }}</option>@endforeach</select></div>
        <div><label for="f-result" class="adm-label">Result</label><select id="f-result" name="result" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Any</option>@foreach(['ok' => 'OK', 'failed' => 'Failed', 'skipped' => 'Skipped (already running)', 'running' => 'Running'] as $k => $l)<option value="{{ $k }}" @selected(request('result') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="f-trigger" class="adm-label">Started by</label><select id="f-trigger" name="trigger" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Anyone</option>@foreach(['schedule' => 'Scheduler', 'admin' => 'Admin button', 'cron' => 'Cron endpoint'] as $k => $l)<option value="{{ $k }}" @selected(request('trigger') === $k)>{{ $l }}</option>@endforeach</select></div>
    </x-backend.filters>
    @if($history->isEmpty())<div class="mt-4"><x-site.empty title="No runs match" :reset="route('backend.admin.jobs')">Runs appear here as the scheduler, the cron endpoints or this page start jobs.</x-site.empty></div>@else
    <div class="table-wrap mt-4"><table><caption class="sr-only">Run history</caption><thead><tr>
        <x-backend.sort-th key="started" label="Started" :filters="$filters" /><x-backend.sort-th key="job" label="Job" :filters="$filters" /><th scope="col">Started by</th><th scope="col">Duration</th><th scope="col">Result</th>
    </tr></thead>
    <tbody>@foreach($history as $r)
        @php($result = $r->finished_at === null ? 'running' : ($r->skipped() ? 'skipped' : ($r->succeeded() ? 'ok' : 'failed')))
        <tr>
            <td class="font-mono whitespace-nowrap text-xs">{{ $r->started_at->format('j M Y H:i:s') }}</td>
            <td><a href="{{ request()->fullUrlWithQuery(['job' => $r->job, 'runs' => null]) }}#history" class="no-underline hover:underline">{{ $jobs[$r->job]['label'] ?? $r->job }}</a></td>
            <td class="text-xs">{{ $r->trigger }}@if($r->user) · {{ $r->user->name }}@endif</td>
            <td class="text-xs tabular-nums">{{ $r->finished_at ? (int) round($r->started_at->diffInSeconds($r->finished_at)).'s' : '—' }}</td>
            <td><x-backend.badge :status="$result === 'skipped' ? 'draft' : $result">{{ $result }}{{ $result === 'failed' ? ' (exit '.$r->exit_code.')' : '' }}</x-backend.badge>@if($r->output)<details class="mt-1 text-xs"><summary class="cursor-pointer text-brand-muted">output</summary><pre class="mt-1 max-h-48 overflow-auto whitespace-pre-wrap rounded-sm bg-brand-paper p-2 text-[11px]">{{ $r->output }}</pre></details>@endif</td>
        </tr>
    @endforeach</tbody></table></div>
    <nav class="mt-4" aria-label="Run history pagination">{{ $history->links() }}</nav>
    @endif
</section>
@endsection
