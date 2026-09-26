@extends('site.layouts.app')
@section('content')
@php($name = $policy->short_title ?: $policy->title)
@php($status = $policy->statusEnum())
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <a href="{{ $policy->jurisdiction->url() }}" class="font-medium text-brand-body hover:text-brand-navy">{{ $policy->jurisdiction->name }}</a>
            <span class="text-brand-muted" aria-hidden="true">·</span>
            <span class="text-brand-muted">{{ $policy->typeEnum()->label() }}</span>
            <x-site.status-badge :status="$status" />
            <span class="badge {{ $policy->is_binding ? 'bg-brand-navy text-white ring-brand-navy' : 'bg-brand-paper text-brand-body ring-brand-line' }}">{{ $policy->is_binding ? 'Binding' : 'Non-binding' }}</span>
        </div>
        <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">{{ $name }}: requirements, deadlines and compliance actions</h1>
        @if($policy->short_title && $policy->short_title !== $policy->title)<p class="mt-1 text-sm text-brand-muted">{{ $policy->title }}</p>@endif
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            <a href="{{ route('verification') }}" class="no-underline" title="How current this record has to be, and how many are past that date"><x-site.verified :record="$policy" class="!text-sm" /></a>
            @if($policy->official_source_url)<a href="{{ $policy->official_source_url }}" rel="noopener" class="text-brand-blue font-medium hover:underline" data-track="source_click">Open official source</a>@endif
            <a href="{{ route('policies.json', $policy->slug) }}" class="text-brand-muted hover:text-brand-navy">JSON record</a>
            <a href="{{ route('policies.context', $policy->slug) }}" class="text-brand-muted hover:text-brand-navy" title="The whole record as one Markdown file, with its provenance">Context file</a>
        </div>
    </header>

    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <x-site.answer-box :text="$answer" :facts="$facts" />
            <section aria-labelledby="overview-heading" class="mt-8">
                <h2 id="overview-heading" class="section-title">What is {{ $policy->definiteName() }}?</h2>
                <div class="prose-policy mt-2"><p>{{ $policy->summary_plain }}</p></div>
                @if($policy->status_note)<p class="mt-3 rounded-sm bg-brand-paper border border-brand-line px-3 py-2 text-sm text-brand-body"><span class="font-medium">Status note:</span> {{ $policy->status_note }}</p>@endif
            </section>

            <section aria-labelledby="scope-heading" class="mt-8">
                <h2 id="scope-heading" class="section-title">Who does it apply to?</h2>
                <div class="prose-policy mt-2"><p>{{ $policy->scope_summary }}</p>@if($policy->who_it_applies_to)<p>{{ $policy->who_it_applies_to }}</p>@endif</div>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                    @foreach(['actor' => 'Actors', 'sector' => 'Sectors', 'use_case' => 'AI use cases', 'risk_category' => 'Risk categories', 'ai_system_type' => 'AI system types'] as $tax => $label)
                        @php($terms = $policy->termsOf($tax))
                        @if($terms->isNotEmpty())
                        <div><dt class="text-brand-muted">{{ $label }}</dt><dd class="mt-1 flex flex-wrap gap-1.5">@foreach($terms as $t)<a class="chip !min-h-0 !py-1" href="{{ route('policies.index', [$tax === 'risk_category' ? 'risk' : $tax => $t->slug]) }}">{{ $t->name }}</a>@endforeach</dd></div>
                        @endif
                    @endforeach
                </dl>
                @if($policy->applicabilityRules->whereNull('obligation_id')->isNotEmpty())
                <ul class="mt-4 space-y-2 text-sm text-brand-body">@foreach($policy->applicabilityRules->whereNull('obligation_id') as $rule)<li>{{ $rule->description }}@if($rule->conditions) <span class="text-brand-muted">({{ $rule->conditions }})</span>@endif</li>@endforeach</ul>
                @endif
            </section>

            <section aria-labelledby="dates-heading" class="mt-8">
                <h2 id="dates-heading" class="section-title">When do the requirements apply?</h2>
                @if($policy->key_dates_summary)<p class="prose-policy mt-2">{{ $policy->key_dates_summary }}</p>@endif
                @if($policy->deadlines->isNotEmpty())
                <div class="table-wrap mt-3">
                    <table>
                        <caption class="sr-only">Key dates and deadlines for {{ $name }}</caption>
                        <thead><tr><th scope="col">Date</th><th scope="col">Milestone</th><th scope="col">Source reference</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                        @foreach($policy->deadlines as $d)
                        <tr>
                            <td class="whitespace-nowrap font-mono">@if($d->due_on)<time datetime="{{ $d->due_on->toDateString() }}">{{ $d->displayDate() }}</time>@else{{ $d->displayDate() }}@endif</td>
                            <td><span class="font-medium text-brand-navy">{{ $d->title }}</span>@if($d->description)<div class="text-brand-muted">{{ $d->description }}</div>@endif</td>
                            <td>{{ $d->source_reference ?: '—' }}</td>
                            <td class="whitespace-nowrap">{{ ucfirst($d->deadline_status) }}@if($d->confidence_level !== 'high')<div class="text-xs text-state-warn">confidence: {{ $d->confidence_level }}</div>@endif</td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
                @if($policy->date_notes)<p class="mt-2 text-xs text-brand-muted">{{ $policy->date_notes }}</p>@endif
            </section>

            @if($policy->what_organizations_must_do || $policy->obligations->isNotEmpty())
            <section aria-labelledby="obligations-heading" class="mt-8">
                <h2 id="obligations-heading" class="section-title">What must organisations do?</h2>
                @if($policy->what_organizations_must_do)<p class="prose-policy mt-2">{{ $policy->what_organizations_must_do }}</p>@endif
                <div class="mt-4 space-y-2">
                    @foreach($policy->obligations as $o)
                    <details class="card-flat group" @if($loop->first) open @endif>
                        <summary class="flex flex-wrap items-center gap-2 p-4 text-sm font-medium text-brand-navy list-none min-h-[44px]">
                            <span class="badge {{ $o->is_binding ? 'bg-brand-navy text-white ring-brand-navy' : 'bg-brand-paper text-brand-body ring-brand-line' }}">{{ $o->is_binding ? 'Legal requirement' : 'Voluntary' }}</span>
                            <span class="flex-1">{{ $o->title }}</span>
                            <span class="text-xs text-brand-muted">{{ $o->source_reference }}</span>
                        </summary>
                        <div class="px-4 pb-4 text-sm text-brand-body space-y-3">
                            <p>{{ $o->summary }}</p>
                            @if($o->practical_action)<p><span class="font-medium text-brand-navy">Practical action:</span> {{ $o->practical_action }}</p>@endif
                            @if($o->evidenceArtifacts->isNotEmpty())<p><span class="font-medium text-brand-navy">Evidence examples:</span> {{ $o->evidenceArtifacts->pluck('title')->implode('; ') }}</p>@endif
                            @if($o->frameworkMappings->isNotEmpty())<p><span class="font-medium text-brand-navy">Framework mapping (original, editorial):</span> @foreach($o->frameworkMappings as $m)@if(!$loop->first); @endif<a href="{{ route('frameworks.show', config('frameworks.'.$m->framework.'.slug', $m->framework)) }}" class="text-brand-navy hover:underline">{{ $m->frameworkName() }}</a> {{ $m->reference }}@endforeach</p>@endif
                            <p class="flex flex-wrap gap-3 text-xs"><a href="{{ $o->url() }}" class="text-brand-blue hover:underline">Obligation page</a>@if($o->applies_from)<span class="text-brand-muted">Applies from {{ $o->applies_from->format('j M Y') }}</span>@endif<x-site.verified :record="$o" /></p>
                        </div>
                    </details>
                    @endforeach
                </div>
            </section>
            @endif

            @if($policy->penalties_summary)
            <section aria-labelledby="penalties-heading" class="mt-8"><h2 id="penalties-heading" class="section-title">Penalties</h2><p class="prose-policy mt-2">{{ $policy->penalties_summary }}</p></section>
            @endif

            @if($policy->sections->isNotEmpty())
            <section aria-labelledby="sections-heading" class="mt-8">
                <h2 id="sections-heading" class="section-title">Key sections and articles</h2>
                <div class="table-wrap mt-3"><table><caption class="sr-only">Sections of {{ $name }}</caption><thead><tr><th scope="col">Reference</th><th scope="col">Title</th><th scope="col">Summary</th></tr></thead><tbody>
                @foreach($policy->sections as $s)<tr><td class="whitespace-nowrap font-medium">{{ $s->reference }}</td><td>{{ $s->title }}</td><td>{{ $s->summary }}</td></tr>@endforeach
                </tbody></table></div>
            </section>
            @endif

            @if($policy->procurementRules->isNotEmpty() || $policy->enforcementEvents->isNotEmpty())
            <section aria-labelledby="public-heading" class="mt-8">
                <h2 id="public-heading" class="section-title">Public-sector rules and enforcement</h2>
                <ul class="mt-2 space-y-3 text-sm text-brand-body">
                    @foreach($policy->procurementRules as $r)<li><span class="font-medium text-brand-navy">{{ $r->title }}</span> — {{ $r->summary }} @if($r->applies_to)<span class="text-brand-muted">(applies to: {{ $r->applies_to }})</span>@endif</li>@endforeach
                    @foreach($policy->enforcementEvents as $e)<li><span class="font-medium text-brand-navy">{{ $e->title }}</span>@if($e->occurred_on) ({{ $e->occurred_on->format('j M Y') }})@endif — {{ $e->summary }} @if($e->official_source_url)<a href="{{ $e->official_source_url }}" rel="noopener" class="text-brand-blue">Source</a>@endif</li>@endforeach
                </ul>
            </section>
            @endif

            <x-site.source-list :sources="$policy->sourceDocuments" class="mt-8" />
            <x-site.cite :title="$name" :url="$policy->url()" :source-url="$policy->official_source_url" :source-title="$policy->source_title" :publisher="$policy->source_publisher" class="mt-8" />

            @if($policy->versions->isNotEmpty() || $policy->changeEvents->isNotEmpty())
            <section aria-labelledby="history-heading" class="mt-8">
                <h2 id="history-heading" class="section-title">Change history</h2>
                <ul class="mt-2 space-y-2 text-sm text-brand-body">
                    @foreach($policy->changeEvents as $c)<li><time class="font-mono" datetime="{{ $c->occurred_on->toDateString() }}">{{ $c->occurred_on->format('j M Y') }}</time> — <a href="{{ $c->url() }}" class="text-brand-navy hover:underline">{{ $c->title }}</a></li>@endforeach
                    @foreach($policy->versions as $v)<li><span class="font-mono">{{ $v->version_date?->format('j M Y') ?? '—' }}</span> — {{ $v->version_label }}@if($v->official_source_url) (<a href="{{ $v->official_source_url }}" rel="noopener" class="text-brand-blue">source</a>)@endif</li>@endforeach
                </ul>
                @if($policy->changeEvents->isNotEmpty())<p class="mt-2 text-xs text-brand-muted"><a href="{{ route('updates.jurisdiction', $policy->jurisdiction->slug) }}" class="hover:text-brand-navy">All updates for {{ $policy->jurisdiction->short_name ?: $policy->jurisdiction->name }}</a> · <a href="{{ route('updates.jurisdiction.feed', $policy->jurisdiction->slug) }}" class="hover:text-brand-navy" data-track="rss_click">RSS</a> · <a href="{{ route('updates.index') }}" class="hover:text-brand-navy">Updates hub</a></p>@endif
                <p class="mt-2 text-xs text-brand-muted">Record version {{ $policy->content_version }}@if($policy->change_summary): {{ $policy->change_summary }}@endif. Full edit history is in the <a href="{{ config('aipolicytracker.github_url') }}" rel="noopener">GitHub repository</a>.</p>
            </section>
            @endif

            <x-site.faq :items="$seo->faqItems()" />
            <x-site.disclaimer class="mt-8" />
        </div>

        <aside class="lg:col-span-1 space-y-6">
            <div class="lg:sticky lg:top-4 space-y-6">
                @if($controls->isNotEmpty())
                <div class="card-flat p-4 text-sm">
                    <p class="font-semibold text-brand-navy">Controls that meet its duties</p>
                    <p class="mt-1 text-xs text-brand-muted">Duties satisfied / duties served, out of this instrument's recorded duties.</p>
                    <ul class="mt-2 space-y-1.5">@foreach($controls->take(7) as $r)<li class="flex items-baseline justify-between gap-2"><a href="{{ $r['control']->url() }}" class="text-brand-navy hover:underline">{{ $r['control']->title }}</a><span class="font-mono text-xs tabular-nums text-brand-muted whitespace-nowrap">{{ $r['satisfies'] }} / {{ $r['duties'] }}</span></li>@endforeach</ul>
                    @if($controls->count() > 7)<p class="mt-2 text-xs"><a href="{{ route('controls.index') }}">All {{ $controls->count() }} controls &rarr;</a></p>@endif
                </div>
                @endif
                <div class="card-flat p-4 text-sm">
                    <p class="font-semibold text-brand-navy">At a glance</p>
                    <dl class="mt-2 space-y-1.5 text-brand-body">
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Issuing body</dt><dd class="text-right">{{ $policy->issuing_body }}</dd></div>
                        @if($policy->adopted_on)<div class="flex justify-between gap-2"><dt class="text-brand-muted">Adopted</dt><dd>{{ $policy->adopted_on->format('j M Y') }}</dd></div>@endif
                        @if($policy->in_force_on)<div class="flex justify-between gap-2"><dt class="text-brand-muted">In force</dt><dd>{{ $policy->in_force_on->format('j M Y') }}</dd></div>@endif
                        @if($policy->applies_from)<div class="flex justify-between gap-2"><dt class="text-brand-muted">Applies from</dt><dd>{{ $policy->applies_from->format('j M Y') }}</dd></div>@endif
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Review status</dt><dd>{{ $policy->reviewStatusEnum()->label() }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Source tier</dt><dd>{{ $policy->source_tier }}</dd></div>
                    </dl>
                </div>
                <x-site.correction-cta subject-type="policy" :subject-slug="$policy->slug" :save-title="$name" :save-url="$policy->url()" :save-meta="$policy->jurisdiction->name" class="flex-col [&>*]:w-full" />
                <x-site.subscribe-form source="policy" :topic="$policy->slug" :topic-label="$name" class="!pt-3 text-sm" compact />
                @if(isset($risksAddressed) && $risksAddressed->isNotEmpty())
                <div class="text-sm"><p class="font-semibold text-brand-navy">AI risks this instrument addresses</p><ul class="mt-2 space-y-1.5">@foreach($risksAddressed as $d)<li><a href="{{ \App\Support\RiskTaxonomy::domainUrl($d['id']) }}" class="text-brand-body hover:underline">{{ $d['name'] }}</a> <span class="text-xs text-brand-muted">({{ $d['incidents'] ? number_format($d['incidents']).' recorded incidents' : 'no incidents classified' }})</span></li>@endforeach</ul><p class="meta mt-1">Mapped through the instrument's recorded use cases.</p></div>
                @endif
                @if($related->isNotEmpty())
                <div class="text-sm"><p class="font-semibold text-brand-navy">Related policies</p><ul class="mt-2 space-y-1.5">@foreach($related as $r)<li><a href="{{ $r->url() }}" class="text-brand-body hover:underline">{{ $r->short_title ?: $r->title }}</a> <span class="text-xs text-brand-muted">({{ $r->jurisdiction->short_name ?: $r->jurisdiction->name }})</span></li>@endforeach</ul></div>
                @endif
                @if($sameJurisdiction->isNotEmpty())
                <div class="text-sm"><p class="font-semibold text-brand-navy">More from {{ $policy->jurisdiction->name }}</p><ul class="mt-2 space-y-1.5">@foreach($sameJurisdiction as $r)<li><a href="{{ $r->url() }}" class="text-brand-body hover:underline">{{ $r->short_title ?: $r->title }}</a></li>@endforeach</ul></div>
                @endif
                @if(!empty($policy->related_frameworks))
                <div class="text-sm"><p class="font-semibold text-brand-navy">Framework crosswalks</p><ul class="mt-2 space-y-1.5">@if(in_array('iso_42001', $policy->related_frameworks))<li><a href="{{ route('guides.show', 'iso-42001-vs-eu-ai-act') }}" class="text-brand-body hover:underline">ISO/IEC 42001 vs EU AI Act</a></li>@endif @if(in_array('nist_ai_rmf', $policy->related_frameworks))<li><a href="{{ route('guides.show', 'nist-ai-rmf-vs-eu-ai-act') }}" class="text-brand-body hover:underline">NIST AI RMF vs EU AI Act</a></li>@endif</ul></div>
                @endif
                <x-site.certifyi-cta label="Export these obligations to a compliance workflow" />
            </div>
        </aside>
    </div>
</div>
@endsection
