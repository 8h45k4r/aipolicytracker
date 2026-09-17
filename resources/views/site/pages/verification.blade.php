@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-4xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">Verification</p>
    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">How current every record is</h1>
    <p class="mt-3 max-w-[70ch] text-brand-body leading-7">Every published record carries the date its facts were last confirmed against the official source. This page sets the maximum age for each kind of record, counts how many are past it, and names the longest overdue. The same check runs on every change to the data, so the corpus cannot go stale without someone deciding to allow it.</p>

    <div class="mt-8 grid gap-3 grid-cols-2 lg:grid-cols-4">
        <div class="card-flat p-4"><p class="text-2xl font-semibold text-brand-navy">{{ number_format($report['covered']) }}</p><p class="meta mt-1">Records under the policy</p></div>
        <div class="card-flat p-4"><p class="text-2xl font-semibold {{ $report['overdue'] ? 'text-state-bad' : 'text-brand-navy' }}">{{ number_format($report['overdue']) }}</p><p class="meta mt-1">Past their re-check date</p></div>
        <div class="card-flat p-4"><p class="text-2xl font-semibold {{ $report['never'] ? 'text-state-bad' : 'text-brand-navy' }}">{{ number_format($report['never']) }}</p><p class="meta mt-1">Never yet confirmed</p></div>
        <div class="card-flat p-4 {{ $report['pass'] ? 'border-state-good/40' : 'border-state-bad/40' }}"><p class="text-2xl font-semibold {{ $report['pass'] ? 'text-state-good' : 'text-state-bad' }}">{{ $report['pass'] ? 'Pass' : 'Fail' }}</p><p class="meta mt-1">Data check, budget {{ $report['budget'] }}</p></div>
    </div>

    <section class="mt-10" aria-labelledby="rules-h">
        <h2 id="rules-h" class="section-title">Maximum age by record type</h2>
        <p class="mt-1 text-sm text-brand-body">A record is governed by the first rule it matches. Rules marked critical fail the data check when breaches exceed the budget.</p>
        <div class="mt-3 overflow-x-auto"><table class="w-full text-sm">
            <thead><tr class="text-left text-brand-muted border-b border-brand-line"><th class="py-2 pr-3">Record type</th><th class="py-2 pr-3">Owner</th><th class="py-2 pr-3">Max age</th><th class="py-2 pr-3">Critical</th><th class="py-2 pr-3 text-right">Records</th><th class="py-2 pr-3 text-right">Overdue</th><th class="py-2 text-right">Never checked</th></tr></thead>
            <tbody class="divide-y divide-brand-line">
            @foreach($report['rules'] as $rule)
                <tr>
                    <td class="py-2 pr-3 text-brand-navy">{{ $rule['label'] }}</td>
                    <td class="py-2 pr-3 text-brand-muted">{{ $report['tracks'][$rule['track']]['owner'] ?? '—' }}</td>
                    <td class="py-2 pr-3 whitespace-nowrap">{{ $rule['days'] }} days</td>
                    <td class="py-2 pr-3">{{ $rule['critical'] ? 'Yes' : 'No' }}</td>
                    <td class="py-2 pr-3 text-right">{{ $rule['covered'] ?: '—' }}</td>
                    <td class="py-2 pr-3 text-right {{ $rule['overdue'] ? 'text-state-bad' : '' }}">{{ $rule['overdue'] ?: '—' }}</td>
                    <td class="py-2 text-right {{ $rule['never'] ? 'text-state-bad' : '' }}">{{ $rule['never'] ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="mt-10" aria-labelledby="stale-h">
        <h2 id="stale-h" class="section-title">Longest overdue</h2>
        @if($report['stale']->isEmpty())
        <p class="mt-2 text-sm text-brand-body">Nothing is past its re-check date.</p>
        @else
        <p class="mt-1 text-sm text-brand-body">The {{ min(40, $report['stale']->count()) }} records most in need of a check. Opening one shows its status and official source; anyone can <a href="{{ route('contribute', ['type' => 'correction']) }}">report a correction</a>.</p>
        <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">
            @foreach($report['stale']->take(40) as $row)
            <li class="flex flex-wrap items-start justify-between gap-3 py-2">
                <span><a href="{{ $row['record']->url() }}" class="text-brand-navy hover:underline">{{ $row['record']->short_title ?? $row['record']->title ?? $row['record']->name }}</a>
                    <span class="meta">· {{ $row['rule']['label'] }}</span></span>
                <span class="meta whitespace-nowrap">{{ $row['never'] ? 'never confirmed' : $row['age'].' days old' }} · limit {{ $row['rule']['days'] }}</span>
            </li>
            @endforeach
        </ul>
        @endif
    </section>

    <section class="mt-10" aria-labelledby="how-h">
        <h2 id="how-h" class="section-title">How a record becomes verified</h2>
        <p class="mt-2 text-sm text-brand-body leading-7">A reviewer opens the official source, confirms each dated claim against it, and records the check with their name and the date. Re-import of the underlying data never clears that decision. Until a record has been through that, it is shown as pending review and its confidence level is published alongside it. Read the <a href="{{ route('methodology') }}">methodology</a> for the full standard.</p>
        <p class="mt-3 meta">The budget shown above is a ratchet, not a target: it records how many critical records were overdue when the policy was introduced, and it may only be lowered.</p>
    </section>
    <x-site.disclaimer class="mt-10" />
</div>
@endsection
