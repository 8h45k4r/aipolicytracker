@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">AI policy changes in {{ $year }}</h1>
    <p class="mt-2 text-slate-700">{{ $changes->count() }} recorded {{ \Illuminate\Support\Str::plural('change', $changes->count()) }} across {{ $changes->pluck('jurisdiction.name')->unique()->count() }} {{ \Illuminate\Support\Str::plural('jurisdiction', $changes->pluck('jurisdiction.name')->unique()->count()) }}, each linked to an official source.</p>
    <div class="mt-3 flex flex-wrap gap-2 text-sm">@foreach($years as $y)<a class="chip {{ (int) $y === $year ? 'chip-active' : '' }}" href="{{ route('changes.year', $y) }}">{{ $y }}</a>@endforeach<a class="chip" href="{{ route('changes.index') }}">All</a></div>
    <div class="mt-6 divide-y divide-slate-200 border-y border-slate-200">@foreach($changes as $c)<x-site.change-item :change="$c" />@endforeach</div>
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
