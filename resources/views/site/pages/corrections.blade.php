@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-4xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">Corrections</p>
    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">What readers reported, and what was done about it</h1>
    <p class="mt-3 max-w-[70ch] text-brand-body leading-7">Inviting corrections means nothing unless you can see what happens to them. Every report a reviewer has decided appears below, <strong class="text-brand-navy">including the ones that were turned down</strong>, with the record it concerned, the date it arrived and the date it was decided.</p>
    <p class="mt-3 max-w-[70ch] text-sm text-brand-body leading-7">What is not published: who sent it, and the words they used. A report is an unchecked claim about a record until a reviewer has been through it, and this site will not republish text it never moderated. Each entry publishes the facts it can stand behind, plus a note the reviewer wrote for this page if they wrote one.</p>

    <div class="mt-8 grid gap-3 grid-cols-2 lg:grid-cols-4">
        <div class="card-flat p-4"><p class="text-2xl font-semibold text-brand-navy">{{ number_format($received) }}</p><p class="meta mt-1">Reports received</p></div>
        <div class="card-flat p-4"><p class="text-2xl font-semibold text-brand-navy">{{ number_format($accepted) }}</p><p class="meta mt-1">Accepted</p></div>
        <div class="card-flat p-4"><p class="text-2xl font-semibold text-brand-navy">{{ number_format($declined) }}</p><p class="meta mt-1">Declined</p></div>
        <div class="card-flat p-4"><p class="text-2xl font-semibold {{ $open ? 'text-state-warn' : 'text-brand-navy' }}">{{ $open ? number_format($open) : '—' }}</p><p class="meta mt-1">Still open</p></div>
    </div>
    @if($median_days !== null)
    <p class="mt-3 meta">Typical time from report to decision: {{ $median_days }} {{ Str::plural('day', $median_days) }} (median, so one long-running case cannot describe the usual wait).</p>
    @endif

    <section class="mt-10" aria-labelledby="log-h">
        <h2 id="log-h" class="section-title">The log</h2>
        @if($entries->isEmpty())
        <x-site.empty class="mt-3" title="No decided reports yet">Nothing has been reported and decided since this log opened. Reports in the queue are not shown until a reviewer has been through them. You can <a href="{{ route('contribute', ['type' => 'correction']) }}">report an error</a> or work the <a href="{{ route('gaps') }}">open queue</a>.</x-site.empty>
        @else
        <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">
            @foreach($entries as $entry)
            <li class="py-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <span class="min-w-0">
                        @if($entry['record'])
                            <a href="{{ $entry['record']['url'] }}" class="text-brand-navy hover:underline">{{ $entry['record']['title'] }}</a>
                        @else
                            <span class="text-brand-navy">Record no longer published</span>
                        @endif
                        <span class="meta block">{{ $entry['type'] }}@if($entry['field']) · {{ $entry['field'] }}@endif</span>
                    </span>
                    <span class="whitespace-nowrap {{ $entry['status']->value === 'approved' ? 'text-state-good' : ($entry['status']->value === 'rejected' ? 'text-brand-muted' : 'text-state-warn') }}">{{ $entry['status']->label() }}</span>
                </div>
                @if($entry['public_note'])<p class="mt-1 text-brand-body">{{ $entry['public_note'] }}</p>@endif
                <p class="mt-1 meta">Received {{ $entry['received_on']?->format('j M Y') ?? '—' }} · decided {{ $entry['decided_at']?->format('j M Y') ?? '—' }}@if($entry['days'] !== null) · {{ $entry['days'] }} {{ Str::plural('day', $entry['days']) }}@endif</p>
            </li>
            @endforeach
        </ul>
        @endif
    </section>

    <section class="mt-10" aria-labelledby="how-h">
        <h2 id="how-h" class="section-title">How a report is handled</h2>
        <p class="mt-2 text-sm text-brand-body leading-7">A reviewer opens the official source and checks the claim against it. An accepted correction is written into the public data files through a pull request, so the change itself is auditable line by line. A report is declined when the source does not support it, and that is published here too. Read the <a href="{{ route('methodology') }}">methodology</a> for the standard a reviewer applies, the <a href="{{ route('verification') }}">verification policy</a> for how often records are re-checked, and <a href="{{ route('coverage') }}">coverage</a> for what records are missing.</p>
        <p class="mt-3"><a class="btn-primary !min-h-0" href="{{ route('contribute', ['type' => 'correction']) }}">Report an error</a></p>
    </section>
    <x-site.disclaimer class="mt-10" />
</div>
@endsection
