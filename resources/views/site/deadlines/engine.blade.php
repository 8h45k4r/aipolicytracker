@extends('site.layouts.app')
@section('content')
@php($hidden = function (array $skip = []) use ($query) { $out = ''; foreach ($query as $k => $v) { if (in_array($k, $skip, true)) continue; foreach ((array) $v as $x) { $out .= '<input type="hidden" name="'.e($k).(is_array($v) ? '[]' : '').'" value="'.e($x).'">'; } } return $out; })
@php($stepTitles = ['Where do you operate?', 'What is your role?', 'What kind of system, and which risk tier?', 'Which sector and use case?', 'Your timeline'])
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-2">Deadline engine</p>
    <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Which AI regulation date applies to you?</h1>
    <p class="mt-2 max-w-3xl text-brand-body">Five questions, then a personal timeline built only from the dates on record, with the reason each applies, the original date where one has moved, and .ics and PDF exports. Works without JavaScript.</p>
    <x-site.disclaimer class="mt-3 max-w-3xl">A filter over recorded dates by recorded scope; not a determination that any law applies to you.</x-site.disclaimer>

    <ol class="mt-6 flex flex-wrap gap-2 text-xs" aria-label="Steps">
        @foreach($stepTitles as $i => $t)
        <li class="chip !min-h-0 !py-1 {{ $i + 1 === $step ? 'ring-2 ring-brand-navy font-semibold' : ($i + 1 < $step ? '' : 'border-dashed') }}"@if($i + 1 === $step) aria-current="step"@endif>{{ $i + 1 }}. {{ $t }}</li>
        @endforeach
    </ol>

    @unless($done)
    <form method="get" action="{{ route('deadlines.engine') }}" class="mt-6 card-flat p-4 sm:p-5 space-y-5 max-w-3xl" aria-label="Step {{ $step }} of 5: {{ $stepTitles[$step - 1] }}">
        @if($step === 1)
        {!! $hidden(['jurisdictions']) !!}
        <x-site.jurisdiction-picker name="jurisdictions" :jurisdictions="$jurisdictions" :selected="$answers['jurisdictions']" required legend="1. Where do you operate?" hint="Markets where you sell, deploy or have users. Pick up to eight." />
        @if(request()->has('jurisdictions') && $answers['jurisdictions'] === [])<p class="-mt-3 text-xs text-state-bad" role="alert">Select at least one jurisdiction.</p>@endif
        @elseif($step === 2)
        {!! $hidden(['role']) !!}
        <div><label for="d-role" class="label">2. Your organisation's role for this system</label><select id="d-role" name="role" class="input max-w-md"><option value="">Not sure / any role</option>@foreach($options['roles'] as $slug => $name)<option value="{{ $slug }}" @selected($answers['role'] === $slug)>{{ $name }}</option>@endforeach</select><p class="mt-1 text-xs text-brand-muted">Provider if you build or place it on the market; deployer if you use it under your authority.</p></div>
        @elseif($step === 3)
        {!! $hidden(['system_types', 'risk']) !!}
        <fieldset><legend class="label">3a. What kind of AI system?</legend><div class="mt-2 grid gap-1.5 sm:grid-cols-2">@foreach($options['system_types'] as $slug => $name)<label class="flex items-center gap-2 text-sm text-brand-body"><input type="checkbox" name="system_types[]" value="{{ $slug }}" class="rounded-sm border-brand-line text-brand-navy focus:ring-brand-navy" @checked(in_array($slug, $answers['system_types'], true))>{{ $name }}</label>@endforeach</div><p class="mt-1 text-xs text-brand-muted">Leave all unticked if unsure; only instruments that name a kind of system are narrowed by this.</p></fieldset>
        <div><label for="d-risk" class="label">3b. Risk tier, if you know it</label><select id="d-risk" name="risk" class="input max-w-md"><option value="">Not sure</option>@foreach($options['risks'] as $slug => $name)<option value="{{ $slug }}" @selected($answers['risk'] === $slug)>{{ $name }}</option>@endforeach</select></div>
        @elseif($step === 4)
        {!! $hidden(['sectors', 'use_cases']) !!}
        <fieldset><legend class="label">4a. Sector</legend><div class="mt-2 grid gap-1.5 sm:grid-cols-2">@foreach($options['sectors'] as $slug => $name)<label class="flex items-center gap-2 text-sm text-brand-body"><input type="checkbox" name="sectors[]" value="{{ $slug }}" class="rounded-sm border-brand-line text-brand-navy focus:ring-brand-navy" @checked(in_array($slug, $answers['sectors'], true))>{{ $name }}</label>@endforeach</div></fieldset>
        <fieldset><legend class="label">4b. Use case</legend><div class="mt-2 grid gap-1.5 sm:grid-cols-2">@foreach($options['use_cases'] as $slug => $name)<label class="flex items-center gap-2 text-sm text-brand-body"><input type="checkbox" name="use_cases[]" value="{{ $slug }}" class="rounded-sm border-brand-line text-brand-navy focus:ring-brand-navy" @checked(in_array($slug, $answers['use_cases'], true))>{{ $name }}</label>@endforeach</div></fieldset>
        @endif
        <input type="hidden" name="step" value="{{ $step + 1 }}">
        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-primary">{{ $step === 4 ? 'Show my timeline' : 'Next' }}</button>
            @if($step > 1)<a href="{{ route('deadlines.engine', $query + ['step' => $step - 1]) }}" class="btn-secondary">Back</a>@endif
            @if($step > 1 && $step < 5)<a href="{{ route('deadlines.engine', $query + ['step' => 5]) }}" class="text-sm text-brand-blue hover:underline">Skip to the timeline</a>@endif
        </div>
    </form>
    @else
    <section class="mt-6 card-flat p-5" aria-labelledby="summary-heading">
        <h2 id="summary-heading" class="sr-only">Summary</h2>
        <p class="text-brand-navy text-base sm:text-lg leading-relaxed">{{ $summary }}</p>
        <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm text-brand-body">
            <div><dt class="inline text-brand-muted">Markets:</dt> <dd class="inline">{{ collect($answers['jurisdictions'])->map(fn ($s) => $labels['jurisdictions'][$s] ?? $s)->join(', ') }}</dd></div>
            @if($answers['role'])<div><dt class="inline text-brand-muted">Role:</dt> <dd class="inline">{{ $labels['roles'][$answers['role']] ?? $answers['role'] }}</dd></div>@endif
            @if($answers['system_types'])<div><dt class="inline text-brand-muted">System:</dt> <dd class="inline">{{ collect($answers['system_types'])->map(fn ($s) => $labels['system_types'][$s] ?? $s)->join(', ') }}</dd></div>@endif
            @if($answers['risk'])<div><dt class="inline text-brand-muted">Risk tier:</dt> <dd class="inline">{{ $labels['risks'][$answers['risk']] ?? $answers['risk'] }}</dd></div>@endif
            @if($answers['sectors'])<div><dt class="inline text-brand-muted">Sector:</dt> <dd class="inline">{{ collect($answers['sectors'])->map(fn ($s) => $labels['sectors'][$s] ?? $s)->join(', ') }}</dd></div>@endif
            @if($answers['use_cases'])<div><dt class="inline text-brand-muted">Use case:</dt> <dd class="inline">{{ collect($answers['use_cases'])->map(fn ($s) => $labels['use_cases'][$s] ?? $s)->join(', ') }}</dd></div>@endif
        </dl>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('deadlines.engine.ics', $query) }}" class="btn-primary" data-track="deadline_ics">Add to calendar (.ics)</a>
            <a href="{{ route('deadlines.engine.pdf', $query) }}" class="btn-secondary" data-track="deadline_pdf">Download PDF</a>
            <a href="{{ route('deadlines.engine', $query + ['step' => 1]) }}" class="btn-secondary">Change answers</a>
            <a href="{{ route('deadlines.engine') }}" class="text-sm text-brand-blue hover:underline self-center">Start over</a>
        </div>
    </section>

    <section class="mt-8" aria-labelledby="timeline-heading">
        <h2 id="timeline-heading" class="section-title">Your timeline</h2>
        @if($rows->isEmpty())
        <p class="mt-2 text-sm text-brand-body">No recorded date matches these answers. Broaden them (fewer system types or use cases), or check the <a href="{{ route('calendar') }}">full calendar</a>; a jurisdiction with no dated instrument on record shows nothing here.</p>
        @else
        @php($groups = $rows->groupBy(fn ($r) => $r['deadline']->due_on?->format('Y') ?? 'Not yet dated'))
        @foreach($groups as $year => $group)
        <h3 class="mt-5 text-sm font-semibold uppercase tracking-wide text-brand-muted">{{ $year }}</h3>
        <ol class="mt-2 border-l-2 border-brand-line pl-4 space-y-3">
            @foreach($group as $r)
            @php($d = $r['deadline'])
            <li class="relative text-sm">
                <span class="absolute -left-[1.4rem] top-1.5 h-2.5 w-2.5 rounded-full {{ $d->isUpcoming() ? 'bg-brand-blue' : ($d->due_on ? 'bg-brand-muted' : 'bg-brand-line') }}" aria-hidden="true"></span>
                <div class="flex flex-wrap items-center gap-2">
                    <time datetime="{{ $d->due_on?->toDateString() }}" class="font-medium text-brand-navy">{{ $d->displayDate() }}</time>
                    @if($d->isUpcoming())<span class="badge-neutral">upcoming</span>@elseif($d->deadline_status === 'passed')<span class="badge-neutral">passed</span>@elseif($d->deadline_status === 'tbd')<span class="badge-neutral">date not set</span>@endif
                    <span class="meta">confidence {{ $d->confidence_level }}</span>
                </div>
                <p class="mt-0.5 font-medium text-brand-navy">{{ $d->title }}</p>
                <p class="text-xs text-brand-muted"><a href="{{ $d->policyInstrument->url() }}" class="text-brand-blue hover:underline">{{ $d->policyInstrument->short_title ?: $d->policyInstrument->title }}</a> · {{ $d->policyInstrument->jurisdiction->name }}@if($d->obligation) · duty: <a href="{{ $d->obligation->url() }}" class="text-brand-blue hover:underline">{{ $d->obligation->title }}</a>@endif @if($d->source_reference) · {{ $d->source_reference }}@endif</p>
                @if($r['revision'])<p class="mt-0.5 text-xs text-brand-body"><span class="font-medium">Moved:</span> originally {{ $r['revision']->from_due_on?->format('j F Y') ?? ($r['revision']->from_label ?: 'undated') }} → now {{ $d->displayDate() }} <span class="meta">(recorded {{ $r['revision']->changed_at->format('j M Y') }})</span></p>@endif
                @if($d->description)<p class="mt-0.5 text-brand-body">{{ $d->description }}</p>@endif
                <p class="mt-0.5 text-xs text-brand-muted">Shown because it {{ implode(' and ', $r['why']) }}.@if($d->official_source_url) <a href="{{ $d->official_source_url }}" rel="noopener" class="text-brand-blue hover:underline">Official source</a>@endif</p>
            </li>
            @endforeach
        </ol>
        @endforeach
        @endif
    </section>
    @endunless

    <x-site.faq :items="$seo->faqItems()" />
    <p class="mt-6 text-sm text-brand-body">See also: the <a href="{{ route('calendar') }}">deadline calendar</a> with a subscription feed per jurisdiction, the <a href="{{ route('tools.applicability') }}">applicability check</a>, the <a href="{{ route('updates.index') }}">AI policy updates</a> hub and the <a href="{{ route('templates.show', 'global-ai-regulatory-applicability-matrix') }}">applicability matrix template</a>.</p>
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
