@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3">
        <p class="eyebrow">{{ ucfirst($jurisdiction->jurisdiction_type) }}@if($jurisdiction->parent) · <a href="{{ $jurisdiction->parent->url() }}">{{ $jurisdiction->parent->name }}</a>@endif · {{ $jurisdiction->region }}</p>
        <h1 class="mt-1 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">AI regulation in {{ $jurisdiction->nameWithArticle() }}</h1>
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm"><a href="{{ route('verification') }}" class="no-underline" title="How current this record has to be, and how many are past that date"><x-site.verified :record="$jurisdiction" class="!text-sm" /></a><a href="{{ route('jurisdictions.context', $jurisdiction->slug) }}" class="text-brand-muted hover:text-brand-navy" title="The whole profile as one Markdown file, with its provenance">Context file</a><span class="text-brand-muted">{{ $policies->count() }} {{ \Illuminate\Support\Str::plural('instrument', $policies->count()) }} · {{ $policies->where('is_binding', true)->count() }} binding</span></div>
    </header>

    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            @if(!empty($translation))
            <p class="mb-4 rounded-sm border border-brand-line bg-brand-paper px-3 py-2 text-sm text-brand-body" lang="{{ $locale }}">{{ $translation['lead'] ?? '' }} <span class="meta">{{ $reviewedTranslation ? 'Revisado: '.$translation['reviewed_by'] : ($translation['translated_by'] ?? 'Draft').' · unreviewed translation' }} · <a href="{{ $englishUrl }}" hreflang="en">English</a></span></p>
            @endif
            <x-site.answer-box :text="$answer" :facts="$facts" class="mb-8" />
            <section aria-labelledby="overview-heading"><h2 id="overview-heading" class="section-title">Overview</h2><p class="prose-policy mt-2">{{ $jurisdiction->overview }}</p></section>
            <section aria-labelledby="status-heading" class="mt-8"><h2 id="status-heading" class="section-title">What is the current regulatory status?</h2><p class="prose-policy mt-2">{{ $jurisdiction->regulatory_status_summary }}</p></section>
            @if($jurisdiction->binding_vs_guidance)<section aria-labelledby="binding-heading" class="mt-8"><h2 id="binding-heading" class="section-title">Binding rules versus guidance</h2><p class="prose-policy mt-2">{{ $jurisdiction->binding_vs_guidance }}</p></section>@endif

            <section aria-labelledby="policies-heading" class="mt-8">
                <h2 id="policies-heading" class="section-title">Key policy instruments</h2>
                <div class="mt-1">
                    @forelse($policies as $p)<x-site.policy-row :policy="$p" />@empty<div class="py-6"><x-site.empty title="No published instrument yet" /></div>@endforelse
                </div>
            </section>

            @if($policies->isNotEmpty())
            <section aria-labelledby="instruments-heading" class="mt-8">
                <h2 id="instruments-heading" class="section-title">Instruments at a glance</h2>
                <p class="mt-1 text-xs text-brand-muted">Native-language names are shown where the record carries one; a blank means the source is in English or the name is not yet recorded.</p>
                <div class="table-wrap mt-3"><table><caption class="sr-only">AI policy instruments recorded for {{ $jurisdiction->name }}</caption>
                    <thead><tr><th scope="col">Instrument</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Binding</th><th scope="col">Key date</th><th scope="col">Source</th></tr></thead>
                    <tbody>@foreach($policies as $p)<tr>
                        <th scope="row" class="font-medium"><a href="{{ $p->url() }}" class="text-brand-navy">{{ $p->short_title ?: $p->title }}</a>@if($p->title_native)<span class="block text-xs font-normal text-brand-muted">{{ $p->title_native }}</span>@endif</th>
                        <td class="whitespace-nowrap">{{ $p->typeEnum()->label() }}</td>
                        <td><x-site.status-badge :status="$p->statusEnum()" /></td>
                        <td>{{ $p->is_binding ? 'Binding' : 'Voluntary' }}</td>
                        <td class="whitespace-nowrap">@php($kd = $p->applies_from ?? $p->in_force_on ?? $p->adopted_on ?? $p->published_on)@if($kd)<time datetime="{{ $kd->toDateString() }}">{{ $kd->format('j M Y') }}</time>@else—@endif</td>
                        <td>@if($p->official_source_url)<a href="{{ $p->official_source_url }}" rel="noopener" class="text-brand-blue">Official</a>@else<span class="text-brand-muted">Not linked</span>@endif</td>
                    </tr>@endforeach</tbody></table></div>
            </section>
            @endif

            @if(!empty($timeline))
            <section aria-labelledby="timeline-heading" class="mt-8">
                <h2 id="timeline-heading" class="section-title">Timeline</h2>
                <p class="mt-1 text-xs text-brand-muted">Every dated event on the recorded instruments: publication, adoption, entry into force, application and deadlines. Future dates are marked.</p>
                <ol class="mt-3 border-l-2 border-brand-line pl-4 space-y-2 text-sm">
                    @foreach($timeline as $e)
                    <li class="relative"><span class="absolute -left-[1.4rem] top-1.5 h-2.5 w-2.5 rounded-full {{ $e['future'] ? 'bg-brand-blue' : 'bg-brand-navy' }}" aria-hidden="true"></span><time datetime="{{ $e['date']->toDateString() }}" class="font-medium text-brand-navy">{{ $e['date']->format('j M Y') }}</time>@if($e['future']) <span class="badge-neutral">upcoming</span>@endif · <a href="{{ $e['policy']->url() }}" class="text-brand-body hover:underline">{{ $e['label'] }}</a></li>
                    @endforeach
                </ol>
            </section>
            @endif

            <section aria-labelledby="deadlines-heading" class="mt-8">
                <h2 id="deadlines-heading" class="section-title">Upcoming deadlines</h2>
                <p class="mt-1 text-sm text-brand-muted">Subscribe to these dates in your calendar: <a href="{{ route('calendar.feed.jurisdiction', $jurisdiction->slug) }}">{{ $jurisdiction->short_name ?: $jurisdiction->name }} feed</a> · <a href="{{ route('calendar') }}">how it works</a></p>
                @if($deadlines->isNotEmpty())
                <div class="table-wrap mt-3"><table><caption class="sr-only">Upcoming deadlines in {{ $jurisdiction->name }}</caption><thead><tr><th scope="col">Date</th><th scope="col">Milestone</th><th scope="col">Instrument</th></tr></thead><tbody>
                @foreach($deadlines as $d)<tr><td class="whitespace-nowrap font-mono"><time datetime="{{ $d->due_on->toDateString() }}">{{ $d->displayDate() }}</time></td><td>{{ $d->title }}@if($d->confidence_level !== 'high')<div class="text-xs text-state-warn">confidence: {{ $d->confidence_level }}</div>@endif</td><td><a href="{{ $d->policyInstrument->url() }}" class="text-brand-navy hover:underline">{{ $d->policyInstrument->short_title ?: $d->policyInstrument->title }}</a></td></tr>@endforeach
                </tbody></table></div>
                @else<p class="mt-2 text-sm text-brand-muted">No scheduled future dates recorded. Past milestones are listed on each policy page.</p>@endif
            </section>

            @if($jurisdiction->current_priorities)<section aria-labelledby="priorities-heading" class="mt-8"><h2 id="priorities-heading" class="section-title">Current priorities</h2><p class="prose-policy mt-2">{{ $jurisdiction->current_priorities }}</p></section>@endif

            <section aria-labelledby="changes-heading" class="mt-8">
                <h2 id="changes-heading" class="section-title">Latest changes</h2>
                <p class="mt-1 text-xs"><a href="{{ route('updates.jurisdiction', $jurisdiction->slug) }}" class="text-brand-blue hover:underline">All updates for {{ $jurisdiction->short_name ?: $jurisdiction->name }}</a> · <a href="{{ route('updates.jurisdiction.feed', $jurisdiction->slug) }}" class="text-brand-blue hover:underline">RSS</a></p>
                <div class="mt-1 divide-y divide-brand-line border-y border-brand-line">@forelse($changes as $c)<x-site.change-item :change="$c" compact />@empty<p class="py-4 text-sm text-brand-muted">No change events recorded yet.</p>@endforelse</div>
                @if($changes->isNotEmpty())<p class="mt-3 text-xs text-brand-muted"><a href="{{ route('updates.jurisdiction', $jurisdiction->slug) }}" class="hover:text-brand-navy">All updates for {{ $jurisdiction->short_name ?: $jurisdiction->name }}</a> · <a href="{{ route('updates.jurisdiction.feed', $jurisdiction->slug) }}" class="hover:text-brand-navy" data-track="rss_click">RSS</a> · <a href="{{ route('updates.index') }}" class="hover:text-brand-navy">Updates hub</a></p>@endif
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
            <x-site.cite :title="'AI regulation in '.$jurisdiction->name" :url="$jurisdiction->url()" class="mt-8" />
            <x-site.faq :items="$seo->faqItems()" />
            <x-site.subscribe-form source="jurisdiction" :topic="$jurisdiction->slug" class="mt-10" />
            <x-site.disclaimer class="mt-8" />
        </div>

        <aside class="space-y-6">
            <div class="lg:sticky lg:top-4 space-y-6">
                @if(!empty($jurisdiction->regulators))
                @if($controls->isNotEmpty())
                <div class="card-flat p-4 text-sm">
                    <p class="font-semibold text-brand-navy">Controls that meet {{ $jurisdiction->short_name ?: $jurisdiction->name }} duties</p>
                    <p class="mt-1 text-xs text-brand-muted">Satisfied / served, across the {{ $policies->count() }} recorded {{ \Illuminate\Support\Str::plural('instrument', $policies->count()) }}.</p>
                    <ul class="mt-2 space-y-1.5">@foreach($controls->take(7) as $r)<li class="flex items-baseline justify-between gap-2"><a href="{{ $r['control']->url() }}" class="text-brand-navy hover:underline">{{ $r['control']->title }}</a><span class="font-mono text-xs tabular-nums text-brand-muted whitespace-nowrap">{{ $r['satisfies'] }} / {{ $r['duties'] }}</span></li>@endforeach</ul>
                    @if($controls->count() > 7)<p class="mt-2 text-xs"><a href="{{ route('controls.index') }}">All {{ $controls->count() }} controls &rarr;</a></p>@endif
                </div>
                @endif
                <div class="card-flat p-4 text-sm"><p class="font-semibold text-brand-navy">Regulators</p><ul class="mt-2 space-y-2">@foreach($jurisdiction->regulators as $r)<li><a href="{{ $r['url'] }}" rel="noopener" class="text-brand-navy hover:underline" data-track="source_click">{{ $r['name'] }}</a>@if(!empty($r['role']))<div class="text-xs text-brand-muted">{{ $r['role'] }}</div>@endif</li>@endforeach</ul></div>
                @endif
                @if($related->isNotEmpty())
                <div class="text-sm"><p class="font-semibold text-brand-navy">Compare with</p><ul class="mt-2 space-y-1.5">@foreach($related as $r)<li><a href="{{ route('compare.index', ['j' => $jurisdiction->slug.','.$r->slug]) }}" class="text-brand-body hover:underline" rel="nofollow">{{ $jurisdiction->short_name ?: $jurisdiction->name }} vs {{ $r->short_name ?: $r->name }}</a></li>@endforeach</ul></div>
                @endif
                @if($jurisdiction->children->isNotEmpty())
                <div class="text-sm"><p class="font-semibold text-brand-navy">Sub-national jurisdictions</p><ul class="mt-2 space-y-1.5">@foreach($jurisdiction->children as $c)<li><a href="{{ $c->url() }}" class="text-brand-body hover:underline">{{ $c->name }}</a></li>@endforeach</ul></div>
                @endif
                <x-site.correction-cta subject-type="jurisdiction" :subject-slug="$jurisdiction->slug" :save-title="$jurisdiction->name" :save-url="$jurisdiction->url()" save-meta="Jurisdiction" class="flex-col [&>*]:w-full" />
                <x-site.certifyi-cta />
            </div>
        </aside>
    </div>
</div>
@endsection
