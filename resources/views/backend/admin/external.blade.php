@extends('backend.layouts.app', ['title' => 'External data'])
@section('content')
<h1 class="font-display text-2xl font-semibold text-brand-navy">External data</h1>
<p class="mt-1 meta">Third-party datasets shown on the public site. Refreshed weekly by the "Refresh external datasets" workflow, which opens a pull request.</p>
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="card-flat p-5"><h2 class="section-title !text-lg">AI Incident Database</h2>
        <dl class="mt-3 text-sm divide-y divide-brand-line">
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Snapshot</dt><dd class="font-mono">{{ $aiid['snapshot_date'] ?? '—' }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Export file</dt><dd class="font-mono text-xs">{{ $aiid['export_file'] ?? '—' }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Incidents (summary / imported rows)</dt><dd class="font-mono">{{ isset($aiid['totals']) ? number_format($aiid['totals']['incidents']) : '—' }} / {{ number_format(\App\Models\ExternalIncident::count()) }}</dd></div>
            <div class="py-2 flex justify-between"><dt class="text-brand-muted">Licence</dt><dd>{{ $aiid['license'] ?? '—' }}</dd></div>
        </dl>
        <p class="mt-3 text-sm"><a href="{{ route('risk.incidents') }}">Public page</a> · <a href="{{ config('aipolicytracker.github_url') }}/actions/workflows/refresh-external-data.yml" rel="noopener">Run refresh workflow</a></p>
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
