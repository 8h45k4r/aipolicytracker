@extends('backend.layouts.app', ['title' => 'Guides and downloads'])
@section('content')
<x-backend.page-header title="Guides and downloads" description="Who requests and downloads the templates and free tools, and how they found them. Each view filters, sorts and exports on its own.">
    <x-slot:actions>@can('tools.manage')<a href="{{ route('backend.admin.tools.index') }}" class="btn-secondary">Manage the tool library</a>@endcan</x-slot:actions>
</x-backend.page-header>

<nav class="adm-tabs" aria-label="Views">
    <a href="{{ route('backend.admin.downloads') }}" @if($view === 'overview') aria-current="page" @endif>Overview</a>
    <a href="{{ route('backend.admin.downloads', ['view' => 'requests']) }}" @if($view === 'requests') aria-current="page" @endif>Template requests<span class="adm-count">{{ number_format($counts['requests']) }}</span></a>
    <a href="{{ route('backend.admin.downloads', ['view' => 'downloads']) }}" @if($view === 'downloads') aria-current="page" @endif>Tool downloads<span class="adm-count">{{ number_format($counts['downloads']) }}</span></a>
    <a href="{{ route('backend.admin.downloads', ['view' => 'users']) }}" @if($view === 'users') aria-current="page" @endif>Registered users<span class="adm-count">{{ number_format($counts['users']) }}</span></a>
</nav>

@if($view === 'overview')
    @unless($turnstile)<p class="mt-4 rounded-md border border-state-warn/30 bg-state-warnbg px-3 py-2 text-sm text-state-warn">Cloudflare Turnstile is not configured, so the request form is protected by the honeypot, the email checks and rate limits only. @can('settings.manage')Add the keys in <a href="{{ route('backend.admin.settings') }}#turnstile">Settings → Bot protection</a>.@else An owner can add the keys in Settings → Bot protection.@endcan</p>@endunless
    <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-backend.stat label="Template requests, 30 days" :value="number_format($metrics['requests_30d'])" :trend="$trends['requests']" :href="route('backend.admin.downloads', ['view' => 'requests', 'from' => now()->subDays(29)->toDateString()])" :hint="number_format($metrics['requests_downloaded']).' of all requests used their link'" />
        <x-backend.stat label="Tool downloads, 30 days" :value="number_format($metrics['downloads_30d'])" :trend="$trends['downloads']" :href="route('backend.admin.downloads', ['view' => 'downloads', 'from' => now()->subDays(29)->toDateString()])" />
        <x-backend.stat label="New users, 30 days" :value="number_format($metrics['users_30d'])" :trend="$trends['users']" :href="route('backend.admin.downloads', ['view' => 'users', 'from' => now()->subDays(29)->toDateString()])" :hint="($metrics['verified_pct'] === null ? '—' : $metrics['verified_pct'].'%').' of all users verified'" />
        <x-backend.stat label="Opted in to updates" :value="number_format($metrics['consent'] + $metrics['requests_opted_in'])" :href="route('backend.admin.downloads', ['view' => 'requests', 'updates' => 'yes'])" :hint="number_format($metrics['consent']).' users, '.number_format($metrics['requests_opted_in']).' template requests'" />
    </div>
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="card-flat p-5" aria-labelledby="by-template">
            <h2 id="by-template" class="text-base font-semibold text-brand-navy">Most requested templates</h2>
            @if($byTemplate->isEmpty())<p class="mt-3 text-sm text-brand-muted">No template requests yet.</p>@else
            <table class="mt-3 w-full text-sm"><caption class="sr-only">Most requested templates</caption><thead><tr class="text-xs text-brand-muted"><th scope="col" class="py-1 text-left font-medium">Template</th><th scope="col" class="text-right font-medium">Requests</th><th scope="col" class="text-right font-medium">Used</th></tr></thead>
            <tbody class="divide-y divide-brand-line">@foreach($byTemplate as $t)<tr><td class="py-1.5"><a href="{{ route('backend.admin.downloads', ['view' => 'requests', 'template' => $t->template_slug]) }}" class="no-underline hover:underline">{{ \App\Services\Templates\TemplateCatalog::find($t->template_slug)['title'] ?? $t->template_slug }}</a></td><td class="text-right tabular-nums">{{ $t->n }}</td><td class="text-right tabular-nums text-brand-muted">{{ (int) $t->used }}</td></tr>@endforeach</tbody></table>
            @endif
        </section>
        <section class="card-flat p-5" aria-labelledby="by-res">
            <h2 id="by-res" class="text-base font-semibold text-brand-navy">Most downloaded tools</h2>
            @if($byResource->isEmpty())<p class="mt-3 text-sm text-brand-muted">No tool downloads yet.</p>@else
            <table class="mt-3 w-full text-sm"><caption class="sr-only">Most downloaded tools</caption><thead><tr class="text-xs text-brand-muted"><th scope="col" class="py-1 text-left font-medium">Tool</th><th scope="col" class="text-right font-medium">Downloads</th><th scope="col" class="text-right font-medium">Users</th></tr></thead>
            <tbody class="divide-y divide-brand-line">@foreach($byResource as $r)<tr><td class="py-1.5"><a href="{{ route('backend.admin.downloads', ['view' => 'downloads', 'resource' => $r['slug']]) }}" class="no-underline hover:underline">{{ $r['title'] }}</a></td><td class="text-right tabular-nums">{{ $r['n'] }}</td><td class="text-right tabular-nums text-brand-muted">{{ $r['users'] }}</td></tr>@endforeach</tbody></table>
            @endif
        </section>
        <section class="card-flat p-5" aria-labelledby="funnel-h">
            <h2 id="funnel-h" class="text-base font-semibold text-brand-navy">Tool funnel, last 30 days</h2>
            <p class="mt-1 text-xs text-brand-muted">Anonymous daily page counts (no cookies, no IPs) plus account and download records.</p>
            @php($top = max(1, $funnel['library_views'], $funnel['tool_views']))
            <ol class="mt-3 space-y-2 text-sm">
                @foreach([['Guides library views', $funnel['library_views']], ['Tool page views', $funnel['tool_views']], ['Download clicks (gate views)', $funnel['gate_views']], ['Sign-ups from a tool gate', $funnel['signups_from_tools']], ['Downloads', $funnel['downloads']], ['Users with a second tool', $funnel['second_downloads']]] as [$label, $n])
                <li><div class="flex justify-between"><span>{{ $label }}</span><span class="tabular-nums font-medium">{{ number_format($n) }}</span></div><div class="mt-1 h-1.5 rounded-full bg-brand-paper"><div class="h-full rounded-full bg-brand-blue" style="width: {{ min(100, round(100 * $n / $top)) }}%"></div></div></li>
                @endforeach
            </ol>
        </section>
        <section class="card-flat p-5" aria-labelledby="by-src">
            <h2 id="by-src" class="text-base font-semibold text-brand-navy">Where users signed up</h2>
            <ul class="mt-3 divide-y divide-brand-line text-sm">@forelse($bySource as $src => $n)<li class="flex justify-between py-1.5"><a href="{{ route('backend.admin.downloads', ['view' => 'users', 'source' => $src]) }}" class="no-underline hover:underline">{{ $src }}</a><span class="tabular-nums">{{ $n }}</span></li>@empty<li class="py-1.5 text-brand-muted">No users yet.</li>@endforelse</ul>
            @if($topPages->isNotEmpty())<h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-brand-muted">Most viewed pages, 30 days</h3><ul class="mt-1 divide-y divide-brand-line text-xs">@foreach($topPages as $path => $n)<li class="flex justify-between gap-2 py-1"><a href="{{ url($path) }}" class="font-mono truncate no-underline hover:underline" rel="noopener" target="_blank">{{ $path }}</a><span class="tabular-nums">{{ $n }}</span></li>@endforeach</ul>@endif
        </section>
    </div>

@elseif($view === 'requests')
    <x-backend.filters :action="route('backend.admin.downloads')" :filters="$filters" :export="route('backend.admin.downloads.export')" placeholder="Name, email, company, title or country" :total="$rows->total()" noun="request">
        <input type="hidden" name="view" value="requests">
        <div><label for="f-template" class="adm-label">Template</label><select id="f-template" name="template" class="input !min-h-[38px] !py-1.5 !w-auto max-w-[16rem]"><option value="">Any</option>@foreach($templates as $slug)<option value="{{ $slug }}" @selected(request('template') === $slug)>{{ \App\Services\Templates\TemplateCatalog::find($slug)['title'] ?? $slug }}</option>@endforeach</select></div>
        <div><label for="f-used" class="adm-label">Link used</label><select id="f-used" name="used" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Either</option><option value="yes" @selected(request('used') === 'yes')>Yes</option><option value="no" @selected(request('used') === 'no')>Not yet</option></select></div>
        <div><label for="f-updates" class="adm-label">Updates</label><select id="f-updates" name="updates" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Either</option><option value="yes" @selected(request('updates') === 'yes')>Opted in</option><option value="no" @selected(request('updates') === 'no')>Not opted in</option></select></div>
    </x-backend.filters>
    @if($rows->isEmpty())<div class="mt-6"><x-site.empty title="No template requests match" :reset="route('backend.admin.downloads', ['view' => 'requests'])">They appear here as soon as someone requests a template.</x-site.empty></div>@else
    <div class="table-wrap mt-4"><table><caption class="sr-only">Template requests</caption><thead><tr>
        <x-backend.sort-th key="requested" label="Requested" :filters="$filters" /><x-backend.sort-th key="template" label="Template" :filters="$filters" /><th scope="col">Name</th><th scope="col">Work email</th><x-backend.sort-th key="company" label="Company" :filters="$filters" /><th scope="col">Title · country</th><th scope="col">Updates</th><x-backend.sort-th key="downloads" label="Downloads" :filters="$filters" class="text-right" />
    </tr></thead><tbody>
    @foreach($rows as $r)<tr>
        <td class="whitespace-nowrap font-mono text-xs">{{ $r->created_at?->format('Y-m-d H:i') }}</td>
        <td><a href="{{ request()->fullUrlWithQuery(['template' => $r->template_slug, 'page' => null]) }}" class="no-underline hover:underline" title="Only requests for this template">{{ $r->template()['title'] ?? $r->template_slug }}</a></td>
        <td>{{ $r->name }}</td>
        <td><a href="mailto:{{ $r->email }}" class="font-mono text-xs">{{ $r->email }}</a></td>
        <td>@if($r->company)<a href="{{ request()->fullUrlWithQuery(['q' => $r->company, 'page' => null]) }}" class="no-underline hover:underline" title="Every request from this company">{{ $r->company }}</a>@endif</td>
        <td class="text-xs">{{ $r->job_title ?: '—' }}@if($r->country) · {{ $r->country }}@endif</td>
        <td>@if($r->marketing_consent_at)<x-backend.badge status="yes">yes</x-backend.badge>@else<span class="text-brand-muted">—</span>@endif</td>
        <td class="text-right font-mono">{{ $r->downloads ?: '—' }}</td>
    </tr>@endforeach
    </tbody></table></div>
    <nav class="mt-4" aria-label="Pagination">{{ $rows->links() }}</nav>
    @endif

@elseif($view === 'downloads')
    <x-backend.filters :action="route('backend.admin.downloads')" :filters="$filters" :export="route('backend.admin.downloads.export')" placeholder="Tool, file, person or organisation" :total="$rows->total()" noun="download">
        <input type="hidden" name="view" value="downloads">
        @if(request('user'))<input type="hidden" name="user" value="{{ request('user') }}">@endif
        <div><label for="f-resource" class="adm-label">Tool</label><select id="f-resource" name="resource" class="input !min-h-[38px] !py-1.5 !w-auto max-w-[16rem]"><option value="">Any</option>@foreach($resources as $slug)<option value="{{ $slug }}" @selected(request('resource') === $slug)>{{ $slug }}</option>@endforeach</select></div>
    </x-backend.filters>
    @if($rows->isEmpty())<div class="mt-6"><x-site.empty title="No downloads match" :reset="route('backend.admin.downloads', ['view' => 'downloads'])">Downloads of the free tools appear here.</x-site.empty></div>@else
    <div class="table-wrap mt-4"><table><caption class="sr-only">Tool downloads</caption><thead><tr>
        <x-backend.sort-th key="when" label="When" :filters="$filters" /><th scope="col">User</th><x-backend.sort-th key="resource" label="Tool" :filters="$filters" /><th scope="col">File</th><x-backend.sort-th key="version" label="Version" :filters="$filters" /><th scope="col">Referrer</th>
    </tr></thead><tbody>
    @foreach($rows as $d)<tr>
        <td class="whitespace-nowrap font-mono text-xs">{{ $d->created_at->format('Y-m-d H:i') }}</td>
        <td>@if($d->user)<a href="{{ request()->fullUrlWithQuery(['user' => $d->user_id, 'page' => null]) }}" class="no-underline hover:underline" title="Every download by this person">{{ $d->user->name }}</a> <span class="block text-xs text-brand-muted">{{ $d->user->email }}</span>@else<span class="text-brand-muted">Unavailable</span>@endif</td>
        <td><a href="{{ request()->fullUrlWithQuery(['resource' => $d->resource_slug, 'page' => null]) }}" class="no-underline hover:underline">{{ $d->tool?->title ?? $d->resource_slug }}</a></td>
        <td class="font-mono text-xs">{{ $d->file_name }}</td>
        <td class="font-mono text-xs">{{ $d->version }}</td>
        <td class="text-xs text-brand-muted">{{ $d->referrer ? \Illuminate\Support\Str::limit($d->referrer, 40) : '—' }}</td>
    </tr>@endforeach
    </tbody></table></div>
    <nav class="mt-4" aria-label="Pagination">{{ $rows->links() }}</nav>
    @endif

@else
    <x-backend.filters :action="route('backend.admin.downloads')" :filters="$filters" :export="route('backend.admin.downloads.export')" placeholder="Name, email or organisation" :total="$rows->total()" noun="user">
        <input type="hidden" name="view" value="users">
        <div><label for="f-source" class="adm-label">Signed up from</label><select id="f-source" name="source" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Anywhere</option><option value="legacy" @selected(request('source') === 'legacy')>legacy</option>@foreach($sources as $src)<option value="{{ $src }}" @selected(request('source') === $src)>{{ $src }}</option>@endforeach</select></div>
        <div><label for="f-verified" class="adm-label">Email verified</label><select id="f-verified" name="verified" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Either</option><option value="yes" @selected(request('verified') === 'yes')>Yes</option><option value="no" @selected(request('verified') === 'no')>No</option></select></div>
        <div><label for="f-updates" class="adm-label">Updates</label><select id="f-updates" name="updates" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Either</option><option value="yes" @selected(request('updates') === 'yes')>Opted in</option><option value="no" @selected(request('updates') === 'no')>Not opted in</option></select></div>
    </x-backend.filters>
    @if($rows->isEmpty())<div class="mt-6"><x-site.empty title="No users match" :reset="route('backend.admin.downloads', ['view' => 'users'])">Clear the filters to see every registered user.</x-site.empty></div>@else
    <div class="table-wrap mt-4"><table><caption class="sr-only">Registered users</caption><thead><tr>
        <x-backend.sort-th key="joined" label="Signed up" :filters="$filters" /><x-backend.sort-th key="name" label="Name" :filters="$filters" /><x-backend.sort-th key="email" label="Email" :filters="$filters" /><th scope="col">Organisation</th><th scope="col">Verified</th><th scope="col">Updates</th><th scope="col">Source</th><x-backend.sort-th key="downloads" label="Downloads" :filters="$filters" class="text-right" />
    </tr></thead><tbody>
    @foreach($rows as $u)<tr>
        <td class="whitespace-nowrap font-mono text-xs">{{ $u->created_at?->format('Y-m-d') ?? '—' }}</td>
        <td>@can('users.manage')<a href="{{ route('backend.admin.users.show', $u) }}">{{ $u->name }}</a>@else{{ $u->name }}@endcan</td>
        <td><a href="mailto:{{ $u->email }}" class="font-mono text-xs">{{ $u->email }}</a></td>
        <td>{{ $u->organization_name ?: '—' }}</td>
        <td>{{ $u->email_verified_at ? 'yes' : '—' }}</td>
        <td>{{ $u->marketing_consent_at ? 'yes' : '—' }}</td>
        <td class="text-xs"><a href="{{ request()->fullUrlWithQuery(['source' => $u->signup_source ?? 'legacy', 'page' => null]) }}" class="no-underline hover:underline">{{ $u->signup_source ?? 'legacy' }}</a></td>
        <td class="text-right font-mono">@if($u->resource_downloads_count)<a href="{{ route('backend.admin.downloads', ['view' => 'downloads', 'user' => $u->id]) }}" title="This person's downloads">{{ $u->resource_downloads_count }}</a>@else — @endif</td>
    </tr>@endforeach
    </tbody></table></div>
    <nav class="mt-4" aria-label="Pagination">{{ $rows->links() }}</nav>
    <p class="mt-3 text-xs text-brand-muted">Only data with a product purpose is stored: name, email, optional organisation, consent timestamps, sign-up source and download history. IPs are stored as hashes with each download.</p>
    @endif
@endif
@endsection
