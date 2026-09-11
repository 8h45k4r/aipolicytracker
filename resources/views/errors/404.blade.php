@php($seo = \App\Support\Seo::make('Page not found', 'The page you requested does not exist. Browse AI policies, jurisdictions and obligations instead.', url()->current(), false))
@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-16 text-center">
    <p class="eyebrow">404</p>
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Page not found</h1>
    <p class="mt-3 text-brand-body">The page may have moved when the site was restructured, or the record may not be published yet.</p>
    <form action="{{ route('policies.index') }}" method="get" role="search" class="mt-6 flex gap-2 max-w-md mx-auto"><label for="e-q" class="sr-only">Search policies</label><input id="e-q" name="q" type="search" class="input" placeholder="Search policies"><button type="submit" class="btn-primary">Search</button></form>
    <div class="mt-6 flex flex-wrap justify-center gap-2"><a href="{{ route('home') }}" class="btn-secondary">Home</a><a href="{{ route('jurisdictions.index') }}" class="btn-secondary">Jurisdictions</a><a href="{{ route('changes.index') }}" class="btn-secondary">Change log</a></div>
</div>
@endsection
