@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3 max-w-3xl">
        <p class="eyebrow">By role, sector and use case</p>
        <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">Start from who you are</h1>
        <p class="mt-3 text-lg leading-8 text-brand-body">The same corpus, cut by the role you play, the sector you work in or the use case you run. Each page lists every recorded duty that names your situation, the controls that meet those duties and the evidence a reviewer would expect.</p>
    </header>
    @foreach(['actor' => 'By role', 'sector' => 'By sector', 'use_case' => 'By use case'] as $tax => $label)
    @php($group = $pages->where('taxonomy', $tax))
    @if($group->isNotEmpty())
    <section class="mt-8" aria-labelledby="aud-{{ $tax }}">
        <div class="rule-strong pt-3"><h2 id="aud-{{ $tax }}" class="section-title">{{ $label }}</h2></div>
        <ul class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($group as $p)
            <li class="card-flat p-5 flex flex-col hover:border-brand-navy transition-colors">
                <h3 class="font-display text-lg font-semibold text-brand-navy leading-snug"><a href="{{ $p['url'] }}" class="no-underline hover:underline">{{ $p['h1'] }}</a></h3>
                <p class="mt-1.5 text-sm text-brand-body flex-1 line-clamp-3">{{ $p['description'] }}</p>
                <p class="mt-3 font-mono text-xs tabular-nums text-brand-muted">{{ $p['duties'] }} recorded {{ \Illuminate\Support\Str::plural('duty', $p['duties']) }}</p>
            </li>
            @endforeach
        </ul>
    </section>
    @endif
    @endforeach
    <x-site.disclaimer class="mt-10" />
</div>
@endsection
