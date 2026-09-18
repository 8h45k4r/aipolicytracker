@extends('backend.layouts.app', ['title' => 'Guides and downloads'])
@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
    <div><h1 class="font-display text-2xl font-semibold text-brand-navy">Guides and downloads</h1><p class="mt-1 meta">Manage the free-tool library (tools, files, versions) and see who downloads what. Downloads need a free account; each one records terms acceptance and the file version.</p></div>
    <div class="flex gap-2"><a href="{{ route('backend.admin.tools.index') }}" class="btn-primary">Manage library</a><a href="{{ route('backend.admin.downloads.export') }}" class="btn-secondary">Export users CSV</a><a href="{{ route('backend.admin.downloads.export', ['rows' => 'downloads']) }}" class="btn-secondary">Export downloads CSV (one row each)</a></div>
</div>
<dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach([['Registered users', $metrics['users_total']], ['New users: today / 7d / 30d', ($metrics['users_today'] ?: '—').' / '.($metrics['users_7d'] ?: '—').' / '.($metrics['users_30d'] ?: '—')], ['Verified email', $metrics['verified_pct'] === null ? '—' : $metrics['verified_pct'].'%'], ['Marketing opt-ins', $metrics['consent']], ['Downloads: today / 7d / 30d', ($metrics['downloads_today'] ?: '—').' / '.($metrics['downloads_7d'] ?: '—').' / '.($metrics['downloads_30d'] ?: '—')], ['Downloads total', $metrics['downloads_total']], ['Repeat downloaders (2+ tools)', $metrics['repeat']], ['Free tools published', \App\Models\Tool::published()->count()]] as [$label, $value])
    <div class="card-flat p-4"><dt class="meta">{{ $label }}</dt><dd class="mt-1 font-mono tabular-nums text-2xl text-brand-navy">{{ $value === 0 ? '—' : $value }}</dd></div>
    @endforeach
</dl>
<div class="mt-8 grid gap-8 lg:grid-cols-2">
    <section class="card-flat p-5" aria-labelledby="by-res"><h2 id="by-res" class="section-title !text-lg">Most downloaded</h2>
        @if($byResource->isEmpty())<p class="mt-3 text-sm text-brand-muted">No downloads yet.</p>@else<table class="mt-3 w-full text-sm"><thead><tr><th class="text-left py-1">Resource</th><th class="text-right py-1">Downloads</th><th class="text-right py-1">Users</th></tr></thead><tbody class="divide-y divide-brand-line">@foreach($byResource as $r)<tr><td class="py-2"><a href="{{ route('tools.show', $r['slug']) }}">{{ $r['title'] }}</a></td><td class="py-2 text-right font-mono">{{ $r['n'] }}</td><td class="py-2 text-right font-mono">{{ $r['users'] }}</td></tr>@endforeach</tbody></table>@endif
    </section>
    <section class="card-flat p-5" aria-labelledby="by-src"><h2 id="by-src" class="section-title !text-lg">Sign-up source</h2>
        <dl class="mt-3 text-sm divide-y divide-brand-line">@forelse($bySource as $src => $n)<div class="py-2 flex justify-between"><dt>{{ $src }}</dt><dd class="font-mono">{{ $n }}</dd></div>@empty<p class="text-brand-muted">No users yet.</p>@endforelse</dl>
    </section>
    <section class="card-flat p-5" aria-labelledby="funnel-h"><h2 id="funnel-h" class="section-title !text-lg">Funnel, last 30 days</h2>
        <p class="mt-1 meta">Anonymous daily page counts (no cookies, no IPs) plus account and download records.</p>
        <ol class="mt-3 text-sm divide-y divide-brand-line">
            @foreach([['Guides library views', $funnel['library_views']], ['Tool page views', $funnel['tool_views']], ['Download clicks (gate views)', $funnel['gate_views']], ['Sign-ups from a tool gate', $funnel['signups_from_tools']], ['Downloads', $funnel['downloads']], ['Users with a second tool', $funnel['second_downloads']]] as [$label, $n])
            <li class="py-2 flex justify-between"><span>{{ $label }}</span><span class="font-mono">{{ $n ?: '—' }}</span></li>
            @endforeach
        </ol>
        @if($topPages->isNotEmpty())<p class="mt-3 text-xs font-semibold uppercase tracking-wide text-brand-muted">Most viewed</p><ul class="mt-1 text-xs divide-y divide-brand-line">@foreach($topPages as $path => $n)<li class="py-1 flex justify-between gap-2"><span class="font-mono truncate">{{ $path }}</span><span class="font-mono">{{ $n }}</span></li>@endforeach</ul>@endif
    </section>
</div>
<section class="mt-8" aria-labelledby="recent"><h2 id="recent" class="section-title !text-lg">Download activity</h2>
    @if($recent->isEmpty())<p class="mt-2 text-sm text-brand-muted">No downloads recorded.</p>@else
    <div class="table-wrap mt-3"><table><thead><tr><th>When</th><th>User</th><th>Resource</th><th>File</th><th>Version</th><th>Served</th><th>Referrer</th></tr></thead><tbody>
    @foreach($recent as $d)<tr><td class="whitespace-nowrap font-mono">{{ $d->created_at->format('Y-m-d H:i') }}</td><td>{{ $d->user?->name ?? 'Unavailable' }} <span class="meta">{{ $d->user?->email }}</span></td><td>{{ $d->tool?->title ?? $d->resource_slug }}</td><td class="font-mono">{{ $d->file_name }}</td><td class="font-mono">{{ $d->version }}</td><td>{{ $d->downloaded_at?->format('H:i') ?? '—' }}</td><td class="meta">{{ $d->referrer ? \Illuminate\Support\Str::limit($d->referrer, 40) : '—' }}</td></tr>@endforeach
    </tbody></table></div><nav class="mt-3" aria-label="Downloads pagination">{{ $recent->links() }}</nav>@endif
</section>
<section class="mt-8" aria-labelledby="users"><h2 id="users" class="section-title !text-lg">Registered users</h2>
    @if($users->isEmpty())<p class="mt-2 text-sm text-brand-muted">No users.</p>@else
    <div class="table-wrap mt-3"><table><thead><tr><th>Signed up</th><th>Name</th><th>Email</th><th>Organisation</th><th>Verified</th><th>Terms</th><th>Updates</th><th>Source</th><th>Downloads</th></tr></thead><tbody>
    @foreach($users as $u)<tr><td class="whitespace-nowrap font-mono">{{ $u->created_at?->format('Y-m-d') ?? '—' }}</td><td>{{ $u->name }}</td><td><a href="mailto:{{ $u->email }}">{{ $u->email }}</a></td><td>{{ $u->organization_name ?: '—' }}</td><td>{{ $u->email_verified_at ? 'yes' : '—' }}</td><td>{{ $u->terms_accepted_at?->format('Y-m-d') ?? '—' }}</td><td>{{ $u->marketing_consent_at ? 'opted in' : '—' }}</td><td>{{ $u->signup_source ?: '—' }}</td><td class="font-mono text-right">{{ $u->resource_downloads_count ?: '—' }}</td></tr>@endforeach
    </tbody></table></div><nav class="mt-3" aria-label="Users pagination">{{ $users->links() }}</nav>@endif
    <p class="mt-3 meta">Only data with a product purpose is stored: name, email, optional organisation, consent timestamps, sign-up source and download history. IPs are stored as hashes with each download.</p>
</section>
@endsection
