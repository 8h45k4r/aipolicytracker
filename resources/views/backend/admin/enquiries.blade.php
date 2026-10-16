@extends('backend.layouts.app', ['title' => 'Licence enquiries'])
@section('content')
<x-backend.page-header title="Licence enquiries" description="Team, commercial licence and coverage enquiries from the form on /pricing and /open-data. Each one is stored before anyone is told; reply from the contact mailbox, where the notification's reply address is the enquirer." />

<div class="mt-5 grid gap-4 sm:grid-cols-3">
    <x-backend.stat label="Received, last 30 days" :value="number_format($summary['month'])" :href="route('backend.admin.enquiries.index', ['from' => now()->subDays(29)->toDateString()])" />
    <x-backend.stat label="Commercial licence" :value="number_format($byTier['commercial'] ?? 0)" :href="route('backend.admin.enquiries.index', ['tier' => 'commercial'])" />
    <x-backend.stat label="Not mailed to the contact address" :value="number_format($summary['unsent'])" :tone="$summary['unsent'] ? 'text-state-warn' : null" hint="Stored, but the notification failed or was held back in a flood" />
</div>

<x-backend.filters :action="route('backend.admin.enquiries.index')" :filters="$filters" :export="route('backend.admin.enquiries.export')" placeholder="Name, email, organisation or use" :total="$enquiries->total()" noun="enquiry">
    <div><label for="f-tier" class="adm-label">Tier</label><select id="f-tier" name="tier" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Any tier</option>@foreach(\App\Models\LicenceEnquiry::TIERS as $k => $label)<option value="{{ $k }}" @selected(request('tier') === $k)>{{ $label }} ({{ $byTier[$k] ?? 0 }})</option>@endforeach</select></div>
    <div><label for="f-coverage" class="adm-label">Coverage</label><select id="f-coverage" name="coverage" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Any</option><option value="1" @selected(request('coverage') === '1')>Asked for coverage</option></select></div>
</x-backend.filters>

<div class="table-wrap mt-4"><table><caption class="sr-only">Licence enquiries</caption>
    <thead><tr>
        <x-backend.sort-th key="received" label="Received" :filters="$filters" />
        <x-backend.sort-th key="tier" label="Tier" :filters="$filters" />
        <x-backend.sort-th key="organisation" label="Organisation" :filters="$filters" />
        <x-backend.sort-th key="email" label="From" :filters="$filters" />
        <th scope="col">What for</th>
        <th scope="col">Mailed</th>
    </tr></thead>
    <tbody>
    @forelse($enquiries as $e)
    <tr>
        <td class="whitespace-nowrap"><time datetime="{{ $e->created_at->toIso8601String() }}" title="{{ $e->created_at->toDayDateTimeString() }}">{{ $e->created_at->format('j M Y H:i') }}</time><div class="meta">/{{ $e->source ?? 'pricing' }}</div></td>
        <td><x-backend.badge status="info">{{ $e->tierLabel() }}</x-backend.badge></td>
        <td class="font-medium text-brand-navy">{{ $e->organization }}</td>
        <td>{{ $e->name }}<div class="meta"><a href="mailto:{{ $e->email }}">{{ $e->email }}</a></div></td>
        <td class="text-sm max-w-[60ch]">
            <details><summary class="cursor-pointer">{{ \Illuminate\Support\Str::limit($e->use_case, 90) }}</summary><p class="mt-2 whitespace-pre-line">{{ $e->use_case }}</p></details>
            @if($e->coverage_request)<p class="mt-1 meta"><span class="font-medium">Coverage:</span> {{ $e->coverage_request }}</p>@endif
        </td>
        <td class="whitespace-nowrap">@if($e->notified_at)<time datetime="{{ $e->notified_at->toIso8601String() }}">{{ $e->notified_at->format('j M H:i') }}</time>@else<span class="text-brand-muted">—</span>@endif</td>
    </tr>
    @empty
    <tr><td colspan="6"><x-site.empty title="{{ $filters->active() || request()->hasAny(['tier', 'coverage']) ? 'No enquiries match these filters' : 'No enquiries yet' }}">{{ $filters->active() ? 'Widen the dates or clear a filter.' : 'Enquiries sent from the form on /pricing and /open-data appear here.' }}</x-site.empty></td></tr>
    @endforelse
    </tbody></table></div>
<nav class="mt-4" aria-label="Pagination">{{ $enquiries->links() }}</nav>
@endsection
