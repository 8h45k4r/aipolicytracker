@php($seo = \App\Support\Seo::make('Not allowed', 'You do not have access to this page.', url()->current(), false))
@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-16 text-center">
    <p class="eyebrow">403</p>
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">You do not have access to this page</h1>
    <p class="mt-3 text-brand-body">Either it needs an account with different permissions, or the link was meant for someone else. Every policy record on this site is public and needs no account at all.</p>
    <div class="mt-6 flex flex-wrap justify-center gap-2"><a href="{{ route('home') }}" class="btn-primary">Home</a><a href="{{ route('policies.index') }}" class="btn-secondary">Policies</a><a href="{{ route('open-data') }}" class="btn-secondary">Open data</a></div>
</div>
@endsection
