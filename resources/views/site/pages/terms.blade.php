@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Terms of use</h1>
    <p class="mt-3 prose-policy">These terms govern your use of {{ config('aipolicytracker.site_name') }}, operated by <a href="{{ config('legal.controller.url') }}" rel="noopener">{{ config('legal.controller.name') }}</a>. Using the site means accepting them. They are written to be read, not to be survived.</p>
    @if($effective)<p class="mt-2 text-sm text-brand-muted">Last updated <time datetime="{{ $effective->toDateString() }}">{{ $effective->isoFormat('D MMMM YYYY') }}</time>.</p>@endif

    <section class="mt-8" aria-labelledby="advice-heading"><h2 id="advice-heading" class="section-title">This is information, not legal advice</h2>
        <p class="prose-policy mt-2">{{ config('aipolicytracker.disclaimer') }}</p>
        <p class="prose-policy mt-2">Everything here is a structured summary of a public document, written to help you find and understand that document. It is not advice, it does not create a professional relationship, and it cannot account for your circumstances. Before you act, open the official source linked on the record and consult qualified counsel in the relevant jurisdiction. Where a record has not yet been confirmed by a named reviewer, the page says so; the <a href="{{ route('verification') }}">verification policy</a> explains what that means and the <a href="{{ route('methodology') }}">methodology</a> explains how records are made.</p>
    </section>

    <section class="mt-8" aria-labelledby="accuracy-heading"><h2 id="accuracy-heading" class="section-title">What we do and do not warrant</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1.5 text-sm text-brand-body">
            <li><span class="font-medium text-brand-navy">We do not warrant that a record is current.</span> Law changes, application dates move and instruments are amended or repealed. Each record shows its status and when it was last confirmed. Read those before relying on it.</li>
            <li><span class="font-medium text-brand-navy">We do not warrant completeness.</span> Coverage is deliberately narrow and deep. The <a href="{{ route('coverage') }}">coverage report</a> measures the records that exist here; it cannot tell you what has never been recorded.</li>
            <li><span class="font-medium text-brand-navy">We do publish our mistakes.</span> The <a href="{{ route('corrections') }}">corrections log</a> shows what readers reported and what was decided, refusals included. If you think something is wrong, <a href="{{ route('contribute') }}">tell us</a>.</li>
            <li><span class="font-medium text-brand-navy">The service is provided as it is.</span> We do not guarantee that the site will be available without interruption or free of error.</li>
        </ul>
    </section>

    <section class="mt-8" aria-labelledby="data-heading"><h2 id="data-heading" class="section-title">Using the data</h2>
        <p class="prose-policy mt-2">The policy records are published under <a href="{{ config('aipolicytracker.data_license_url') }}" rel="noopener">{{ config('aipolicytracker.data_license') }}</a>. You may copy, redistribute, adapt and use them commercially, provided you credit {{ config('aipolicytracker.site_name') }} and link back. The <a href="{{ route('open-data') }}">open data page</a> gives the citation format, the schema and the bulk downloads.</p>
        <p class="prose-policy mt-2">The application source is published separately under its own licence in the <a href="{{ config('aipolicytracker.github_url') }}" rel="noopener">repository</a>. Site design, wording and branding are not covered by the data licence.</p>
        <p class="prose-policy mt-2">Some material on this site comes from third parties under their own terms, and those terms travel with it. Official legal texts remain subject to the source jurisdiction's reuse rules. Nothing here grants rights in copyrighted legal commentary, paid database content or standards documents, none of which is reproduced.</p>
    </section>

    <section class="mt-8" aria-labelledby="api-heading"><h2 id="api-heading" class="section-title">Automated access</h2>
        <p class="prose-policy mt-2">The read-only interface, bulk exports and machine-readable record files exist so you do not have to scrape the site. Use them. Requests are rate limited so one caller cannot degrade the site for everybody; if a limit blocks legitimate work, <a href="{{ route('contribute') }}">ask</a> rather than working around it. Do not attempt to bypass authentication or rate limits, and do not use automated access to reconstruct paid or account-gated material for redistribution.</p>
    </section>

    <section class="mt-8" aria-labelledby="account-heading"><h2 id="account-heading" class="section-title">Accounts</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1.5 text-sm text-brand-body">
            <li>A free account is needed to download templates. Give an address you can actually be reached at: temporary and disposable mailboxes are refused, because a deadline alert sent to one never arrives.</li>
            <li>You are responsible for what happens under your account and for keeping your password to yourself.</li>
            <li>Templates are licensed to you and your organisation for your own governance work. Do not resell them or redistribute them as your own product.</li>
            <li>We can suspend an account that is used to attack the site, to scrape around the published interfaces, or to submit deliberately false records. We will say why.</li>
            <li>You can delete your account at any time from the <a href="{{ route('profile.edit') }}">account page</a>.</li>
        </ul>
    </section>

    <section class="mt-8" aria-labelledby="contrib-heading"><h2 id="contrib-heading" class="section-title">What you contribute</h2>
        <p class="prose-policy mt-2">If you submit a correction or a record, you confirm you are free to share it and that it is not copied from a source that forbids it. You allow us to publish the substance of your submission and the decision made on it under the same open licence as the rest of the data. Your name, address and affiliation are not published. Submissions are reviewed before publication and may be declined; refusals are published with a reason.</p>
    </section>

    <section class="mt-8" aria-labelledby="liability-heading"><h2 id="liability-heading" class="section-title">Limits of liability</h2>
        <p class="prose-policy mt-2">To the extent the law allows, {{ config('legal.controller.name') }} is not liable for loss arising from reliance on this site, including regulatory penalties, missed deadlines, lost profit or lost data. This site is a starting point for research, not a compliance decision. Nothing in these terms limits liability that cannot be limited by law, including liability for fraud.</p>
    </section>

    <section class="mt-8" aria-labelledby="changes-heading"><h2 id="changes-heading" class="section-title">Changes, and the law that applies</h2>
        <p class="prose-policy mt-2">These terms may change. Every change is a commit in the public repository, so its history is the change log, and the date at the top is the last substantive update. Continuing to use the site after a change means accepting it.</p>
        @if(config('legal.governing_law'))
        <p class="prose-policy mt-2">These terms are governed by the laws of {{ config('legal.governing_law') }}, and the courts of {{ config('legal.governing_law') }} have jurisdiction over any dispute arising from them.</p>
        @endif
        <p class="prose-policy mt-2">If any part of these terms is unenforceable, the rest continues to apply. Questions about them go to the <a href="{{ route('contribute') }}">contact form</a>@if($contact) or to <a href="mailto:{{ $contact }}">{{ $contact }}</a>@endif. How we handle your personal data is set out in the <a href="{{ route('privacy') }}">privacy policy</a>.</p>
    </section>

    <x-site.disclaimer class="mt-8" />
</div>
@endsection
