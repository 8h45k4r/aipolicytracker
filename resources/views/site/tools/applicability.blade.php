@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">AI regulation applicability check</h1>
    <p class="mt-2 max-w-3xl text-brand-body">Answer a few questions to see which recorded policies and obligations overlap with your situation, what to investigate next and where the official sources are.</p>
    <x-site.disclaimer class="mt-3 max-w-3xl">Educational screening only; not legal advice. Results reflect the scope recorded in our data, not a determination that any law applies to you.</x-site.disclaimer>

    <div class="mt-6 grid gap-10 lg:grid-cols-3">
        <form method="get" action="{{ route('tools.applicability') }}" class="card-flat p-4 sm:p-5 space-y-5 lg:col-span-1 self-start" data-track="applicability_submit" aria-label="Screening questionnaire">
            <x-site.jurisdiction-picker name="jurisdictions" :jurisdictions="$jurisdictions" :selected="$answers['jurisdictions']" required
                legend="1. Markets or jurisdictions"
                hint="Where you sell, deploy or have users. The number beside a name is how many instruments are recorded for it." />
            @if(request()->has('jurisdictions') && $answers['jurisdictions'] === [])<p class="-mt-3 text-xs text-state-bad" role="alert">Select at least one jurisdiction.</p>@endif
            <div><label for="a-role" class="label">2. Your organisation's role</label><select id="a-role" name="role" class="input"><option value="">Not sure</option>@foreach($actors as $a)<option value="{{ $a->slug }}" @selected($answers['role'] === $a->slug)>{{ $a->name }}</option>@endforeach</select></div>
            <div><label for="a-use" class="label">3. Main AI use case</label><select id="a-use" name="use_case" class="input"><option value="">Not sure</option>@foreach($useCases as $u)<option value="{{ $u->slug }}" @selected($answers['use_case'] === $u->slug)>{{ $u->name }}</option>@endforeach</select></div>
            <div><label for="a-sector" class="label">4. Industry or sector</label><select id="a-sector" name="sector" class="input"><option value="">Not sure</option>@foreach($sectors as $s)<option value="{{ $s->slug }}" @selected($answers['sector'] === $s->slug)>{{ $s->name }}</option>@endforeach</select></div>
            <fieldset><legend class="label">5. Personal or sensitive data involved?</legend><div class="flex gap-4 text-sm">@foreach(['yes' => 'Yes', 'no' => 'No', 'unsure' => 'Unsure'] as $v => $l)<label class="flex items-center gap-1.5 min-h-[36px]"><input type="radio" name="personal_data" value="{{ $v }}" class="border-brand-line text-brand-blue focus:ring-brand-cyan" @checked($answers['personal_data'] === $v)> {{ $l }}</label>@endforeach</div></fieldset>
            <fieldset><legend class="label">6. Does the use affect any of these areas?</legend><div class="grid gap-1.5">@foreach($domains as $k => $label)<label class="flex items-start gap-2 text-sm min-h-[36px]"><input type="checkbox" name="domains[]" value="{{ $k }}" class="mt-1 rounded border-brand-line text-brand-blue focus:ring-brand-cyan" @checked(in_array($k, $answers['domains'], true))> {{ $label }}</label>@endforeach</div></fieldset>
            <fieldset><legend class="label">7. Generative or general-purpose AI involved?</legend><div class="flex gap-4 text-sm">@foreach(['yes' => 'Yes', 'no' => 'No', 'unsure' => 'Unsure'] as $v => $l)<label class="flex items-center gap-1.5 min-h-[36px]"><input type="radio" name="genai" value="{{ $v }}" class="border-brand-line text-brand-blue focus:ring-brand-cyan" @checked($answers['genai'] === $v)> {{ $l }}</label>@endforeach</div></fieldset>
            <div class="flex gap-2"><button type="submit" class="btn-primary flex-1">Run screening</button><a href="{{ route('tools.applicability') }}" class="btn-secondary">Reset</a></div>
        </form>

        <div class="lg:col-span-2 min-w-0">
            @if(!$submitted)
                <div class="rounded-sm border border-dashed border-brand-line p-8 text-sm text-brand-muted"><p class="font-medium text-brand-navy">Your results will appear here.</p><p class="mt-1">The screening lists recorded policies whose scope overlaps your answers, the obligations that mention your role and use case, questions to investigate, a starter checklist and official sources. Nothing is stored.</p></div>
            @else
                @if(session('error'))<p class="mb-4 rounded-sm border border-state-bad/30 bg-state-badbg px-3 py-2 text-sm text-state-bad" role="alert">{{ session('error') }}</p>@endif
                <section class="mb-6 card-flat p-4" aria-labelledby="r-watch">
                    <h2 id="r-watch" class="section-title !text-lg">Watch this screening</h2>
                    @if($savedProfile)
                        <p class="mt-1 text-sm text-brand-body">Saved as <strong>{{ $savedProfile->name }}</strong>. Changes in scope reach you in the daily alert. <a href="{{ route('following.index') }}">Manage profiles</a>.</p>
                    @elseif($canSave)
                        <p class="mt-1 text-sm text-brand-body">Save these answers and the daily alert will tell you when a change may affect this system, with the official source to check.</p>
                        <form method="post" action="{{ route('profiles.store') }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf
                            @foreach($answers as $key => $value)
                                @if(is_array($value))@foreach($value as $v)<input type="hidden" name="answers[{{ $key }}][]" value="{{ $v }}">@endforeach
                                @elseif($value !== null)<input type="hidden" name="answers[{{ $key }}]" value="{{ $value }}">@endif
                            @endforeach
                            <div class="flex-1 min-w-[220px]"><label for="profile-name" class="label">Name this system or programme</label><input id="profile-name" name="name" class="input" required maxlength="120" placeholder="e.g. Customer support agent, EU and Australia"></div>
                            <button type="submit" class="btn-primary" data-track="profile_save">Save and alert me</button>
                        </form>
                    @else
                        <p class="mt-1 text-sm text-brand-body">Sign in to save this screening and get a daily alert when a change may affect it, naming the system and linking the official source. It is free.</p>
                    @endif
                    <p class="mt-2 meta">Screening is relevance, not a legal determination. Nothing about your systems is published.</p>
                </section>
                <section aria-labelledby="r-policies"><h2 id="r-policies" class="section-title">Likely relevant policies</h2>
                    <p class="mt-1 text-xs text-brand-muted">Ranked by overlap with your answers. "Why" explains the match; it is not a legal conclusion.</p>
                    <div class="mt-2 divide-y divide-brand-line border-y border-brand-line">
                    @forelse($result['policies']->take(10) as $item)
                        <article class="py-3"><div class="flex flex-wrap items-center gap-2 text-xs text-brand-muted"><span class="font-medium text-brand-body">{{ $item['policy']->jurisdiction->name }}</span><x-site.status-badge :status="$item['policy']->statusEnum()" />@if($item['policy']->is_binding)<span class="badge bg-brand-navy text-white ring-brand-navy">Binding</span>@endif</div>
                        <h3 class="mt-1 font-semibold text-brand-navy"><a href="{{ $item['policy']->url() }}" class="hover:underline">{{ $item['policy']->short_title ?: $item['policy']->title }}</a></h3>
                        <p class="text-xs text-brand-muted">Why: {{ $item['why'] ? implode('; ', $item['why']) : 'recorded for a selected jurisdiction' }}. @if($item['policy']->official_source_url)<a href="{{ $item['policy']->official_source_url }}" rel="noopener" class="text-brand-blue" data-track="source_click">Official source</a>@endif</p></article>
                    @empty<p class="py-4 text-sm text-brand-muted">No policies recorded for the selected jurisdictions yet.</p>@endforelse
                    </div>
                </section>
                <section aria-labelledby="r-obligations" class="mt-8"><h2 id="r-obligations" class="section-title">Obligations to review</h2>
                    <div class="mt-2 divide-y divide-brand-line border-y border-brand-line">
                    @forelse($result['obligations']->take(15) as $o)
                        <article class="py-3"><div class="flex flex-wrap items-center gap-2 text-xs"><span class="badge {{ $o->is_binding ? 'bg-brand-navy text-white ring-brand-navy' : 'bg-brand-paper text-brand-body ring-brand-line' }}">{{ $o->is_binding ? 'Legal requirement' : 'Voluntary' }}</span><span class="text-brand-muted">{{ $o->policyInstrument->jurisdiction->name }} · {{ $o->policyInstrument->short_title ?: $o->policyInstrument->title }}</span></div>
                        <h3 class="mt-1 text-sm font-semibold text-brand-navy"><a href="{{ $o->url() }}" class="hover:underline">{{ $o->title }}</a></h3></article>
                    @empty<p class="py-4 text-sm text-brand-muted">No obligations matched your role and use case. Broaden the answers or browse the <a href="{{ route('obligations.index') }}">obligation explorer</a>.</p>@endforelse
                    </div>
                </section>
                <section aria-labelledby="r-questions" class="mt-8"><h2 id="r-questions" class="section-title">Questions to investigate</h2><ol class="mt-2 list-decimal pl-5 space-y-1.5 text-sm text-brand-body">@foreach($result['questions'] as $q)<li>{{ $q }}</li>@endforeach</ol></section>
                <section aria-labelledby="r-checklist" class="mt-8"><h2 id="r-checklist" class="section-title">Starter action checklist</h2><ul class="mt-2 space-y-1.5 text-sm text-brand-body">@foreach($result['checklist'] as $c)<li class="flex gap-2"><span aria-hidden="true" class="mt-1 inline-block h-4 w-4 rounded border border-brand-line"></span>{{ $c }}</li>@endforeach</ul>
                    <div class="mt-4 flex flex-wrap gap-2"><button type="button" class="btn-secondary" data-copy-link>Copy link to these results</button><a href="{{ route('compare.index', ['j' => implode(',', $answers['jurisdictions'])]) }}" class="btn-secondary" rel="nofollow">Compare selected jurisdictions</a></div>
                    <x-site.certifyi-cta class="mt-4" label="Turn this checklist into tracked workflows" />
                </section>
            @endif
        </div>
    </div>
</div>
@endsection
