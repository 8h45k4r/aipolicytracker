@extends('backend.layouts.app', ['title' => 'Consultations'])
@section('content')
<x-backend.page-header title="Consultations" description="Open AI comment periods and upcoming public meetings collected from the US Federal Register. Open the official page before deciding: confirm when it is about AI governance and the close date matches, hide when it is not. A collected entry shows on the public calendar labelled as not reviewed." />

<div class="mt-5 grid gap-4 sm:grid-cols-3">
    <x-backend.stat label="Open, to review" :value="number_format($counts['collected'] ?? 0)" :href="route('backend.admin.consultations.index', ['status' => 'collected'])" :tone="($counts['collected'] ?? 0) ? 'text-state-warn' : null" />
    <x-backend.stat label="Open, confirmed" :value="number_format($counts['confirmed'] ?? 0)" :href="route('backend.admin.consultations.index', ['status' => 'confirmed'])" />
    <x-backend.stat label="Open, hidden" :value="number_format($counts['hidden'] ?? 0)" :href="route('backend.admin.consultations.index', ['status' => 'hidden'])" />
</div>

<div class="table-wrap mt-6"><table><caption class="sr-only">Consultations, {{ $status }}</caption>
    <thead><tr><th scope="col">Date</th><th scope="col">Consultation</th><th scope="col">Source</th><th scope="col"><span class="sr-only">Decision</span></th></tr></thead>
    <tbody>
    @forelse($consultations as $c)
    <tr>
        <td class="whitespace-nowrap">{{ $c->kind === 'meeting' ? 'Held' : 'Closes' }} <time datetime="{{ $c->listedOn()?->toDateString() }}">{{ $c->listedOn()?->format('j M Y') }}</time>@if($c->dates_text && $c->kind === 'meeting')<div class="meta max-w-[24ch] whitespace-normal">As published: {{ $c->dates_text }}</div>@endif</td>
        <td class="max-w-[70ch]"><a href="{{ $c->url }}" rel="noopener" target="_blank" class="font-medium">{{ $c->title }}</a>
            <div class="meta">{{ \App\Models\Consultation::KINDS[$c->kind] ?? $c->kind }} · {{ $c->agency }} · {{ $c->document_type }}@if($c->reviewer) · {{ $c->status }} by {{ $c->reviewer->name }} {{ $c->reviewed_at?->format('j M Y') }}@endif</div>
            @if($c->abstract)<details class="mt-1 text-sm"><summary class="cursor-pointer">Abstract</summary><p class="mt-1">{{ $c->abstract }}</p></details>@endif</td>
        <td class="whitespace-nowrap">{{ $c->sourceLabel() }}</td>
        <td class="whitespace-nowrap">
            @foreach(['confirmed' => 'Confirm', 'hidden' => 'Hide', 'collected' => 'Back to review'] as $to => $label)
            @if($c->status !== $to)<form method="post" action="{{ route('backend.admin.consultations.review', $c) }}" class="inline">@csrf<input type="hidden" name="status" value="{{ $to }}"><button type="submit" class="btn-secondary !min-h-[32px] !py-1 text-sm">{{ $label }}</button></form>@endif
            @endforeach
        </td>
    </tr>
    @empty
    <tr><td colspan="4" class="text-brand-muted">Nothing here.</td></tr>
    @endforelse
    </tbody>
</table></div>
<div class="mt-4">{{ $consultations->links() }}</div>
@endsection
