@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="mt-2 eyebrow">Templates</p>
    <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $landing['label'] }} templates</h1>

    <section class="mt-5 card-flat p-5" aria-labelledby="summary-heading">
        <h2 id="summary-heading" class="sr-only">Summary</h2>
        <p class="text-brand-navy text-base sm:text-lg leading-relaxed">{{ $intro }}</p>
    </section>

    <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($items as $item)<x-site.template-card :item="$item" />@endforeach</ul>

    <nav class="mt-10" aria-labelledby="more-heading">
        <h2 id="more-heading" class="section-title">More templates</h2>
        <div class="mt-3 flex flex-wrap gap-2 text-sm">
            <a href="{{ route('templates.index') }}" class="chip">All templates</a>
            @foreach($others as $f)<a href="{{ $f['url'] }}" class="chip">{{ $f['label'] }} <span class="text-brand-muted">{{ $f['count'] }}</span></a>@endforeach
        </div>
    </nav>

    <x-site.faq :items="$seo->faqItems()" />
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
