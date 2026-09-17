@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-4xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">Open queue</p>
    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">Records missing something</h1>
    <p class="mt-3 max-w-[70ch] text-brand-body leading-7">Every gap the <a href="{{ route('coverage') }}">coverage check</a> found, required first. Each row opens the record and the form that fixes it, with the record and field already selected. You do not need an account, and a submission is checked against the official source before anything changes.</p>

    <form method="get" action="{{ route('gaps') }}" class="mt-6 flex flex-wrap items-end gap-2">
        <div>
            <label for="kind" class="label">Record type</label>
            <select id="kind" name="kind" class="input !min-h-0">
                <option value="">All</option>
                @foreach($kinds as $id => $meta)<option value="{{ $id }}" @selected($activeKind === $id && ! $activeCheck)>{{ $meta['label'] }}</option>@endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[16rem]">
            <label for="check" class="label">What is missing</label>
            <select id="check" name="check" class="input !min-h-0">
                <option value="">Anything</option>
                @foreach($checks as $c)<option value="{{ $c['id'] }}" @selected(($activeCheck['id'] ?? null) === $c['id'])>{{ $c['label'] }} ({{ $c['kind'] }})</option>@endforeach
            </select>
        </div>
        <button type="submit" class="btn-secondary !min-h-0">Filter</button>
        @if($activeKind || $activeCheck)<a href="{{ route('gaps') }}" class="btn-secondary !min-h-0">Clear</a>@endif
    </form>

    @if($activeCheck)
    <p class="mt-4 card-flat p-4 text-sm text-brand-body">{{ $activeCheck['why'] }}</p>
    @endif

    <p class="mt-6 text-sm text-brand-muted">{{ number_format($total) }} {{ Str::plural('gap', $total) }}@if($total > $limit), showing the first {{ number_format($limit) }}@endif.</p>

    @if($queue->isEmpty())
    <x-site.empty class="mt-4" title="Nothing in this queue" :reset="$activeKind || $activeCheck ? route('gaps') : null">Every record matching this filter carries what it should. The <a href="{{ route('coverage') }}">coverage report</a> shows how the rest of the corpus stands.</x-site.empty>
    @else
    <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">
        @foreach($queue as $gap)
        <li class="flex flex-wrap items-start justify-between gap-3 py-3">
            <span class="min-w-0">
                <a href="{{ $gap['record']->url() }}" class="text-brand-navy hover:underline">{{ $gap['record']->short_title ?? $gap['record']->title ?? $gap['record']->name }}</a>
                <span class="meta block">Missing: {{ $gap['check']['label'] }} · {{ $gap['check']['severity'] === 'required' ? 'required' : 'expected' }}</span>
            </span>
            <a class="btn-secondary !min-h-0 whitespace-nowrap" href="{{ route('contribute', ['type' => 'correction', 'subject_type' => $gap['kind'], 'subject_slug' => $gap['record']->slug, 'field' => $gap['check']['field']]) }}">Fill this in</a>
        </li>
        @endforeach
    </ul>
    @endif

    <section class="mt-10" aria-labelledby="how-h">
        <h2 id="how-h" class="section-title">What happens to what you send</h2>
        <p class="mt-2 text-sm text-brand-body leading-7">A reviewer opens the official source and checks the proposal against it before the record changes. The decision, including a refusal, is published in the <a href="{{ route('corrections') }}">corrections log</a> with the date it was received and the date it was decided. Your name and address are never published.</p>
    </section>
    <x-site.disclaimer class="mt-10" />
</div>
@endsection
