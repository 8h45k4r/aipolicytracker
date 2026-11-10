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
            <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $items->count() === \App\Services\Templates\TemplateCatalog::all()->count() ? $items->count().' free ' : 'Free ' }}AI governance templates, generated from the law on record</h1>
            <p class="mt-2"><span class="badge bg-state-goodbg text-state-good ring-state-good/30">{{ \App\Models\TemplateDownloadRequest::accessLabel() }}</span></p>
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
        @if($bundleOffered)
        <p class="mt-3 text-sm text-brand-body" data-template-bundle>
            @if(auth()->user()?->entitled('templates.bundle'))<a href="{{ route('templates.bundle') }}" class="btn-secondary" data-track="template_bundle">Download every template (ZIP)</a>
            @else Every template in one ZIP, in every format, is part of <a href="{{ route('pricing') }}" data-track="template_bundle_upsell">Builder</a>. Each template below stays free.
            @endif
        </p>
        @endif
        @if($lastBuilt)<p class="mt-3 text-xs text-brand-muted">Last built <time datetime="{{ $lastBuilt->toAtomString() }}">{{ $lastBuilt->format('j M Y, H:i') }} UTC</time> · {{ config('templates.licence') }}</p>@endif
    </section>

    {{-- The landing pages, as plain links: the way a reader (or a crawler) reaches "EU AI Act templates". --}}
    <nav class="mt-5 flex flex-wrap items-center gap-2 text-sm" aria-label="Templates by framework">
        <span class="meta mr-1">By framework:</span>
        @foreach($facets->where('facet', 'framework') as $f)<a href="{{ $f['url'] }}" class="chip">{{ $f['label'] }} <span class="text-brand-muted">{{ $f['count'] }}</span></a>@endforeach
    </nav>

    {{-- Forty-odd cards in seven groups: a way to reach a group without scrolling past the others. --}}
    @if($jump !== [])
    <nav class="mt-3 hidden lg:flex flex-wrap items-center gap-2 text-sm" aria-label="Jump to a type of template" data-template-jump>
        <span class="meta mr-1">On this page:</span>
        @foreach($jump as $id => $label)<a href="#{{ $id }}" class="chip">{{ $label }}</a>@endforeach
    </nav>
    <x-site.toc :items="$jump" variant="mobile" class="mt-3" />
    @endif

    {{-- Closed until used: three selects cost a phone a full screen before the first template. --}}
    <details class="mt-3 card-flat text-sm" data-template-filters @if($filtered) open @endif>
        <summary class="flex min-h-[44px] cursor-pointer items-center justify-between gap-2 px-4 font-medium text-brand-navy">Filter by type, topic or framework <span class="text-xs font-normal text-brand-muted">{{ $filtered ? $items->count().' shown' : 'optional' }}</span></summary>
        <form method="get" action="{{ route('templates.index') }}" class="grid gap-3 border-t border-brand-line p-4 sm:grid-cols-4" data-autosubmit aria-label="Filter templates">
            <div><label for="t-type" class="label">Type</label><select id="t-type" name="type" class="input"><option value="">All types</option>@foreach($types as $k => $label)<option value="{{ $k }}" @selected($filters['type'] === $k)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="t-topic" class="label">Topic</label><select id="t-topic" name="topic" class="input"><option value="">All topics</option>@foreach($topics as $k => $label)<option value="{{ $k }}" @selected($filters['topic'] === $k)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="t-fw" class="label">Framework</label><select id="t-fw" name="framework" class="input"><option value="">All frameworks</option>@foreach($fw as $k => $label)<option value="{{ $k }}" @selected($filters['framework'] === $k)>{{ $label }}</option>@endforeach</select></div>
            <div class="flex items-end gap-2"><button type="submit" class="btn-primary">Apply</button><a href="{{ route('templates.index') }}" class="btn-secondary">Reset</a></div>
        </form>
    </details>

    @if($items->isEmpty())
    <div class="mt-8"><x-site.empty title="No template matches" :reset="route('templates.index')">Try fewer filters.</x-site.empty></div>
    @else
    @foreach($items->groupBy('type') as $type => $group)
    @php($landing = $facets->first(fn ($f) => $f['facet'] === 'type' && $f['key'] === $type))
    <section id="type-{{ $type }}" class="mt-10 scroll-mt-6" aria-labelledby="type-{{ $type }}-heading">
        <div class="rule-strong pt-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"><h2 id="type-{{ $type }}-heading" class="section-title">{{ \Illuminate\Support\Str::plural($types[$type] ?? ucfirst($type)) }}</h2><span class="meta">@if($landing && ! $filtered)<a href="{{ $landing['url'] }}">All {{ $landing['count'] }} {{ mb_strtolower(\Illuminate\Support\Str::plural($types[$type] ?? $type, $landing['count'])) }} on one page</a>@else{{ $group->count() }}@endif</span></div>
        <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($group as $item)<x-site.template-card :item="$item" />@endforeach</ul>
    </section>
    @endforeach
    @endif

    {{-- A score first, then the template that closes the gap it shows. Each assessment names the template it goes with. --}}
    @if($paired->isNotEmpty() && ! $filtered)
    <section id="self-assessments" class="mt-10 scroll-mt-6" aria-labelledby="assess-heading">
        <div class="rule-strong pt-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"><h2 id="assess-heading" class="section-title">Not sure where to start? Score yourself first</h2><a href="{{ route('assessments.index') }}" class="text-sm">All {{ $assessmentCount }} free self-assessments</a></div>
        <p class="mt-2 max-w-[70ch] text-sm text-brand-body">A short questionnaire shows which areas are weakest; the template beside it is where that work gets written down. {{ config('assessments.provider.disclosure') }} A score is a self-check, not an audit.</p>
        <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($paired as $a)
            <li class="card-flat flex flex-col p-4">
                <p class="flex flex-wrap items-center gap-1.5 text-xs"><span class="badge bg-brand-paper text-brand-body ring-brand-line">Self-assessment</span><span class="text-brand-muted">{{ $a['questions'] }} questions · about {{ $a['minutes'] }} min</span></p>
                <a href="{{ $a['url'] }}" rel="noopener" class="mt-2 text-base font-semibold leading-snug text-brand-navy hover:underline" data-track="assessment_click" data-track-label="{{ $a['slug'] }}">{{ $a['title'] }}</a>
                <p class="mt-1 flex-1 text-sm text-brand-body">{{ $a['summary'] }}</p>
                <p class="mt-3 text-sm">Then: <a href="{{ route('templates.show', $a['template']['slug']) }}" class="font-medium text-brand-navy">{{ $a['template']['title'] }}</a></p>
            </li>
            @endforeach
        </ul>
    </section>
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
