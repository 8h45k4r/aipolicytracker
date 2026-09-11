@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">AI regulation applicability check</h1>
    <p class="mt-2 max-w-3xl text-slate-700">Answer a few questions to see which recorded policies and obligations overlap with your situation, what to investigate next and where the official sources are.</p>
    <x-site.disclaimer class="mt-3 max-w-3xl">Educational screening only; not legal advice. Results reflect the scope recorded in our data, not a determination that any law applies to you.</x-site.disclaimer>

    <div class="mt-6 grid gap-10 lg:grid-cols-3">
        <form method="get" action="{{ route('tools.applicability') }}" class="card-flat p-4 sm:p-5 space-y-5 lg:col-span-1 self-start" data-track="applicability_submit" aria-label="Screening questionnaire">
            <fieldset><legend class="label">1. Markets or jurisdictions <span class="text-rose-700" aria-hidden="true">*</span></legend>
                <div class="grid gap-1.5">@foreach($jurisdictions as $j)<label class="flex items-center gap-2 text-sm min-h-[36px]"><input type="checkbox" name="jurisdictions[]" value="{{ $j->slug }}" class="rounded border-slate-300 text-teal-700 focus:ring-teal-600" @checked(in_array($j->slug, $answers['jurisdictions'], true))> {{ $j->name }}</label>@endforeach</div>
                @if(request()->has('jurisdictions') && $answers['jurisdictions'] === [])<p class="mt-1 text-xs text-rose-700" role="alert">Select at least one jurisdiction.</p>@endif
            </fieldset>
            <div><label for="a-role" class="label">2. Your organisation's role</label><select id="a-role" name="role" class="input"><option value="">Not sure</option>@foreach($actors as $a)<option value="{{ $a->slug }}" @selected($answers['role'] === $a->slug)>{{ $a->name }}</option>@endforeach</select></div>
            <div><label for="a-use" class="label">3. Main AI use case</label><select id="a-use" name="use_case" class="input"><option value="">Not sure</option>@foreach($useCases as $u)<option value="{{ $u->slug }}" @selected($answers['use_case'] === $u->slug)>{{ $u->name }}</option>@endforeach</select></div>
            <div><label for="a-sector" class="label">4. Industry or sector</label><select id="a-sector" name="sector" class="input"><option value="">Not sure</option>@foreach($sectors as $s)<option value="{{ $s->slug }}" @selected($answers['sector'] === $s->slug)>{{ $s->name }}</option>@endforeach</select></div>
            <fieldset><legend class="label">5. Personal or sensitive data involved?</legend><div class="flex gap-4 text-sm">@foreach(['yes' => 'Yes', 'no' => 'No', 'unsure' => 'Unsure'] as $v => $l)<label class="flex items-center gap-1.5 min-h-[36px]"><input type="radio" name="personal_data" value="{{ $v }}" class="border-slate-300 text-teal-700 focus:ring-teal-600" @checked($answers['personal_data'] === $v)> {{ $l }}</label>@endforeach</div></fieldset>
            <fieldset><legend class="label">6. Does the use affect any of these areas?</legend><div class="grid gap-1.5">@foreach($domains as $k => $label)<label class="flex items-start gap-2 text-sm min-h-[36px]"><input type="checkbox" name="domains[]" value="{{ $k }}" class="mt-1 rounded border-slate-300 text-teal-700 focus:ring-teal-600" @checked(in_array($k, $answers['domains'], true))> {{ $label }}</label>@endforeach</div></fieldset>
            <fieldset><legend class="label">7. Generative or general-purpose AI involved?</legend><div class="flex gap-4 text-sm">@foreach(['yes' => 'Yes', 'no' => 'No', 'unsure' => 'Unsure'] as $v => $l)<label class="flex items-center gap-1.5 min-h-[36px]"><input type="radio" name="genai" value="{{ $v }}" class="border-slate-300 text-teal-700 focus:ring-teal-600" @checked($answers['genai'] === $v)> {{ $l }}</label>@endforeach</div></fieldset>
            <div class="flex gap-2"><button type="submit" class="btn-primary flex-1">Run screening</button><a href="{{ route('tools.applicability') }}" class="btn-secondary">Reset</a></div>
        </form>

        <div class="lg:col-span-2 min-w-0">
            @if(!$submitted)
                <div class="rounded-lg border border-dashed border-slate-300 p-8 text-sm text-slate-600"><p class="font-medium text-slate-900">Your results will appear here.</p><p class="mt-1">The screening lists recorded policies whose scope overlaps your answers, the obligations that mention your role and use case, questions to investigate, a starter checklist and official sources. Nothing is stored.</p></div>
            @else
                <section aria-labelledby="r-policies"><h2 id="r-policies" class="section-title">Likely relevant policies</h2>
                    <p class="mt-1 text-xs text-slate-500">Ranked by overlap with your answers. "Why" explains the match; it is not a legal conclusion.</p>
                    <div class="mt-2 divide-y divide-slate-200 border-y border-slate-200">
                    @forelse($result['policies']->take(10) as $item)
                        <article class="py-3"><div class="flex flex-wrap items-center gap-2 text-xs text-slate-500"><span class="font-medium text-slate-700">{{ $item['policy']->jurisdiction->name }}</span><x-site.status-badge :status="$item['policy']->statusEnum()" />@if($item['policy']->is_binding)<span class="badge bg-slate-900 text-white ring-slate-900">Binding</span>@endif</div>
                        <h3 class="mt-1 font-semibold text-slate-900"><a href="{{ $item['policy']->url() }}" class="hover:underline">{{ $item['policy']->short_title ?: $item['policy']->title }}</a></h3>
                        <p class="text-xs text-slate-600">Why: {{ $item['why'] ? implode('; ', $item['why']) : 'recorded for a selected jurisdiction' }}. @if($item['policy']->official_source_url)<a href="{{ $item['policy']->official_source_url }}" rel="noopener" class="text-teal-800" data-track="source_click">Official source</a>@endif</p></article>
                    @empty<p class="py-4 text-sm text-slate-600">No policies recorded for the selected jurisdictions yet.</p>@endforelse
                    </div>
                </section>
                <section aria-labelledby="r-obligations" class="mt-8"><h2 id="r-obligations" class="section-title">Obligations to review</h2>
                    <div class="mt-2 divide-y divide-slate-200 border-y border-slate-200">
                    @forelse($result['obligations']->take(15) as $o)
                        <article class="py-3"><div class="flex flex-wrap items-center gap-2 text-xs"><span class="badge {{ $o->is_binding ? 'bg-slate-900 text-white ring-slate-900' : 'bg-slate-100 text-slate-700 ring-slate-500/20' }}">{{ $o->is_binding ? 'Legal requirement' : 'Voluntary' }}</span><span class="text-slate-500">{{ $o->policyInstrument->jurisdiction->name }} · {{ $o->policyInstrument->short_title ?: $o->policyInstrument->title }}</span></div>
                        <h3 class="mt-1 text-sm font-semibold text-slate-900"><a href="{{ $o->url() }}" class="hover:underline">{{ $o->title }}</a></h3></article>
                    @empty<p class="py-4 text-sm text-slate-600">No obligations matched your role and use case. Broaden the answers or browse the <a href="{{ route('obligations.index') }}">obligation explorer</a>.</p>@endforelse
                    </div>
                </section>
                <section aria-labelledby="r-questions" class="mt-8"><h2 id="r-questions" class="section-title">Questions to investigate</h2><ol class="mt-2 list-decimal pl-5 space-y-1.5 text-sm text-slate-700">@foreach($result['questions'] as $q)<li>{{ $q }}</li>@endforeach</ol></section>
                <section aria-labelledby="r-checklist" class="mt-8"><h2 id="r-checklist" class="section-title">Starter action checklist</h2><ul class="mt-2 space-y-1.5 text-sm text-slate-700">@foreach($result['checklist'] as $c)<li class="flex gap-2"><span aria-hidden="true" class="mt-1 inline-block h-4 w-4 rounded border border-slate-400"></span>{{ $c }}</li>@endforeach</ul>
                    <div class="mt-4 flex flex-wrap gap-2"><button type="button" class="btn-secondary" data-copy-link>Copy link to these results</button><a href="{{ route('compare.index', ['j' => implode(',', $answers['jurisdictions'])]) }}" class="btn-secondary" rel="nofollow">Compare selected jurisdictions</a></div>
                    <x-site.certifyi-cta class="mt-4" label="Turn this checklist into tracked workflows" />
                </section>
            @endif
        </div>
    </div>
</div>
@endsection
