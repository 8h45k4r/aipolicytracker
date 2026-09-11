@extends('backend.layouts.app', ['title' => 'External data'])
@section('content')
<h1 class="font-display text-2xl font-semibold text-brand-navy">External data</h1>
<p class="mt-1 meta">Third-party datasets shown on the public site. Incidents are synced from the AI Incident Database API every six hours ("Sync AI incidents" workflow); the weekly "Refresh external datasets" workflow rebuilds the charts and opens a pull request.</p>
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="card-flat p-5"><h2 class="section-title !text-lg">AI Incident Database</h2>
        <dl class="mt-3 text-sm divide-y divide-brand-line">
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Snapshot</dt><dd class="font-mono">{{ $aiid['snapshot_date'] ?? '—' }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Export file</dt><dd class="font-mono text-xs">{{ $aiid['export_file'] ?? '—' }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Incidents (summary / imported rows)</dt><dd class="font-mono">{{ isset($aiid['totals']) ? number_format($aiid['totals']['incidents']) : '—' }} / {{ number_format(\App\Models\ExternalIncident::count()) }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Licence</dt><dd>{{ $aiid['license'] ?? '—' }}</dd></div>
        </dl>
        <h3 class="mt-4 text-sm font-semibold text-brand-navy">Live API sync</h3>
        <dl class="mt-1 text-sm divide-y divide-brand-line">
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Rows (synced from API / total)</dt><dd class="font-mono">{{ number_format($live['synced_rows']) }} / {{ number_format($live['rows']) }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Latest incident id</dt><dd class="font-mono">{{ $live['latest_id'] ?? '—' }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Report rows</dt><dd class="font-mono">{{ number_format($live['reports']) }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Last synced</dt><dd class="font-mono">{{ $live['synced_at'] ? \Illuminate\Support\Carbon::parse($live['synced_at'])->format('Y-m-d H:i') : '—' }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Last run</dt><dd class="font-mono text-xs">@if($live['last_run']){{ $live['last_run']['at'] }} · {{ $live['last_run']['incidents'] }} incidents, {{ $live['last_run']['reports'] }} reports{{ $live['last_run']['error'] ? ' · error: '.$live['last_run']['error'] : '' }}@else —@endif</dd></div>
        </dl>
        <form method="post" action="{{ route('backend.admin.external.sync') }}" class="mt-3 flex flex-wrap items-center gap-3 text-sm">@csrf<button type="submit" class="btn-primary">Sync now from the AIID API</button><span class="meta">Incremental: records modified since the last sync, up to 300 per run.</span></form>
        <p class="mt-3 text-sm"><a href="{{ route('risk.incidents') }}">Public page</a> · <a href="{{ config('aipolicytracker.github_url') }}/actions/workflows/sync-aiid.yml" rel="noopener">Sync workflow</a> · <a href="{{ config('aipolicytracker.github_url') }}/actions/workflows/refresh-external-data.yml" rel="noopener">Weekly refresh workflow</a></p>
    </section>
    <section class="card-flat p-5"><h2 class="section-title !text-lg">MIT AI Risk Repository</h2>
        <dl class="mt-3 text-sm divide-y divide-brand-line">
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Edition</dt><dd>{{ $mit['edition'] ?? '—' }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Domains / subdomains</dt><dd class="font-mono">{{ isset($mit['domains']) ? count($mit['domains']).' / '.collect($mit['domains'])->sum(fn ($d) => count($d['subdomains'])) : '—' }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Generated</dt><dd class="font-mono">{{ $mit['generated_at'] ?? '—' }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Risk rows imported</dt><dd class="font-mono">{{ number_format(\App\Models\ExternalRisk::count()) }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Licence</dt><dd>{{ $mit['license'] ?? '—' }}</dd></div>
        </dl>
        <p class="mt-3 text-sm"><a href="{{ route('risk.index') }}">Public page</a></p>
    </section>
</div>
@endsection
