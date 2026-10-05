@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Free AI self-assessments</h1>
    <p class="mt-2 max-w-3xl text-brand-body">Know where you stand before an auditor, a customer or a regulator asks. {{ $all->count() }} short questionnaires covering the EU AI Act, ISO/IEC 42001, the NIST AI RMF, US state AI laws, AI security and AI governance. Answer one in about six minutes to see a score for each domain; ask for the PDF report by email. No account needed.</p>
    <p class="mt-2 text-xs text-brand-muted">{{ config('assessments.provider.disclosure') }} A score is a self-check, not an audit, a certification or legal advice.</p>

    <form method="get" action="{{ route('assessments.index') }}" role="search" class="mt-6 flex flex-wrap items-end gap-3 rounded-sm border border-brand-line bg-brand-paper p-3 text-sm">
        <div class="min-w-[12rem] flex-1"><label for="a-q" class="label">Search</label><input id="a-q" type="search" name="q" value="{{ $q }}" class="input !min-h-[40px]" placeholder="e.g. FRIA, LLM, hiring"></div>
        <div><label for="a-type" class="label">Type</label><select id="a-type" name="type" class="input !min-h-[40px] !w-auto"><option value="">All types</option>@foreach(config('assessments.types') as $k => $label)<option value="{{ $k }}" @selected($type === $k)>{{ $label }} ({{ $all->where('type', $k)->count() }})</option>@endforeach</select></div>
        <div><label for="a-region" class="label">Region</label><select id="a-region" name="region" class="input !min-h-[40px] !w-auto"><option value="">All regions</option>@foreach($regions as $r => $n)<option value="{{ $r }}" @selected($region === $r)>{{ $r }} ({{ $n }})</option>@endforeach</select></div>
        <button type="submit" class="btn-primary !min-h-[40px]">Show</button>
        @if($filtered)<a href="{{ route('assessments.index') }}" class="btn-secondary !min-h-[40px]">Clear</a>@endif
        <p class="ml-auto pb-2 text-brand-muted" role="status"><span class="font-semibold text-brand-navy">{{ $items->count() }}</span> {{ \Illuminate\Support\Str::plural('assessment', $items->count()) }}</p>
    </form>

    @if($items->isEmpty())
    <div class="mt-6"><x-site.empty title="No assessment matches" :reset="route('assessments.index')">Try another type or region, or clear the search.</x-site.empty></div>
    @else
    <ul class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach($items as $a)
        <li class="card-flat flex flex-col p-4">
            <p class="flex flex-wrap items-center gap-1.5 text-xs"><span class="badge bg-brand-paper text-brand-body ring-brand-line">{{ \App\Services\Assessments\AssessmentCatalog::typeLabel($a['type']) }}</span><span class="text-brand-muted">{{ $a['region'] }}</span></p>
            <h2 class="mt-2 text-base font-semibold leading-snug"><a href="{{ $a['url'] }}" rel="noopener" class="text-brand-navy no-underline hover:underline" data-track="assessment_click" data-track-label="{{ $a['slug'] }}">{{ $a['title'] }}</a></h2>
            <p class="mt-1 flex-1 text-sm text-brand-body">{{ $a['summary'] }}</p>
            <p class="mt-3 flex items-center justify-between gap-2 text-xs text-brand-muted"><span>{{ $a['questions'] }} questions · about {{ $a['minutes'] }} min · {{ $a['domains'] }} domains</span><a href="{{ $a['url'] }}" rel="noopener" class="btn-secondary !min-h-0 !py-1 !px-2.5 text-xs" data-track="assessment_click" data-track-label="{{ $a['slug'] }}" aria-label="Start the {{ $a['title'] }}">Start</a></p>
        </li>
        @endforeach
    </ul>
    @endif

    <section class="mt-10 max-w-3xl" aria-labelledby="next-heading">
        <h2 id="next-heading" class="section-title">After the score</h2>
        <p class="prose-policy mt-2">A low domain score points at work to do. The <a href="{{ route('templates.index') }}">free templates</a> turn the recorded duties into the registers and documents an auditor asks for, the <a href="{{ route('obligations.index') }}">obligations</a> say what each law requires with its source, and the <a href="{{ route('tools.applicability') }}">applicability check</a> narrows which duties may reach you.</p>
    </section>
</div>
@endsection
