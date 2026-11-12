@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">AI Voices</p>
    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">Who shapes AI policy, safety and governance</h1>
    <p class="mt-3 max-w-[68ch] text-brand-body leading-7">{{ $all->count() }} people who hold the offices that make, enforce or coordinate AI rules: regulators, ministers, legislators, AI safety institutes and international bodies. They are chosen by the role they hold, not ranked, and each profile says only what an official source says about that role, with the source.</p>

    @php($colours = ['regulator' => 'fill-brand-navy', 'government' => 'fill-brand-blue', 'legislator' => 'fill-state-warn', 'safety_institute' => 'fill-state-good', 'international' => 'fill-brand-muted', 'standards' => 'fill-brand-line', 'judiciary' => 'fill-brand-ink'])
    @php($total = max(1, $all->count()))
    <figure class="mt-6" aria-labelledby="mix-title">
        <svg viewBox="0 0 720 28" class="w-full h-auto" role="img" aria-labelledby="mix-title mix-desc" preserveAspectRatio="none">
            <title id="mix-title">The mix of roles</title>
            <desc id="mix-desc">{{ collect(\App\Services\Voices\VoiceCatalog::CATEGORIES)->filter(fn ($l, $k) => ($byCategory[$k] ?? 0) > 0)->map(fn ($l, $k) => $l.': '.$byCategory[$k])->implode('; ') }}.</desc>
            @php($x = 0)
            @foreach(\App\Services\Voices\VoiceCatalog::CATEGORIES as $k => $label)
            @if(($byCategory[$k] ?? 0) > 0)
            @php($w = 720 * $byCategory[$k] / $total)
            <rect x="{{ round($x, 2) }}" y="0" width="{{ round($w, 2) }}" height="28" class="{{ $colours[$k] }}" aria-hidden="true"/>
            @php($x += $w)
            @endif
            @endforeach
        </svg>
        <figcaption class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-brand-body">
            @foreach(\App\Services\Voices\VoiceCatalog::CATEGORIES as $k => $label)
            @if(($byCategory[$k] ?? 0) > 0)<span class="inline-flex items-center gap-1"><svg viewBox="0 0 10 10" class="h-2.5 w-2.5" aria-hidden="true"><rect width="10" height="10" rx="2" class="{{ $colours[$k] }}"/></svg>{{ $label }} {{ $byCategory[$k] }}</span>@endif
            @endforeach
        </figcaption>
    </figure>

    <nav class="mt-6" aria-label="Filter AI Voices">
        <p class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('voices.index', array_filter(['topic' => $topic])) }}" class="chip {{ $category ? '' : 'chip-active' }}">All roles</a>
            @foreach(\App\Services\Voices\VoiceCatalog::CATEGORIES as $k => $label)
            @if(($byCategory[$k] ?? 0) > 0)<a href="{{ route('voices.index', array_filter(['category' => $k, 'topic' => $topic])) }}" rel="nofollow" class="chip {{ $category === $k ? 'chip-active' : '' }}" @if($category === $k) aria-current="page" @endif>{{ $label }}</a>@endif
            @endforeach
        </p>
        <p class="mt-2 flex flex-wrap gap-2 text-sm">
            <a href="{{ route('voices.index', array_filter(['category' => $category])) }}" class="chip {{ $topic ? '' : 'chip-active' }}">All topics</a>
            @foreach(\App\Services\Voices\VoiceCatalog::TOPICS as $k => $label)
            <a href="{{ route('voices.index', array_filter(['category' => $category, 'topic' => $k])) }}" rel="nofollow" class="chip {{ $topic === $k ? 'chip-active' : '' }}" @if($topic === $k) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </p>
    </nav>

    @if($voices->isEmpty())
    <p class="mt-8 text-brand-muted">No profile matches these filters.</p>
    @else
    <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-voices-list>
        @foreach($voices as $v)
        <li>
            <a href="{{ route('voices.show', $v['slug']) }}" class="group flex h-full gap-3 rounded-xl border border-brand-line bg-white p-4 no-underline hover:border-brand-navy" data-track="voice_card">
                <x-site.initials :name="$v['name']" />
                <span class="min-w-0">
                    <span class="block font-semibold text-brand-navy group-hover:underline">{{ $v['name'] }}</span>
                    <span class="block text-sm text-brand-body">{{ $v['role'] }}</span>
                    <span class="mt-1 block text-xs text-brand-muted">{{ $v['organisation'] }} · {{ $jurisdictions[$v['jurisdiction']] ?? ($v['jurisdiction'] === 'international' ? 'International' : $v['jurisdiction']) }}</span>
                </span>
            </a>
        </li>
        @endforeach
    </ul>
    @endif

    <section class="mt-10 max-w-[68ch] text-sm text-brand-body" aria-labelledby="voices-how">
        <h2 id="voices-how" class="section-title">How people are chosen</h2>
        <p class="mt-2">By the office they hold, not by fame or influence: heads of AI regulators and authorities, ministers responsible for AI, sponsors and rapporteurs of AI laws on record, heads of public AI safety institutes, and the people leading AI work at international and standards bodies. A profile gives the role, what official sources say the person did in it, related laws on this site, public statements on official pages, and the sources. It carries no photograph and nothing private. Every profile is pending review until a <a href="{{ route('reviewers') }}">reviewer</a> confirms it. To correct a profile or ask for one to be removed, use the <a href="{{ route('contribute') }}">correction form</a>.</p>
    </section>
</div>
@endsection
