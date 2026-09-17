@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-4xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">Coverage</p>
    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">What every published record carries, and what it is missing</h1>
    <p class="mt-3 max-w-[70ch] text-brand-body leading-7">A record is only checkable if it says where its facts came from and what they mean. This page lists what a published record is expected to carry, counts how many carry it today, and links straight to the ones that do not. The same check runs on every change to the data.</p>
    <p class="mt-3 max-w-[70ch] text-sm text-brand-body leading-7"><strong class="text-brand-navy">Read this as completeness, not coverage of the world.</strong> It measures the records that exist here. It cannot tell you how many instruments exist somewhere that have never been recorded, and no number on this page should be read as if it could.</p>

    <div class="mt-8 grid gap-3 grid-cols-2 lg:grid-cols-4">
        <div class="card-flat p-4"><p class="text-2xl font-semibold text-brand-navy">{{ number_format($report['records']) }}</p><p class="meta mt-1">Published records</p></div>
        <div class="card-flat p-4"><p class="text-2xl font-semibold text-brand-navy">{{ number_format($report['complete']) }}</p><p class="meta mt-1">Carrying everything on this page</p></div>
        <div class="card-flat p-4"><p class="text-2xl font-semibold {{ $report['required_gaps'] ? 'text-state-bad' : 'text-brand-navy' }}">{{ number_format($report['required_gaps']) }}</p><p class="meta mt-1">Required fields missing</p></div>
        <div class="card-flat p-4 {{ $report['pass'] ? 'border-state-good/40' : 'border-state-bad/40' }}"><p class="text-2xl font-semibold {{ $report['pass'] ? 'text-state-good' : 'text-state-bad' }}">{{ $report['pass'] ? 'Pass' : 'Fail' }}</p><p class="meta mt-1">Data check, budget {{ $report['budget'] }}</p></div>
    </div>

    <section class="mt-10" aria-labelledby="kinds-h">
        <h2 id="kinds-h" class="section-title">By record type</h2>
        <p class="mt-1 text-sm text-brand-body">A record counts as complete only when it has no gap at all, required or expected, so this is the strict reading.</p>
        <div class="mt-3 overflow-x-auto"><table class="w-full text-sm">
            <thead><tr class="text-left text-brand-muted border-b border-brand-line"><th class="py-2 pr-3">Record type</th><th class="py-2 pr-3 text-right">Published</th><th class="py-2 pr-3 text-right">Complete</th><th class="py-2 pr-3 text-right">Required missing</th><th class="py-2 text-right">Expected missing</th></tr></thead>
            <tbody class="divide-y divide-brand-line">
            @foreach($report['kinds'] as $kind)
                <tr>
                    <td class="py-2 pr-3 text-brand-navy"><a href="{{ route('gaps', ['kind' => $kind['id']]) }}" class="hover:underline">{{ $kind['label'] }}</a></td>
                    <td class="py-2 pr-3 text-right">{{ $kind['records'] ?: '—' }}</td>
                    <td class="py-2 pr-3 text-right">{{ $kind['complete'] ?: '—' }}</td>
                    <td class="py-2 pr-3 text-right {{ $kind['required_gaps'] ? 'text-state-bad' : '' }}">{{ $kind['required_gaps'] ?: '—' }}</td>
                    <td class="py-2 text-right">{{ $kind['expected_gaps'] ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="mt-10" aria-labelledby="checks-h">
        <h2 id="checks-h" class="section-title">Every check, and how the corpus stands against it</h2>
        <p class="mt-1 text-sm text-brand-body"><strong class="text-brand-navy">Required</strong> means a record should not be published without it, and the data check fails when required gaps exceed the budget. <strong class="text-brand-navy">Expected</strong> means the record works without it but is less useful; those are counted and published, never gated, because gating them would reward filling boxes over checking facts. "Applies to" excludes records the check was never meant for.</p>
        <div class="mt-3 overflow-x-auto"><table class="w-full text-sm">
            <thead><tr class="text-left text-brand-muted border-b border-brand-line"><th class="py-2 pr-3">What the record should carry</th><th class="py-2 pr-3">Record type</th><th class="py-2 pr-3">Severity</th><th class="py-2 pr-3 text-right">Missing</th><th class="py-2 text-right">Applies to</th></tr></thead>
            <tbody class="divide-y divide-brand-line">
            @foreach($report['checks'] as $check)
                <tr>
                    <td class="py-2 pr-3">
                        @if($check['missing'])<a href="{{ route('gaps', ['check' => $check['id']]) }}" class="text-brand-navy hover:underline">{{ $check['label'] }}</a>@else<span class="text-brand-navy">{{ $check['label'] }}</span>@endif
                        <span class="meta block">{{ $check['why'] }}</span>
                    </td>
                    <td class="py-2 pr-3 text-brand-muted whitespace-nowrap">{{ $check['kind'] }}</td>
                    <td class="py-2 pr-3 whitespace-nowrap">{{ ucfirst($check['severity']) }}</td>
                    <td class="py-2 pr-3 text-right {{ $check['missing'] && $check['severity'] === 'required' ? 'text-state-bad' : '' }}">{{ $check['missing'] ?: '—' }}</td>
                    <td class="py-2 text-right text-brand-muted">{{ $check['applicable'] ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="mt-10" aria-labelledby="next-h">
        <h2 id="next-h" class="section-title">What to do with this</h2>
        <p class="mt-2 text-sm text-brand-body leading-7">The <a href="{{ route('gaps') }}">open queue</a> lists every gap above as a record you can open, with a form that fills one in. How old a record's facts are is a separate question, measured by the <a href="{{ route('verification') }}">verification policy</a>: a record can carry every field on this page and still be years out of date. What readers have reported and what happened to it is in the <a href="{{ route('corrections') }}">corrections log</a>.</p>
        <p class="mt-3 meta">The budget shown above is a ratchet, not a target: it may only be lowered.</p>
    </section>
    <x-site.disclaimer class="mt-10" />
</div>
@endsection
