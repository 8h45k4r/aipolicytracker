@extends('backend.layouts.app', ['title' => 'Dashboard'])
@section('content')
<h1 class="font-display text-2xl font-semibold text-brand-navy">Dashboard</h1>
<p class="mt-1 meta">Live counts from the structured policy-intelligence model that powers the public site.</p>
<dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach([['Published jurisdictions', $stats['jurisdictions'], route('jurisdictions.index')], ['Published instruments', $stats['policies'], route('policies.index')], ['Human-verified instruments', $stats['policies_verified'].' / '.$stats['policies'], route('backend.review.index')], ['Obligations', $stats['obligations'], route('obligations.index')], ['Changes in last 30 days', $stats['changes_30d'], route('changes.index')], ['Submissions awaiting review', $stats['submissions_pending'], route('backend.admin.submissions', ['status' => 'pending_review'])], ['Active subscribers', $stats['subscribers_active'], route('backend.admin.subscribers')], ['Unconfirmed subscribers', $stats['subscribers_unconfirmed'], route('backend.admin.subscribers', ['state' => 'unconfirmed'])]] as [$label, $value, $href])
    <div class="card-flat p-4"><dt class="meta">{{ $label }}</dt><dd class="mt-1 font-mono tabular-nums text-2xl text-brand-navy">{{ $value === 0 || $value === '0 / 0' ? '—' : $value }}</dd><a href="{{ $href }}" class="text-xs">Open</a></div>
    @endforeach
</dl>
<div class="mt-8 grid gap-8 lg:grid-cols-2">
    <section class="card-flat p-5" aria-labelledby="queues">
        <h2 id="queues" class="section-title !text-lg">Editorial queues</h2>
        <ul class="mt-3 divide-y divide-brand-line text-sm">
            <li class="py-2 flex justify-between"><span>Instruments never verified or stale (&gt;180 days)</span><a href="{{ route('backend.review.index') }}" class="font-mono">{{ $stale ?: '—' }}</a></li>
            <li class="py-2 flex justify-between"><span>Submissions pending review</span><a href="{{ route('backend.admin.submissions', ['status' => 'pending_review']) }}" class="font-mono">{{ $stats['submissions_pending'] ?: '—' }}</a></li>
            <li class="py-2 flex justify-between"><span>AI Incident Database snapshot</span><a href="{{ route('backend.admin.external') }}" class="font-mono">{{ $aiid['snapshot_date'] ?? '—' }}</a></li>
        </ul>
    </section>
    <section class="card-flat p-5" aria-labelledby="mail">
        <h2 id="mail" class="section-title !text-lg">Email delivery</h2>
        <dl class="mt-3 text-sm divide-y divide-brand-line">
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Transport</dt><dd class="font-mono">{{ $mail['mailer'] }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">From</dt><dd class="font-mono">{{ $mail['from'] ?: '—' }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Resend API key</dt><dd>{{ $mail['resend_key_set'] ? 'configured' : 'not set' }}</dd></div>
        </dl>
        @if($mail['mailer'] === 'log')<p class="mt-3 text-sm text-state-warn">Mail is written to the log only. Configure Resend under <a href="{{ route('backend.admin.settings') }}">Settings and API keys</a>.</p>@endif
    </section>
</div>
<section class="mt-8 card-flat p-5" aria-labelledby="recent">
    <h2 id="recent" class="section-title !text-lg">Latest submissions</h2>
    @if($recentSubmissions->isEmpty())<p class="mt-3 text-sm text-brand-muted">No submissions yet.</p>@else
    <table class="mt-3 w-full text-sm"><thead><tr class="text-left text-xs uppercase tracking-wide text-brand-muted"><th class="py-1">Date</th><th>Type</th><th>Summary</th><th>Status</th></tr></thead>
    <tbody class="divide-y divide-brand-line">@foreach($recentSubmissions as $s)<tr><td class="py-2 font-mono whitespace-nowrap">{{ $s->created_at->format('j M Y') }}</td><td>{{ \App\Models\ContributorSubmission::TYPES[$s->type] ?? $s->type }}</td><td><a href="{{ route('backend.admin.submissions', ['type' => $s->type]) }}">{{ \Illuminate\Support\Str::limit($s->summary, 90) }}</a></td><td><span class="badge-neutral">{{ $s->status }}</span></td></tr>@endforeach</tbody></table>
    @endif
</section>
@endsection
