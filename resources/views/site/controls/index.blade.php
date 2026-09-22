@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3 max-w-3xl">
        <p class="eyebrow">Controls</p>
        <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">One control, many legal duties</h1>
        <p class="mt-3 text-lg leading-8 text-brand-body">A law says what must be true. A control is what an organisation operates to make it true, and the evidence is how it proves it. Each control below is linked to every recorded duty it satisfies or supports, across every jurisdiction, so the work done once can be counted once.</p>
        <p class="mt-2 text-sm text-brand-muted">Original catalogue. Standards are cited by clause number only; the official text of a duty decides whether a control is enough.</p>
    </header>

    <dl class="mt-8 grid grid-cols-2 sm:grid-cols-4 gap-3" id="evidence">
        <x-site.stat label="Controls" :value="$totals['controls']" note="original catalogue" />
        <x-site.stat label="Duties served" :value="$totals['obligations']" :href="route('obligations.index')" note="recorded obligations" />
        <x-site.stat label="Jurisdictions" :value="$totals['jurisdictions']" :href="route('jurisdictions.index')" note="with a duty a control meets" />
        <x-site.stat label="Evidence types" :value="$totals['evidence_types']" note="from the evidence taxonomy" />
    </dl>

    <form method="get" action="{{ route('controls.index') }}" role="search" class="mt-8 flex flex-col sm:flex-row gap-2 sm:items-end">
        <div class="flex-1"><label for="c-q" class="label">Search controls</label><input id="c-q" name="q" type="search" value="{{ $q }}" class="input" placeholder="risk assessment, logging, vendor…"></div>
        <button type="submit" class="btn-primary">Search</button>
    </form>
    <div class="mt-3 flex flex-wrap gap-2" aria-label="Filter by kind">
        <a class="chip {{ $kind ? '' : 'chip-active' }}" href="{{ route('controls.index') }}">All kinds</a>
        @foreach(\App\Models\Control::KINDS as $k => $label)
        <a class="chip {{ $kind === $k ? 'chip-active' : '' }}" href="{{ route('controls.index', ['kind' => $k]) }}">{{ $label }} <span class="{{ $kind === $k ? 'text-white/70' : 'text-brand-muted' }}">{{ $byKind[$k] ?? 0 }}</span></a>
        @endforeach
    </div>

    <ul class="mt-6 grid gap-4 md:grid-cols-2">
        @forelse($controls as $c)
        @php($binding = $c->obligations->where('is_binding', true)->count())
        @php($jurisdictions = $c->obligations->pluck('policyInstrument.jurisdiction.name')->filter()->unique())
        <li class="card-flat p-5 flex flex-col hover:border-brand-navy transition-colors">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="badge bg-brand-paper text-brand-body ring-brand-line">{{ $c->kindLabel() }}</span>
                <span class="text-brand-muted">{{ $c->owner_role }} · {{ strtolower($c->frequencyLabel()) }}</span>
            </div>
            <h2 class="mt-2 font-display text-lg font-semibold text-brand-navy leading-snug"><a href="{{ $c->url() }}" class="no-underline hover:underline">{{ $c->title }}</a></h2>
            <p class="mt-1.5 text-sm text-brand-body flex-1">{{ $c->purpose }}</p>
            <dl class="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
                <div class="rounded-sm bg-brand-paper py-2"><dt class="text-[11px] uppercase tracking-wide text-brand-muted">Duties</dt><dd class="font-mono text-lg tabular-nums text-brand-navy">{{ $c->obligations->count() }}</dd></div>
                <div class="rounded-sm bg-brand-paper py-2"><dt class="text-[11px] uppercase tracking-wide text-brand-muted">Binding</dt><dd class="font-mono text-lg tabular-nums text-brand-navy">{{ $binding }}</dd></div>
                <div class="rounded-sm bg-brand-paper py-2"><dt class="text-[11px] uppercase tracking-wide text-brand-muted">Jurisdictions</dt><dd class="font-mono text-lg tabular-nums text-brand-navy">{{ $jurisdictions->count() }}</dd></div>
            </dl>
            <p class="mt-3 text-xs text-brand-muted line-clamp-1">{{ $jurisdictions->take(6)->join(', ') }}{{ $jurisdictions->count() > 6 ? ' and '.($jurisdictions->count() - 6).' more' : '' }}</p>
            <p class="mt-2 text-xs text-brand-muted">Evidence: {{ $c->evidence->pluck('title')->take(3)->join(', ') }}{{ $c->evidence->count() > 3 ? '…' : '' }}</p>
        </li>
        @empty
        <li class="md:col-span-2 py-6"><x-site.empty :reset="route('controls.index')" /></li>
        @endforelse
    </ul>
    <x-site.disclaimer class="mt-10" />
</div>
@endsection
