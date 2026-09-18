@php($seo = \App\Support\Seo::make('Too many requests', 'You have made too many requests in a short period. Wait a moment and try again.', url()->current(), false))
@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-16 text-center">
    <p class="eyebrow">429</p>
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Too many requests, too quickly</h1>
    <p class="mt-3 text-brand-body">The rate limit is 120 requests a minute per address. Wait a minute and carry on.</p>
    <p class="mt-3 text-sm text-brand-body">If you are pulling data in bulk, do not scrape the pages: the whole corpus is published as one download and as newline-delimited JSON, which is faster for you and cheaper for everyone.</p>
    <div class="mt-6 flex flex-wrap justify-center gap-2"><a href="{{ route('open-data') }}" class="btn-primary">Bulk downloads</a><a href="{{ route('openapi') }}" class="btn-secondary">API reference</a></div>
</div>
@endsection
