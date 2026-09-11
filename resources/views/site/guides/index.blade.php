@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">Guides</h1>
    <p class="mt-2 text-slate-700">Practical, source-backed guides that connect recorded obligations to what a team actually does. Each guide links to the live records it draws on.</p>
    <ul class="mt-6 space-y-4">@foreach($guides as $g)<li class="card-flat p-4"><a href="{{ route('guides.show', $g['slug']) }}" class="text-lg font-semibold text-slate-900 hover:underline">{{ $g['h1'] }}</a><p class="mt-1 text-sm text-slate-700">{{ $g['summary'] }}</p></li>@endforeach</ul>
</div>
@endsection
