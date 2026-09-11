@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">{{ $config['title'] }}</h1>
    <p class="mt-3 max-w-3xl prose-policy">{{ $config['intro'] }}</p>
    @include('site.compare._table')
    <div class="mt-6 flex flex-wrap gap-2">@foreach($jurisdictions as $j)<a href="{{ $j->url() }}" class="btn-secondary">AI regulation in {{ $j->nameWithArticle() }}</a>@endforeach<a href="{{ route('compare.index', ['j' => $jurisdictions->pluck('slug')->implode(',')]) }}" class="btn-secondary" rel="nofollow">Adjust comparison</a></div>
    <x-site.faq :items="$config['faq'] ?? []" />
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
