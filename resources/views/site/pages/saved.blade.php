@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-3xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">Reading list</p>
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Saved records</h1>
    <p class="mt-3 text-brand-body leading-7">Policies, jurisdictions and obligations you saved with the "Save" button. The list lives only in this browser: nothing is sent to the server and it will not follow you to another device. For updates by email, <a href="{{ route('subscribe.show') }}">subscribe to the weekly digest</a>, or <a href="{{ route('pricing') }}">follow records with Pro</a> to get a daily alert the day one changes.</p>
    <div class="mt-6 flex flex-wrap gap-2" data-saved-tools hidden>
        <button type="button" class="btn-secondary" data-saved-copy>Copy list as Markdown</button>
        <button type="button" class="btn-secondary" data-saved-json>Copy list as JSON</button>
        <button type="button" class="btn-secondary" data-saved-clear>Clear all</button>
    </div>
    <ul class="mt-4 divide-y divide-brand-line border-y border-brand-line" data-saved-list aria-label="Saved records"></ul>
    <div class="mt-6" data-saved-empty>
        <x-site.empty title="Nothing saved yet">Open any policy, jurisdiction or obligation and use the Save button in its sidebar. JavaScript and browser storage must be enabled.</x-site.empty>
        <div class="mt-4 flex flex-wrap gap-2"><a href="{{ route('policies.index') }}" class="btn-primary">Browse policies</a><a href="{{ route('jurisdictions.index') }}" class="btn-secondary">Browse jurisdictions</a></div>
    </div>
</div>
@endsection
