@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="badge {{ $obligation->is_binding ? 'bg-brand-navy text-white ring-brand-navy' : 'bg-brand-paper text-brand-body ring-brand-line' }}">{{ $obligation->is_binding ? 'Legal requirement' : 'Voluntary guidance' }}</span>
            <a href="{{ route('obligations.index', ['category' => $obligation->category]) }}" class="text-brand-body hover:text-brand-navy">{{ $categoryName }}</a>
            <span class="text-brand-muted" aria-hidden="true">·</span>
            <a href="{{ $policy->jurisdiction->url() }}" class="text-brand-body hover:text-brand-navy">{{ $policy->jurisdiction->name }}</a>
            <x-site.status-badge :status="$policy->statusEnum()" />
        </div>
        <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $obligation->title }}</h1>
        <p class="mt-1 text-sm text-brand-muted"><a href="{{ route('obligations.context', $obligation->slug) }}" class="float-right text-brand-muted hover:text-brand-navy" title="This duty as one Markdown file, with its provenance">Context file</a>Under <a href="{{ $policy->url() }}" class="font-medium text-brand-body">{{ $policy->short_title ?: $policy->title }}</a>@if($obligation->source_reference), {{ $obligation->source_reference }}@endif</p>
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm"><x-site.verified :record="$obligation" class="!text-sm" />@if($obligation->official_source_url)<a href="{{ $obligation->official_source_url }}" rel="noopener" class="text-brand-blue font-medium hover:underline" data-track="source_click">Open official source</a>@endif</div>
    </header>
    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <section aria-labelledby="req-heading"><h2 id="req-heading" class="section-title">What does it require?</h2><p class="prose-policy mt-2">{{ $obligation->summary }}</p></section>
            @if($obligation->practical_action)<section aria-labelledby="action-heading" class="mt-8"><h2 id="action-heading" class="section-title">Practical action</h2><p class="prose-policy mt-2">{{ $obligation->practical_action }}</p></section>@endif
            <section aria-labelledby="who-heading" class="mt-8">
                <h2 id="who-heading" class="section-title">Who does it apply to?</h2>
                @foreach($obligation->applicabilityRules as $r)<p class="prose-policy mt-2">{{ $r->description }}@if($r->conditions) <span class="text-brand-muted">{{ $r->conditions }}</span>@endif</p>@endforeach
                <dl class="mt-3 grid gap-3 sm:grid-cols-3 text-sm">
                    @foreach(['actor' => 'Actors', 'sector' => 'Sectors', 'use_case' => 'Use cases'] as $tax => $label)
                    @php($terms = $obligation->termsOf($tax))
                    @if($terms->isNotEmpty())<div><dt class="text-brand-muted">{{ $label }}</dt><dd class="mt-1 flex flex-wrap gap-1.5">@foreach($terms as $t)<a class="chip !min-h-0 !py-1" href="{{ route('obligations.index', [$tax => $t->slug]) }}">{{ $t->name }}</a>@endforeach</dd></div>@endif
                    @endforeach
                </dl>
                @if($obligation->applies_from)<p class="mt-3 text-sm text-brand-body"><span class="font-medium">Applies from:</span> <time datetime="{{ $obligation->applies_from->toDateString() }}">{{ $obligation->applies_from->format('j F Y') }}</time></p>@endif
            </section>
            @if($obligation->evidenceArtifacts->isNotEmpty())
            <section aria-labelledby="evidence-heading" class="mt-8"><h2 id="evidence-heading" class="section-title">Evidence examples</h2><ul class="mt-2 space-y-1.5 text-sm text-brand-body list-disc pl-5">@foreach($obligation->evidenceArtifacts as $e)<li><span class="font-medium text-brand-navy">{{ $e->title }}</span>@if($e->description) — {{ $e->description }}@endif <span class="text-xs text-brand-muted">({{ $e->artifact_type }})</span></li>@endforeach</ul></section>
            @endif
            @if($obligation->frameworkMappings->isNotEmpty())
            <section aria-labelledby="mapping-heading" class="mt-8">
                <h2 id="mapping-heading" class="section-title">Framework mappings</h2>
                <p class="mt-1 text-xs text-brand-muted">Original editorial crosswalks. They cite clause numbers only and reproduce no standard text; confidence reflects how direct the mapping is.</p>
                @php($mappedSlug = config('frameworks.'.$obligation->frameworkMappings->first()->framework.'.slug'))
                @if($mappedSlug)<p class="mt-1 text-xs"><a href="{{ route('frameworks.crosswalk', [$mappedSlug, $policy->jurisdiction->slug]) }}" class="text-brand-blue hover:underline">See every {{ $policy->jurisdiction->name }} duty mapped this way &rarr;</a></p>@endif
                <div class="table-wrap mt-3"><table><caption class="sr-only">Framework mappings</caption><thead><tr><th scope="col">Framework</th><th scope="col">Reference</th><th scope="col">Note</th><th scope="col">Confidence</th></tr></thead><tbody>
                @foreach($obligation->frameworkMappings as $m)<tr><td class="whitespace-nowrap font-medium">@if(config('frameworks.'.$m->framework))<a href="{{ route('frameworks.show', config('frameworks.'.$m->framework.'.slug')) }}" class="text-brand-navy hover:underline">{{ $m->frameworkName() }}</a>@else{{ $m->frameworkName() }}@endif</td><td>{{ $m->reference }}</td><td>{{ $m->note }}</td><td>{{ $m->confidence_level }}</td></tr>@endforeach
                </tbody></table></div>
            </section>
            @endif
            <x-site.cite :title="$obligation->title.' ('.($policy->short_title ?: $policy->title).')'" :url="$obligation->url()" :source-url="$obligation->official_source_url" :source-title="$policy->source_title" :publisher="$policy->source_publisher" class="mt-8" />
            @if($similar->isNotEmpty())
            <section aria-labelledby="similar-heading" class="mt-8"><h2 id="similar-heading" class="section-title">Similar obligations in other instruments</h2><ul class="mt-2 space-y-2 text-sm">@foreach($similar as $s)<li><a href="{{ $s->url() }}" class="text-brand-navy hover:underline">{{ $s->title }}</a> <span class="text-xs text-brand-muted">— {{ $s->policyInstrument->short_title ?: $s->policyInstrument->title }}, {{ $s->policyInstrument->jurisdiction->name }}{{ $s->is_binding ? '' : ' (voluntary)' }}</span></li>@endforeach</ul></section>
            @endif
            <x-site.disclaimer class="mt-8" />
        </div>
        <aside class="space-y-6">
            <div class="lg:sticky lg:top-4 space-y-6">
                <div class="card-flat p-4 text-sm"><p class="font-semibold text-brand-navy">Source</p><p class="mt-1 text-brand-body">{{ $policy->source_title }}</p><p class="text-xs text-brand-muted">{{ $policy->source_publisher }}@if($obligation->source_reference) · {{ $obligation->source_reference }}@endif</p>@if($obligation->official_source_url)<a href="{{ $obligation->official_source_url }}" rel="noopener" class="mt-2 inline-block text-brand-blue hover:underline break-all" data-track="source_click">{{ \Illuminate\Support\Str::limit($obligation->official_source_url, 60) }}</a>@endif</div>
                <x-site.correction-cta subject-type="obligation" :subject-slug="$obligation->slug" :save-title="$obligation->title" :save-url="$obligation->url()" :save-meta="($policy->short_title ?: $policy->title)" class="flex-col [&>*]:w-full" />
                <x-site.certifyi-cta label="Turn this obligation into a tracked control" />
            </div>
        </aside>
    </div>
</div>
@endsection
