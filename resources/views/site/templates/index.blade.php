@extends('site.layouts.app')
@section('content')
@php($types = config('templates.types'))
@php($topics = config('templates.topics'))
@php($fw = config('templates.frameworks'))
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="eyebrow">Templates</p>
            <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">AI governance templates, generated from the law on record</h1>
        </div>
        <div class="flex gap-2"><a href="{{ route('templates.feed') }}" class="btn-secondary" data-track="rss_click">RSS of versions</a><a href="#subscribe" class="btn-primary" data-track="newsletter_click">Get update alerts</a></div>
    </div>

    {{-- The answer box: computed from the catalogue and the record counts, never written by hand. --}}
    <section class="mt-5 card-flat p-5" aria-labelledby="summary-heading">
        <h2 id="summary-heading" class="sr-only">Summary</h2>
        <p class="text-brand-navy text-base sm:text-lg leading-relaxed">{{ $summary }}</p>
        <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-site.stat label="Templates" :value="number_format($items->count())" />
            <x-site.stat label="Recorded duties" :value="number_format($counts['duties'])" :href="route('obligations.index')" />
            <x-site.stat label="Controls" :value="number_format($counts['controls'])" :href="route('controls.index')" />
            <x-site.stat label="Instruments" :value="number_format($counts['instruments'])" :href="route('policies.index')" />
        </dl>
        @if($lastBuilt)<p class="mt-3 text-xs text-brand-muted">Last built <time datetime="{{ $lastBuilt->toAtomString() }}">{{ $lastBuilt->format('j M Y, H:i') }} UTC</time> · {{ config('templates.licence') }}</p>@endif
    </section>

    <form method="get" action="{{ route('templates.index') }}" class="mt-5 grid gap-3 sm:grid-cols-4" data-autosubmit aria-label="Filter templates">
        <div><label for="t-type" class="label">Type</label><select id="t-type" name="type" class="input"><option value="">All types</option>@foreach($types as $k => $label)<option value="{{ $k }}" @selected($filters['type'] === $k)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="t-topic" class="label">Topic</label><select id="t-topic" name="topic" class="input"><option value="">All topics</option>@foreach($topics as $k => $label)<option value="{{ $k }}" @selected($filters['topic'] === $k)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="t-fw" class="label">Framework</label><select id="t-fw" name="framework" class="input"><option value="">All frameworks</option>@foreach($fw as $k => $label)<option value="{{ $k }}" @selected($filters['framework'] === $k)>{{ $label }}</option>@endforeach</select></div>
        <div class="flex items-end gap-2"><button type="submit" class="btn-primary">Apply</button><a href="{{ route('templates.index') }}" class="btn-secondary">Reset</a></div>
    </form>

    @if($items->isEmpty())
    <div class="mt-8"><x-site.empty title="No template matches" :reset="route('templates.index')">Try fewer filters.</x-site.empty></div>
    @else
    @foreach($items->groupBy('type') as $type => $group)
    <section class="mt-10" aria-labelledby="type-{{ $type }}">
        <div class="rule-strong pt-3 flex items-baseline justify-between"><h2 id="type-{{ $type }}" class="section-title">{{ \Illuminate\Support\Str::plural($types[$type] ?? ucfirst($type)) }}</h2><span class="meta">{{ $group->count() }}</span></div>
        <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($group as $item)<x-site.template-card :item="$item" />@endforeach</ul>
    </section>
    @endforeach
    @endif

    <section class="mt-10 grid gap-8 lg:grid-cols-3" aria-labelledby="how-heading">
        <div class="lg:col-span-2">
            <h2 id="how-heading" class="section-title">How the templates are made</h2>
            <p class="mt-2 text-sm text-brand-body max-w-[70ch]">Each template is described as data: which duties it covers, which sheets and sections it has, and how the records fill them. A daily job turns the <a href="{{ route('obligations.index') }}">obligations</a>, <a href="{{ route('controls.index') }}">controls</a>, <a href="{{ route('calendar') }}">deadlines</a>, <a href="{{ route('frameworks.index') }}">framework crosswalks</a> and the <a href="{{ route('risk.index') }}">MIT AI Risk Repository taxonomy</a> into the files. Every row that cites a duty links to its record, and the record links to the official source and states its verification status. The <a href="{{ route('methodology') }}">methodology</a> page explains the trust model behind every record.</p>
            <p class="mt-2 text-sm text-brand-body max-w-[70ch]">When the records change, a template that is affected gets the next version with a changelog; the old address keeps working and the page lists every version. Rebuilds appear in the <a href="{{ route('updates.index') }}">AI policy updates</a> hub and in the <a href="{{ route('templates.feed') }}">templates feed</a>.</p>
        </div>
        <aside>
            <x-site.subscribe-form id="subscribe" source="templates" topic="templates" topic-label="the templates library" />
        </aside>
    </section>

    <x-site.faq :items="$seo->faqItems()" />
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
