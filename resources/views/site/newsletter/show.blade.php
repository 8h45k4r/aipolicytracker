@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="mt-2 eyebrow">Weekly digest · <time datetime="{{ $issue->sent_on->toDateString() }}">{{ $issue->sent_on->format('j F Y') }}</time></p>
    <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $changes->count() }} {{ \Illuminate\Support\Str::plural('change', $changes->count()) }} in AI policy, week to {{ $issue->period_end->format('j F Y') }}</h1>
    <p class="mt-2 text-sm text-brand-muted">Covers {{ $issue->period_start->format('j M') }} – {{ $issue->period_end->format('j M Y') }}. Sent to {{ number_format($issue->recipients) }} {{ \Illuminate\Support\Str::plural('subscriber', $issue->recipients) }}. <a href="{{ route('subscribe.show') }}" class="text-brand-blue hover:underline">Subscribe</a></p>

    <section aria-labelledby="changes-heading" class="mt-6">
        <h2 id="changes-heading" class="section-title">What changed</h2>
        <div class="mt-1 divide-y divide-brand-line border-y border-brand-line">
            @forelse($changes as $c)<x-site.change-item :change="$c" />@empty<p class="py-4 text-sm text-brand-muted">No changes were recorded that week.</p>@endforelse
        </div>
    </section>

    @if($deadlines->isNotEmpty())
    <section aria-labelledby="deadlines-heading" class="mt-8">
        <h2 id="deadlines-heading" class="section-title">Application dates that were coming up</h2>
        <ul class="mt-2 space-y-2 text-sm">
            @foreach($deadlines as $d)
            <li><span class="font-mono text-brand-navy">{{ $d->displayDate() }}</span> · <a href="{{ $d->policyInstrument->url() }}" class="text-brand-blue hover:underline">{{ $d->title }}</a> <span class="text-brand-muted">({{ $d->policyInstrument->jurisdiction->name }})</span></li>
            @endforeach
        </ul>
    </section>
    @endif

    @if($issue->incident_count > 0)
    <p class="mt-6 text-sm text-brand-body">{{ number_format($issue->incident_count) }} AI {{ \Illuminate\Support\Str::plural('incident', $issue->incident_count) }} {{ $issue->incident_count === 1 ? 'was' : 'were' }} recorded in the AI Incident Database that week. <a href="{{ route('risk.incidents') }}" class="text-brand-blue hover:underline">Incident summary</a>.</p>
    @endif

    <p class="mt-6 text-sm"><a href="{{ route('newsletter.index') }}" class="text-brand-blue hover:underline">All issues</a> · <a href="{{ route('updates.index') }}" class="text-brand-blue hover:underline">Updates hub</a> · <a href="{{ route('changes.feed') }}" class="text-brand-blue hover:underline">RSS</a></p>
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
