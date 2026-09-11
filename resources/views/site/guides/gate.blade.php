@extends('site.layouts.app')
@section('content')
<div class="container-site py-12 max-w-xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-4">Free download</p>
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Create a free account to download</h1>
    <p class="mt-3 text-brand-body leading-7">Get instant access to the free <strong>{{ $tool->title }}</strong> ({{ $tool->formatList() }}) and receive updates when related AI policy requirements change.</p>
    <div class="mt-6 flex flex-col gap-2 max-w-sm">
        <a href="{{ route('register') }}" class="btn-primary" data-track="gate_register">Continue with email</a>
        <a href="{{ route('login') }}" class="btn-secondary" data-track="gate_login">Sign in to an existing account</a>
    </div>
    <p class="mt-4 text-xs text-brand-muted">By downloading, you agree to the template licence and acknowledge that AIPolicyTracker provides informational resources, not legal advice. We store your email, name and download history; marketing updates are opt-in.</p>
    <p class="mt-6 text-sm"><a href="{{ $tool->url() }}">Back to the preview</a></p>
</div>
@endsection
