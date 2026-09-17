@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-4xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">Reviewers</p>
    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">Who checks the records, and what they have declared</h1>
    <p class="mt-3 max-w-[70ch] text-brand-body leading-7">A record is marked verified only when a named person has opened the official source and confirmed each claim against it. This page names those people, publishes the interests each of them has declared, and shows how many records each has actually verified.</p>
    <p class="mt-3 max-w-[70ch] text-sm text-brand-body leading-7">Nobody appears here without a declaration of interest. The roster lives in the public repository, so every declaration and every later change to one arrives through a pull request with a date and a diff, and a record cannot claim to be verified by a name that is not on this page: the data check rejects it.</p>

    <div class="mt-8 grid gap-3 grid-cols-2 lg:grid-cols-4">
        <div class="card-flat p-4"><p class="text-2xl font-semibold text-brand-navy">{{ number_format($standing['published']) }}</p><p class="meta mt-1">Published records</p></div>
        <div class="card-flat p-4"><p class="text-2xl font-semibold {{ $standing['verified'] ? 'text-state-good' : 'text-state-bad' }}">{{ $standing['verified'] ? number_format($standing['verified']) : '—' }}</p><p class="meta mt-1">Verified by a named reviewer</p></div>
        <div class="card-flat p-4"><p class="text-2xl font-semibold text-brand-navy">{{ $standing['reviewers'] ?: '—' }}</p><p class="meta mt-1">Reviewers on the roster</p></div>
        <div class="card-flat p-4"><p class="text-2xl font-semibold {{ $standing['unattributed'] ? 'text-state-warn' : 'text-brand-navy' }}">{{ $standing['unattributed'] ?: '—' }}</p><p class="meta mt-1">Verified by an unlisted name</p></div>
    </div>

    @if($standing['verified'] === 0)
    <p class="mt-4 card-flat p-4 text-sm text-brand-body leading-7"><strong class="text-brand-navy">No record has yet been verified by a named reviewer.</strong> Every published record therefore shows its review status as pending, with its confidence level beside it, and the <a href="{{ route('verification') }}">verification policy</a> counts all of them as overdue. That is the honest state of the corpus and it is published rather than hidden. If you are qualified to check AI policy records against official sources, <a href="{{ route('contribute', ['type' => 'reviewer_application']) }}">volunteer as a reviewer</a>.</p>
    @endif

    <section class="mt-10" aria-labelledby="roster-h">
        <h2 id="roster-h" class="section-title">The roster</h2>
        @if($reviewers->isEmpty())
        <x-site.empty class="mt-3" title="No reviewer has published a declaration yet">The roster is empty. When someone joins, their name, the jurisdictions they cover and the interests they have declared appear here before they verify anything. You can <a href="{{ route('contribute', ['type' => 'reviewer_application']) }}">volunteer as a reviewer</a>.</x-site.empty>
        @else
        <div class="mt-3 space-y-4">
            @foreach($reviewers as $r)
            <article class="card-flat p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-brand-navy">{{ $r['name'] }}</h3>
                        <p class="meta mt-1">{{ ucfirst($r['role'] ?? 'reviewer') }}@if(! empty($r['joined_on'])) · since {{ \Illuminate\Support\Carbon::parse($r['joined_on'])->format('M Y') }}@endif</p>
                    </div>
                    <p class="text-sm whitespace-nowrap {{ ($r['verified'] ?? 0) ? 'text-state-good' : 'text-brand-muted' }}">{{ ($r['verified'] ?? 0) ? number_format($r['verified']).' '.Str::plural('record', $r['verified']).' verified' : 'nothing verified yet' }}</p>
                </div>

                @if(! empty($r['bio']))<p class="mt-3 text-sm text-brand-body leading-7">{{ $r['bio'] }}</p>@endif

                @if(! empty($r['expertise']) || ! empty($r['jurisdiction_names']))
                <p class="mt-3 text-sm text-brand-body">
                    @if(! empty($r['expertise']))<span class="text-brand-muted">Expertise:</span> {{ implode(', ', $r['expertise']) }}@endif
                    @if(! empty($r['expertise']) && ! empty($r['jurisdiction_names']))<br>@endif
                    @if(! empty($r['jurisdiction_names']))<span class="text-brand-muted">Covers:</span> {{ implode(', ', $r['jurisdiction_names']) }}@endif
                </p>
                @endif

                @if(! empty($r['affiliations']))
                <p class="mt-3 text-sm text-brand-body"><span class="text-brand-muted">Affiliations:</span>
                    @foreach($r['affiliations'] as $a){{ $a['organisation'] }}@if(! empty($a['role'])) ({{ $a['role'] }})@endif@if(isset($a['current']) && ! $a['current']), former@endif{{ ! $loop->last ? '; ' : '' }}@endforeach
                </p>
                @endif

                <div class="mt-4 border-t border-brand-line pt-3">
                    <p class="eyebrow">Declared interests</p>
                    <ul class="mt-2 space-y-2 text-sm text-brand-body">
                        @foreach($r['interests'] as $interest)
                        <li>
                            {{ $interest['declaration'] }}
                            @if(! empty($interest['affects']))<span class="meta block">Bears on: {{ implode(', ', $interest['affects']) }}</span>@endif
                            @if(! empty($interest['mitigation']))<span class="meta block">Mitigation: {{ $interest['mitigation'] }}</span>@endif
                            @if(! empty($interest['declared_on']))<span class="meta block">Declared {{ \Illuminate\Support\Carbon::parse($interest['declared_on'])->format('j M Y') }}</span>@endif
                        </li>
                        @endforeach
                    </ul>
                </div>

                @if(! empty($r['links']))
                <p class="mt-3 text-sm">@foreach($r['links'] as $link)<a href="{{ $link['url'] }}" rel="noopener nofollow">{{ $link['label'] }}</a>{{ ! $loop->last ? ' · ' : '' }}@endforeach</p>
                @endif
            </article>
            @endforeach
        </div>
        @endif
    </section>

    <section class="mt-10" aria-labelledby="how-h">
        <h2 id="how-h" class="section-title">What a reviewer does, and what constrains them</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1.5 text-sm text-brand-body leading-7">
            <li>Opens the record's official source and confirms each dated claim against it, then records the check with their name and the date. Re-importing the underlying data never clears that decision.</li>
            <li>Declares anything that could bear on their judgement before verifying anything. "None declared." is a declaration; saying nothing is not, and fails the build.</li>
            <li>Cannot sign a verification anonymously: the data check rejects a record whose <code>reviewed_by</code> is not a published reviewer.</li>
            <li>Is measured by what they verified, not by what they are listed as covering. The counts above come from the records, not from the roster file.</li>
        </ul>
        <p class="mt-3 text-sm text-brand-body leading-7">How often each record type must be re-checked is set by the <a href="{{ route('verification') }}">verification policy</a>; what a record must carry at all is set by the <a href="{{ route('coverage') }}">completeness policy</a>; what readers reported and what was decided is in the <a href="{{ route('corrections') }}">corrections log</a>.</p>
        <p class="mt-3"><a class="btn-primary !min-h-0" href="{{ route('contribute', ['type' => 'reviewer_application']) }}">Volunteer as a reviewer</a></p>
    </section>
    <x-site.disclaimer class="mt-10" />
</div>
@endsection
