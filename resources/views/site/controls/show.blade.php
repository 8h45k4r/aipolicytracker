@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="badge bg-brand-navy text-white ring-brand-navy">{{ $control->kindLabel() }}</span>
            <span class="text-brand-muted">Owner: {{ $control->owner_role }}</span>
            <span class="text-brand-muted" aria-hidden="true">·</span>
            <span class="text-brand-muted">{{ $control->frequencyLabel() }}</span>
        </div>
        <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">{{ $control->title }}</h1>
        <p class="mt-3 max-w-[64ch] text-lg leading-8 text-brand-body">{{ $control->purpose }}</p>
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            <a href="{{ route('verification') }}" class="no-underline"><x-site.verified :record="$control" class="!text-sm" /></a>
            <a href="{{ route('controls.context', $control->slug) }}" class="text-brand-muted hover:text-brand-navy" title="This control as one Markdown file, with its provenance">Context file</a>
        </div>
    </header>

    <dl class="mt-8 grid grid-cols-2 sm:grid-cols-4 gap-3">
        <x-site.stat label="Duties satisfied" :value="$duties->where('pivot.relationship', 'satisfies')->count()" note="done properly, does the work" />
        <x-site.stat label="Duties supported" :value="$duties->where('pivot.relationship', 'supports')->count()" note="contributes; the duty needs more" />
        <x-site.stat label="Jurisdictions" :value="$byJurisdiction->count()" href="#duties-heading" />
        <x-site.stat label="Evidence items" :value="$control->evidence->count()" href="#evidence-heading" />
    </dl>

    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            @if($control->description)
            <section aria-labelledby="how-heading"><h2 id="how-heading" class="section-title">How is it implemented?</h2><p class="prose-policy mt-2">{{ $control->description }}</p></section>
            @endif

            <section aria-labelledby="duties-heading" class="mt-8">
                <h2 id="duties-heading" class="section-title">Which legal duties does it serve?</h2>
                <p class="mt-1 text-xs text-brand-muted"><span class="font-medium text-brand-navy">Satisfies</span> means the control, operated properly, does the work the duty asks for. <span class="font-medium text-brand-navy">Supports</span> means it contributes but the duty needs more. The official text decides; open it before relying on either.</p>
                @foreach($byJurisdiction as $name => $rows)
                <div class="mt-5">
                    <h3 class="flex items-baseline gap-2 text-sm font-semibold text-brand-navy"><a href="{{ $rows->first()->policyInstrument->jurisdiction->url() }}" class="no-underline hover:underline">{{ $name }}</a> <span class="text-xs font-normal text-brand-muted">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('duty', $rows->count()) }}</span></h3>
                    <ul class="mt-2 divide-y divide-brand-line border-y border-brand-line">
                        @foreach($rows as $o)
                        <li class="py-3 text-sm">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="badge {{ $o->pivot->relationship === 'satisfies' ? 'bg-state-goodbg text-state-good ring-state-good/20' : 'bg-brand-paper text-brand-body ring-brand-line' }}">{{ $o->pivot->relationship }}</span>
                                <span class="badge {{ $o->is_binding ? 'bg-brand-navy text-white ring-brand-navy' : 'bg-brand-paper text-brand-body ring-brand-line' }}">{{ $o->is_binding ? 'Legal requirement' : 'Voluntary' }}</span>
                                @if($o->pivot->confidence_level !== 'medium')<span class="text-brand-muted">confidence {{ $o->pivot->confidence_level }}</span>@endif
                            </div>
                            <a href="{{ $o->url() }}" class="mt-1 block font-medium text-brand-navy no-underline hover:underline">{{ $o->title }}</a>
                            <p class="text-xs text-brand-muted"><a href="{{ $o->policyInstrument->url() }}" class="hover:text-brand-navy">{{ $o->policyInstrument->short_title ?: $o->policyInstrument->title }}</a>@if($o->source_reference) · {{ $o->source_reference }}@endif @if($o->applies_from) · applies from {{ $o->applies_from->format('j M Y') }}@endif</p>
                            @if($o->pivot->note)<p class="mt-1 text-brand-body">{{ $o->pivot->note }}</p>@endif
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </section>

            <section aria-labelledby="evidence-heading" class="mt-8">
                <h2 id="evidence-heading" class="section-title">What evidence shows it is operating?</h2>
                <div class="table-wrap mt-3"><table><caption class="sr-only">Evidence this control produces</caption>
                    <thead><tr><th scope="col">Evidence</th><th scope="col">Type</th><th scope="col">What it shows</th></tr></thead>
                    <tbody>@foreach($control->evidence as $e)<tr><td class="font-medium text-brand-navy">{{ $e->title }}</td><td class="whitespace-nowrap">{{ $evidenceTypes[$e->evidence_type]->name ?? $e->evidence_type }}</td><td>{{ $e->description }}</td></tr>@endforeach</tbody>
                </table></div>
                <p class="mt-2 text-xs text-brand-muted">Owner: {{ $control->owner_role }}. Frequency: {{ strtolower($control->frequencyLabel()) }}.</p>
            </section>

            @if($risks->isNotEmpty())
            <section aria-labelledby="risks-heading" class="mt-8">
                <h2 id="risks-heading" class="section-title">Which risks does it address?</h2>
                <p class="mt-1 text-xs text-brand-muted">Subdomains of the MIT AI Risk Repository, with the incidents the AI Incident Database has recorded under each. Counts are live; they say how often a risk has materialised, not how well this control prevents it.</p>
                <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">
                    @foreach($risks as $r)
                    <li class="py-2.5 flex flex-wrap items-baseline justify-between gap-2"><span><span class="font-mono text-xs text-brand-muted">{{ $r['id'] }}</span> <a href="{{ $r['url'] }}" class="font-medium text-brand-navy no-underline hover:underline">{{ $r['name'] }}</a> <span class="text-xs text-brand-muted">{{ $r['domain'] }}</span></span><span class="font-mono text-xs tabular-nums text-brand-body">{{ number_format($r['incidents']) }} incidents · {{ number_format($r['risks']) }} risk entries</span></li>
                    @endforeach
                </ul>
            </section>
            @endif

            @if($control->frameworkReferences->isNotEmpty())
            <section aria-labelledby="frameworks-heading" class="mt-8">
                <h2 id="frameworks-heading" class="section-title">Which standards clauses does it correspond to?</h2>
                <p class="mt-1 text-xs text-brand-muted">Clause numbers only. A reference means the standard asks for overlapping work, so evidence may be reusable; it never means the standard discharges a legal duty.</p>
                <div class="table-wrap mt-3"><table><caption class="sr-only">Framework references</caption>
                    <thead><tr><th scope="col">Framework</th><th scope="col">Reference</th><th scope="col">Note</th><th scope="col">Confidence</th></tr></thead>
                    <tbody>@foreach($control->frameworkReferences as $r)<tr><td class="whitespace-nowrap font-medium">@if(config('frameworks.'.$r->framework))<a href="{{ route('frameworks.show', config('frameworks.'.$r->framework.'.slug')) }}" class="text-brand-navy hover:underline">{{ $r->frameworkShort() }}</a>@else{{ $r->frameworkName() }}@endif</td><td>{{ $r->reference }}</td><td>{{ $r->note }}</td><td>{{ $r->confidence_level }}</td></tr>@endforeach</tbody>
                </table></div>
            </section>
            @endif

            <x-site.cite :title="$control->title" :url="$control->url()" class="mt-8" />
            <x-site.disclaimer class="mt-8" />
        </div>

        <aside class="space-y-6">
            <div class="lg:sticky lg:top-4 space-y-6">
                <div class="card-flat p-4 text-sm">
                    <p class="font-semibold text-brand-navy">Governance card</p>
                    <dl class="mt-2 space-y-1.5 text-brand-body">
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Kind</dt><dd>{{ $control->kindLabel() }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Owner</dt><dd class="text-right">{{ $control->owner_role }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Frequency</dt><dd class="text-right">{{ $control->frequencyLabel() }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Binding duties</dt><dd>{{ $duties->where('is_binding', true)->count() }} of {{ $duties->count() }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Review status</dt><dd>{{ $control->reviewStatusEnum()->label() }}</dd></div>
                    </dl>
                    <ol class="mt-3 list-decimal pl-4 text-xs text-brand-body space-y-1">
                        <li>Confirm which of the duties above reach you (<a href="{{ route('tools.applicability') }}">applicability check</a>).</li>
                        <li>Assign the owner and the frequency.</li>
                        <li>Operate the control and keep the evidence listed.</li>
                        <li>Re-check when a duty changes (<a href="{{ route('changes.index') }}">change log</a>).</li>
                    </ol>
                </div>
                @if($related->isNotEmpty())
                <div class="card-flat p-4 text-sm">
                    <p class="font-semibold text-brand-navy">Related controls</p>
                    <ul class="mt-2 space-y-1.5">@foreach($related as $r)<li><a href="{{ $r->url() }}" class="text-brand-navy hover:underline">{{ $r->title }}</a> <span class="text-xs text-brand-muted">{{ $r->kindLabel() }}</span></li>@endforeach</ul>
                </div>
                @endif
                <x-site.correction-cta subject-type="control" :subject-slug="$control->slug" :save-title="$control->title" :save-url="$control->url()" save-meta="Control" class="flex-col [&>*]:w-full" />
                <x-site.certifyi-cta label="Run this control as a tracked task" />
            </div>
        </aside>
    </div>
</div>
@endsection
