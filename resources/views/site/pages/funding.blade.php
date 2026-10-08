@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-4xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">Funding and independence</p>
    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">Who pays for this, and what money cannot buy</h1>
    <p class="mt-3 max-w-[70ch] text-brand-body leading-7">A reference is only as useful as it is independent. This page says how AIPolicyTracker is paid for today, lists every funder above {{ '$'.number_format($threshold) }} a year, and sets out the rules that keep funders, subscribers and related products away from what the records say.</p>

    <section class="mt-10" aria-labelledby="now-h">
        <h2 id="now-h" class="section-title">How it is funded today</h2>
        <ul class="mt-3 space-y-2 text-sm text-brand-body leading-7 list-disc pl-5">
            <li>The founder and maintainer, <a href="{{ route('team') }}">Bhaskar Bhatt</a>, pays for hosting and does most of the work unpaid.</li>
            @if($selling)<li>Individual <a href="{{ route('pricing') }}">Pro subscriptions</a> pay for more watches and more alert channels. The records are the same for everyone.</li>@endif
            <li>No advertising, no affiliate links and no sponsored content, on the site or in the digest.</li>
            @if(collect($funders)->where('kind', 'grant')->isEmpty())<li>No grants have been received yet. Applications are made in the project's name and are listed here once agreed.</li>@else<li>Grants are made to the project and listed below from the day they are agreed.</li>@endif
        </ul>
    </section>

    <section class="mt-10" aria-labelledby="funders-h">
        <h2 id="funders-h" class="section-title">Funders above {{ '$'.number_format($threshold) }} a year</h2>
        @if($funders === [])
        <x-site.empty class="mt-3" title="None yet">No grant, sponsor or other funder has given more than {{ '$'.number_format($threshold) }} in a year. When one does, its name, the amount and what it pays for appear here from the day it is agreed.</x-site.empty>
        @else
        <div class="mt-3 overflow-x-auto"><table class="w-full text-sm">
            <caption class="sr-only">Funders, amounts and purposes</caption>
            <thead><tr class="text-left text-brand-muted"><th scope="col" class="py-2 pr-3">Funder</th><th scope="col" class="py-2 pr-3">Kind</th><th scope="col" class="py-2 pr-3">Amount</th><th scope="col" class="py-2">Pays for</th></tr></thead>
            <tbody class="divide-y divide-brand-line">@foreach($funders as $f)<tr><td class="py-2 pr-3">@if(!empty($f['url']))<a href="{{ $f['url'] }}" rel="noopener">{{ $f['name'] }}</a>@else{{ $f['name'] }}@endif</td><td class="py-2 pr-3">{{ \App\Models\Funder::KINDS[$f['kind']] ?? $f['kind'] }}</td><td class="py-2 pr-3 whitespace-nowrap">{{ $f['amount'] }} {{ $f['period'] ?? '' }}@if($f['ended'] ?? null)<span class="block meta">ended {{ $f['ended'] }}</span>@endif</td><td class="py-2">{{ $f['purpose'] }}</td></tr>@endforeach</tbody>
        </table></div>
        @endif
    </section>

    <section class="mt-10" aria-labelledby="rules-h">
        <h2 id="rules-h" class="section-title">The rules</h2>
        <ol class="mt-3 space-y-3 text-sm text-brand-body leading-7 list-decimal pl-5">
            <li><strong class="text-brand-navy">The data is free for everyone.</strong> Every record, source and deadline is published under CC BY 4.0, on every plan, and will not be moved behind a paywall or a stricter licence. Money pays for the service around the data, never for the data.</li>
            <li><strong class="text-brand-navy">No one buys coverage or wording.</strong> No funder, sponsor or subscriber sees a record before it is published, chooses what is covered, or decides how a record is described or whether it is verified.</li>
            <li><strong class="text-brand-navy">No advertising and no affiliate links.</strong> The site and the digest name no vendor or product as a recommendation, and earn nothing from any link.</li>
            <li><strong class="text-brand-navy">The related product gets nothing special.</strong> The maintainer is associated with <a href="{{ $certifyiUrl }}" rel="noopener">Certifyi</a>, a commercial AI-governance platform. It has no editorial say, no early access to records and no data that is not published here for everyone. The free self-assessments run on it and say so on every page that offers one. The association is also declared on the <a href="{{ route('reviewers') }}">reviewer roster</a>.</li>
            <li><strong class="text-brand-navy">Mistakes are public.</strong> Every decided correction, including the ones turned down, is on the <a href="{{ route('corrections') }}">corrections log</a>, and every verification names who did it and when.</li>
            <li><strong class="text-brand-navy">Funders are named.</strong> Anyone giving more than {{ '$'.number_format($threshold) }} in a year is listed above with the amount and purpose.</li>
        </ol>
    </section>

    <section class="mt-10" aria-labelledby="help-h">
        <h2 id="help-h" class="section-title">How to support the work</h2>
        <ul class="mt-3 space-y-2 text-sm text-brand-body leading-7 list-disc pl-5">
            <li><a href="{{ route('contribute', ['type' => 'correction']) }}">Report an error</a> or work the <a href="{{ route('gaps') }}">open queue</a>. Corrections help more than money.</li>
            @if($sponsorUrl)<li><a href="{{ $sponsorUrl }}" rel="noopener" data-track="sponsor_click">Sponsor the project</a>. Sponsorship buys no say over the records.</li>@endif
            <li>Foundations and public funders: write to <a href="mailto:bhaskar@aipolicytracker.org">bhaskar@aipolicytracker.org</a>. Unrestricted funding is preferred, and no funder reviews output before publication.</li>
        </ul>
    </section>
    <x-site.disclaimer class="mt-10" />
</div>
@endsection
