@extends('backend.layouts.app', ['title' => 'Jobs and schedule'])
@section('content')
<h1 class="font-display text-2xl font-semibold text-brand-navy">Jobs and schedule</h1>
<p class="mt-1 meta">Every recurring job the platform runs, when the scheduler runs it, how the last run ended, and a button to run it now. Jobs that send e-mail or rewrite data ask for your password first. Runs started by the scheduler, by <code>/cron/*</code> and from this page are all recorded here.</p>
@if(! $scheduler['last_tick'])<p class="mt-3 rounded-sm border border-state-warn/30 bg-state-warnbg px-3 py-2 text-sm text-state-warn">The scheduler has not reported a tick yet. On a server, add <code>* * * * * php artisan schedule:run</code> to cron; the container runs <code>schedule:work</code> itself. Until then, run jobs from here or through the cron endpoints.</p>@else<p class="mt-3 text-sm text-brand-body">Scheduler last ticked {{ \Illuminate\Support\Carbon::parse($scheduler['last_tick'])->diffForHumans() }}.</p>@endif
<div class="table-wrap mt-6"><table><caption class="sr-only">Jobs</caption>
    <thead><tr><th scope="col">Job</th><th scope="col">Schedule</th><th scope="col">Last run</th><th scope="col">Result</th><th scope="col">Run</th></tr></thead>
    <tbody>
    @foreach($jobs as $key => $job)
    @php($last = $latest[$key])
    <tr>
        <td><span class="font-medium text-brand-navy">{{ $job['label'] }}</span><div class="meta">{{ $job['what'] }} <code class="text-[11px]">{{ $job['command'] }}</code></div></td>
        <td class="whitespace-nowrap text-xs">{{ $job['schedule'] }}</td>
        <td class="whitespace-nowrap text-xs">@if($last){{ $last->started_at->format('j M Y H:i') }}<div class="meta">{{ $last->trigger }}@if($last->user) · {{ $last->user->name }}@endif</div>@else<span class="text-brand-muted">never</span>@endif</td>
        <td>@if($last)<span class="badge {{ $last->finished_at === null ? 'bg-state-warnbg text-state-warn ring-state-warn/20' : ($last->succeeded() ? 'bg-state-goodbg text-state-good ring-state-good/20' : 'bg-state-badbg text-state-bad ring-state-bad/20') }}">{{ $last->finished_at === null ? 'running' : ($last->succeeded() ? 'ok' : 'failed') }}</span>@if($last->output)<details class="mt-1 text-xs"><summary class="cursor-pointer text-brand-muted">output</summary><pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap rounded-sm bg-brand-paper p-2 text-[11px]">{{ $last->output }}</pre></details>@endif@else<span class="text-brand-muted">—</span>@endif</td>
        <td><form method="post" action="{{ $job['confirm'] ? route('backend.admin.jobs.run.confirmed', $key) : route('backend.admin.jobs.run', $key) }}">@csrf<button type="submit" class="btn-secondary !min-h-0 !py-1 text-xs">Run now{{ $job['confirm'] ? ' (confirm)' : '' }}</button></form></td>
    </tr>
    @endforeach
    </tbody></table></div>
<section class="mt-8 card-flat p-5" aria-labelledby="history">
    <h2 id="history" class="section-title !text-lg">Recent runs</h2>
    @if($history->isEmpty())<p class="mt-3 text-sm text-brand-muted">No runs recorded yet.</p>@else
    <table class="mt-3 w-full text-sm"><thead><tr class="text-left text-xs uppercase tracking-wide text-brand-muted"><th class="py-1">Started</th><th>Job</th><th>Trigger</th><th>Duration</th><th>Result</th></tr></thead>
    <tbody class="divide-y divide-brand-line">@foreach($history as $r)<tr><td class="py-2 font-mono whitespace-nowrap text-xs">{{ $r->started_at->format('j M Y H:i:s') }}</td><td>{{ $jobs[$r->job]['label'] ?? $r->job }}</td><td class="text-xs">{{ $r->trigger }}@if($r->user) · {{ $r->user->name }}@endif</td><td class="text-xs">{{ $r->finished_at ? $r->started_at->diffInSeconds($r->finished_at).'s' : '—' }}</td><td><span class="badge-neutral">{{ $r->finished_at === null ? 'running' : ($r->succeeded() ? 'ok' : 'exit '.$r->exit_code) }}</span></td></tr>@endforeach</tbody></table>
    @endif
</section>
@endsection
