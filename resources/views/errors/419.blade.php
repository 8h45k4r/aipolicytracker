@php($seo = \App\Support\Seo::make('Page expired', 'The form you submitted expired. Reload the page and try again.', url()->current(), false))
@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-16 text-center">
    <p class="eyebrow">419</p>
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">That form expired before it was sent</h1>
    <p class="mt-3 text-brand-body">The page had been open long enough that its security token went stale. Nothing was submitted and nothing was lost. Go back, reload the page and send it again.</p>
    <div class="mt-6 flex flex-wrap justify-center gap-2"><a href="{{ url()->previous() }}" class="btn-primary">Go back and retry</a><a href="{{ route('home') }}" class="btn-secondary">Home</a></div>
</div>
@endsection
