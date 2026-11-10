@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-4xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">Methodology</p>
    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">Who checks the records, and what keeps them independent</h1>
    <p class="mt-3 max-w-[70ch] text-brand-body leading-7">A record is only worth citing if you know who stands behind it and can check it yourself. This page says who verifies the records today, what the commercial relationship is and the rules that keep it away from the data, and what is not yet as independent as it should be.</p>

    <section class="mt-10" aria-labelledby="who-h">
        <h2 id="who-h" class="section-title">Who verifies the records today</h2>
        <p class="mt-3 text-sm text-brand-body leading-7">{{ $verified }} of {{ $total }} published instruments are marked verified. A verification means a named person opened the official source the record links and confirmed the record against it. It is a factual check, not a legal review. Every verified record names the reviewer and the date.</p>
        @if($reviewers->isNotEmpty())
        <ul class="mt-3 divide-y divide-brand-line text-sm">
            @foreach($reviewers as $r)
            <li class="py-2"><a href="{{ route('reviewers.show', $r['slug']) }}" class="font-medium">{{ $r['name'] }}</a> <span class="meta">· {{ $r['role'] }} · {{ $r['verified'] ?? 0 }} records verified</span></li>
            @endforeach
        </ul>
        @endif
        <p class="mt-3 text-sm"><a href="{{ route('reviewers') }}">The reviewer roster</a> lists every reviewer with their declared interests.</p>
    </section>

    <section class="mt-10" aria-labelledby="rel-h">
        <h2 id="rel-h" class="section-title">The commercial relationship</h2>
        <p class="mt-3 text-sm text-brand-body leading-7">The maintainer is associated with <strong class="text-brand-navy">Certifyi</strong>, a commercial AI-governance compliance platform. That interest is declared on the maintainer's roster entry, on the <a href="{{ route('funding') }}">funding page</a> and in the repository's governance file. The rules that follow from it:</p>
        <ul class="mt-3 space-y-2 text-sm text-brand-body leading-7 list-disc pl-5">
            <li>Certifyi has no editorial say, no early access to records and no data that is not published here for everyone. It uses the same public API and open data as anyone else.</li>
            <li>The records name no vendor or product, and the site carries no advertising and no affiliate links.</li>
            <li>No funder, sponsor or subscriber sees a record before it is published, chooses what is covered, or decides how a record is described or whether it is verified.</li>
            <li>The data is CC BY 4.0 on every plan and will not move behind a paywall. Money pays for the service around the data, never the data.</li>
        </ul>
    </section>

    <section class="mt-10" aria-labelledby="check-h">
        <h2 id="check-h" class="section-title">How you can check a record yourself</h2>
        <ul class="mt-3 space-y-2 text-sm text-brand-body leading-7 list-disc pl-5">
            <li><strong class="text-brand-navy">Every record links its official source</strong>, and duties cite the article or section they come from.</li>
            <li><strong class="text-brand-navy">Every record has a permanent id</strong> (such as AIPT-POL-0073) and a citation that names its version, its reviewer and the date it was verified, so a citation says exactly which state of the record was relied on.</li>
            <li><strong class="text-brand-navy">Every record shows its history</strong>: what changed in it, from what, to what and when, and whether the official source has been checked recently.</li>
            <li><strong class="text-brand-navy">Speed is measured in public</strong> on the <a href="{{ route('freshness') }}">freshness page</a>, against a target of {{ config('changedesk.target_days') }} days from an official text to its entry here.</li>
            <li><strong class="text-brand-navy">Mistakes are public.</strong> Every decided correction, including the ones turned down, is on the <a href="{{ route('corrections') }}">corrections log</a>. A random quarterly sample of verified records is re-read blind by a second reviewer (<a href="{{ route('methodology') }}#independent-checks">methodology</a>).</li>
            <li>The full edit history of the data is in the public <a href="{{ config('aipolicytracker.github_url') }}" rel="noopener">repository</a>.</li>
        </ul>
    </section>

    <section class="mt-10" aria-labelledby="not-h">
        <h2 id="not-h" class="section-title">What is not yet independent</h2>
        <p class="mt-3 text-sm text-brand-body leading-7"><strong class="text-brand-navy">Joyce Loksee Ho</strong> has been appointed as the independent data reviewer. Once her declaration of interest is published and branch protection is configured, every data change will need her approval before it merges. Until then, the maintainer can still approve data changes alone. The <a href="{{ config('aipolicytracker.github_url') }}/blob/main/GOVERNANCE.md">governance document</a> tracks the current status.</p>
        <p class="mt-3 text-sm text-brand-body leading-7">Today most verifications are still made by the maintainer, who has the commercial association above. Records signed by the "AI Policy Tracker" desk are the maintainer's work too. That is disclosed on each record and on the roster, but disclosure is not independence. As the independent reviewer role becomes active, new and updated records will carry a co-review. We are also recruiting additional independent reviewers with relevant expertise. If that is you, write to <a href="mailto:{{ config('aipolicytracker.contact_email') }}">{{ config('aipolicytracker.contact_email') }}</a>.</p>
    </section>
    <x-site.disclaimer class="mt-10" />
</div>
@endsection
