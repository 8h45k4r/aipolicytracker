@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3">
        <p class="eyebrow">{{ ucfirst($jurisdiction->jurisdiction_type) }}@if($jurisdiction->parent) · <a href="{{ $jurisdiction->parent->url() }}">{{ $jurisdiction->parent->name }}</a>@endif · {{ $jurisdiction->region }}</p>
        <h1 class="mt-1 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">AI regulation in {{ $jurisdiction->nameWithArticle() }}</h1>
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm"><x-site.verified :record="$jurisdiction" class="!text-sm" /><span class="text-brand-muted">{{ $policies->count() }} {{ \Illuminate\Support\Str::plural('instrument', $policies->count()) }} · {{ $policies->where('is_binding', true)->count() }} binding</span></div>
    </header>

    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <section aria-labelledby="overview-heading"><h2 id="overview-heading" class="section-title">Overview</h2><p class="prose-policy mt-2">{{ $jurisdiction->overview }}</p></section>
            <section aria-labelledby="status-heading" class="mt-8"><h2 id="status-heading" class="section-title">What is the current regulatory status?</h2><p class="prose-policy mt-2">{{ $jurisdiction->regulatory_status_summary }}</p></section>
            @if($jurisdiction->binding_vs_guidance)<section aria-labelledby="binding-heading" class="mt-8"><h2 id="binding-heading" class="section-title">Binding rules versus guidance</h2><p class="prose-policy mt-2">{{ $jurisdiction->binding_vs_guidance }}</p></section>@endif

            <section aria-labelledby="policies-heading" class="mt-8">
                <h2 id="policies-heading" class="section-title">Key policy instruments</h2>
                <div class="mt-2 divide-y divide-brand-line border-y border-brand-line">
                    @forelse($policies as $p)<x-site.policy-row :policy="$p" />@empty<div class="py-6"><x-site.empty title="No published instruments yet" /></div>@endforelse
                </div>
                @if($policies->count() > 3)<a href="{{ route('policies.index', ['jurisdiction' => $jurisdiction->slug]) }}" class="mt-3 inline-block text-sm text-brand-blue hover:underline">Filter all {{ $jurisdiction->name }} policies</a>@endif
            </section>

            <section aria-labelledby="deadlines-heading" class="mt-8">
                <h2 id="deadlines-heading" class="section-title">Upcoming deadlines</h2>
                @if($deadlines->isNotEmpty())
                <div class="table-wrap mt-3"><table><caption class="sr-only">Upcoming deadlines in {{ $jurisdiction->name }}</caption><thead><tr><th scope="col">Date</th><th scope="col">Milestone</th><th scope="col">Instrument</th></tr></thead><tbody>
                @foreach($deadlines as $d)<tr><td class="whitespace-nowrap font-mono"><time datetime="{{ $d->due_on->toDateString() }}">{{ $d->displayDate() }}</time></td><td>{{ $d->title }}@if($d->confidence_level !== 'high')<div class="text-xs text-amber-800">confidence: {{ $d->confidence_level }}</div>@endif</td><td><a href="{{ $d->policyInstrument->url() }}" class="text-brand-navy hover:underline">{{ $d->policyInstrument->short_title ?: $d->policyInstrument->title }}</a></td></tr>@endforeach
                </tbody></table></div>
                @else<p class="mt-2 text-sm text-brand-muted">No scheduled future dates recorded. Past milestones are listed on each policy page.</p>@endif
            </section>

            @if($jurisdiction->current_priorities)<section aria-labelledby="priorities-heading" class="mt-8"><h2 id="priorities-heading" class="section-title">Current priorities</h2><p class="prose-policy mt-2">{{ $jurisdiction->current_priorities }}</p></section>@endif

            <section aria-labelledby="changes-heading" class="mt-8">
                <h2 id="changes-heading" class="section-title">Latest changes</h2>
                <div class="mt-1 divide-y divide-brand-line border-y border-brand-line">@forelse($changes as $c)<x-site.change-item :change="$c" compact />@empty<p class="py-4 text-sm text-brand-muted">No change events recorded yet.</p>@endforelse</div>
            </section>

            @if($useCases->isNotEmpty() || $sectors->isNotEmpty() || $obligationCategories->isNotEmpty())
            <section aria-labelledby="applies-heading" class="mt-8">
                <h2 id="applies-heading" class="section-title">Applicable sectors, use cases and obligation areas</h2>
                <dl class="mt-3 grid gap-4 sm:grid-cols-3 text-sm">
                    <div><dt class="text-brand-muted">Use cases</dt><dd class="mt-1 flex flex-wrap gap-1.5">@foreach($useCases as $t)<a class="chip !min-h-0 !py-1" href="{{ route('policies.index', ['jurisdiction' => $jurisdiction->slug, 'use_case' => $t->slug]) }}">{{ $t->name }}</a>@endforeach</dd></div>
                    <div><dt class="text-brand-muted">Sectors</dt><dd class="mt-1 flex flex-wrap gap-1.5">@foreach($sectors as $t)<a class="chip !min-h-0 !py-1" href="{{ route('policies.index', ['jurisdiction' => $jurisdiction->slug, 'sector' => $t->slug]) }}">{{ $t->name }}</a>@endforeach</dd></div>
                    <div><dt class="text-brand-muted">Obligation areas</dt><dd class="mt-1 flex flex-wrap gap-1.5">@foreach($obligationCategories as $c)<a class="chip !min-h-0 !py-1" href="{{ route('obligations.index', ['jurisdiction' => $jurisdiction->slug, 'category' => $c->category]) }}">{{ str_replace('_', ' ', $c->category) }} ({{ $c->n }})</a>@endforeach</dd></div>
                </dl>
            </section>
            @endif

            @if(!empty($jurisdiction->how_to_use))
            <section aria-labelledby="howto-heading" class="mt-8">
                <h2 id="howto-heading" class="section-title">How to use this information</h2>
                <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-sm text-brand-body">@foreach($jurisdiction->how_to_use as $step)<li>{{ $step }}</li>@endforeach</ol>
            </section>
            @endif

            <x-site.source-list :sources="collect($jurisdiction->official_sources ?? [])" title="Official government and regulator sources" class="mt-8" />
            <x-site.faq :items="$jurisdiction->faq ?? []" />
            <x-site.disclaimer class="mt-8" />
        </div>

        <aside class="space-y-6">
            <div class="lg:sticky lg:top-4 space-y-6">
                @if(!empty($jurisdiction->regulators))
                <div class="card-flat p-4 text-sm"><p class="font-semibold text-brand-navy">Regulators</p><ul class="mt-2 space-y-2">@foreach($jurisdiction->regulators as $r)<li><a href="{{ $r['url'] }}" rel="noopener" class="text-brand-navy hover:underline" data-track="source_click">{{ $r['name'] }}</a>@if(!empty($r['role']))<div class="text-xs text-brand-muted">{{ $r['role'] }}</div>@endif</li>@endforeach</ul></div>
                @endif
                @if($related->isNotEmpty())
                <div class="text-sm"><p class="font-semibold text-brand-navy">Compare with</p><ul class="mt-2 space-y-1.5">@foreach($related as $r)<li><a href="{{ route('compare.index', ['j' => $jurisdiction->slug.','.$r->slug]) }}" class="text-brand-body hover:underline" rel="nofollow">{{ $jurisdiction->short_name ?: $jurisdiction->name }} vs {{ $r->short_name ?: $r->name }}</a></li>@endforeach</ul></div>
                @endif
                @if($jurisdiction->children->isNotEmpty())
                <div class="text-sm"><p class="font-semibold text-brand-navy">Sub-national jurisdictions</p><ul class="mt-2 space-y-1.5">@foreach($jurisdiction->children as $c)<li><a href="{{ $c->url() }}" class="text-brand-body hover:underline">{{ $c->name }}</a></li>@endforeach</ul></div>
                @endif
                <x-site.correction-cta subject-type="jurisdiction" :subject-slug="$jurisdiction->slug" class="flex-col [&>*]:w-full" />
                <x-site.certifyi-cta />
            </div>
        </aside>
    </div>
</div>
@endsection
