@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-4xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">AI policy changes in {{ $year }}</h1>
    @php($urgentCount = $changes->whereIn('impact_level', ['urgent', 'high'])->count())
    <p class="mt-2 text-brand-body">{{ $changes->count() }} recorded {{ \Illuminate\Support\Str::plural('change', $changes->count()) }} across {{ $changes->pluck('jurisdiction.name')->unique()->count() }} {{ \Illuminate\Support\Str::plural('jurisdiction', $changes->pluck('jurisdiction.name')->unique()->count()) }}, each linked to an official source.@if($urgentCount) {{ $urgentCount }} marked urgent or high-impact.@endif</p>
    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
        @foreach($years as $y)<a class="chip {{ (int) $y === $year ? 'chip-active' : '' }}" href="{{ route('changes.year', $y) }}">{{ $y }}</a>@endforeach
        <a class="chip" href="{{ route('changes.index') }}">All</a>
        <span class="text-brand-muted" aria-hidden="true">·</span>
        <a href="{{ route('changes.export', ['year' => $year]) }}" class="text-brand-blue hover:underline">CSV</a>
        <a href="{{ route('changes.feed') }}" class="text-brand-blue hover:underline">RSS</a>
        <button type="button" data-copy-link class="text-brand-blue hover:text-brand-navy">Copy link</button>
        <button type="button" data-share class="text-brand-blue hover:text-brand-navy hidden">Share</button>
    </div>
    <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
        <div class="card-flat py-3"><dt class="text-xs text-brand-muted">Changes</dt><dd class="text-2xl font-semibold text-brand-navy">{{ $changes->count() }}</dd></div>
        <div class="card-flat py-3"><dt class="text-xs text-brand-muted">Jurisdictions</dt><dd class="text-2xl font-semibold text-brand-navy">{{ $changes->pluck('jurisdiction.name')->unique()->count() }}</dd></div>
        <div class="card-flat py-3"><dt class="text-xs text-brand-muted">Urgent / high</dt><dd class="text-2xl font-semibold text-brand-navy">{{ $urgentCount }}</dd></div>
        <div class="card-flat py-3"><dt class="text-xs text-brand-muted">Instruments affected</dt><dd class="text-2xl font-semibold text-brand-navy">{{ $changes->pluck('policyInstrument.slug')->filter()->unique()->count() }}</dd></div>
    </dl>
    <div class="mt-6 divide-y divide-brand-line border-y border-brand-line">@foreach($changes as $c)<x-site.change-item :change="$c" />@endforeach</div>
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
