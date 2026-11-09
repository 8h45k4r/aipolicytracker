@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Privacy policy</h1>
    <p class="mt-3 prose-policy">This page describes what {{ config('aipolicytracker.site_name') }} stores about you, why, how long it is kept and how to get it back or removed. It describes the system as it is actually built: the tables named here exist in the published source, and the <a href="{{ config('aipolicytracker.github_url') }}" rel="noopener">repository</a> can be read to check any claim on this page.</p>
    @if($effective)<p class="mt-2 text-sm text-brand-muted">Last updated <time datetime="{{ $effective->toDateString() }}">{{ $effective->isoFormat('D MMMM YYYY') }}</time>.</p>@endif

    <section class="mt-8" aria-labelledby="who-heading"><h2 id="who-heading" class="section-title">Who is responsible</h2>
        <p class="prose-policy mt-2">{{ config('aipolicytracker.site_name') }} is operated by <a href="{{ config('legal.controller.url') }}" rel="noopener">{{ config('legal.controller.name') }}</a>@if(config('legal.controller.address')), {{ config('legal.controller.address') }}@endif. That entity decides what is collected and why.</p>
        @if($contact)
        <p class="prose-policy mt-2">For anything on this page, including a request to see, correct or delete your data, write to <a href="mailto:{{ $contact }}">{{ $contact }}</a>.</p>
        @else
        <p class="prose-policy mt-2">Requests about your data can be sent through the <a href="{{ route('contribute') }}">contact and contribution form</a>.</p>
        @endif
    </section>

    <section class="mt-8" aria-labelledby="browse-heading"><h2 id="browse-heading" class="section-title">Reading the site without an account</h2>
        <p class="prose-policy mt-2">You can read every policy record, obligation, jurisdiction, change entry and guide without an account and without giving us anything.</p>
        <ul class="mt-2 list-disc pl-5 space-y-1.5 text-sm text-brand-body">
            <li><span class="font-medium text-brand-navy">Page counts carry no identifier.</span> The site counts how many times a path was viewed on a given day. That record holds a date, a path and a number, and nothing else. There is no visitor identifier in it, so there is nothing to attribute back to you.</li>
            <li><span class="font-medium text-brand-navy">Analytics are off until you accept them.</span> Where an analytics provider is configured, its script is not loaded until you accept the consent banner. Declining leaves it unloaded.</li>
            <li><span class="font-medium text-brand-navy">No advertising, no profiling, no data sales.</span> We do not sell or rent personal data, we do not run advertising, and we do not build behavioural profiles.</li>
        </ul>
    </section>

    <section class="mt-8" aria-labelledby="account-heading"><h2 id="account-heading" class="section-title">What an account stores</h2>
        <div class="table-wrap mt-3"><table><caption class="sr-only">Data stored for an account</caption>
            <thead><tr><th scope="col">What</th><th scope="col">Why</th></tr></thead>
            <tbody>
                <tr><td>Name and email address</td><td>To identify the account, sign you in and send what you asked for.</td></tr>
                <tr><td>Password</td><td>Stored as a one-way hash. It cannot be read back, by us or by anyone with the database.</td></tr>
                <tr><td>Organisation name</td><td>Required when you download a template, because a template download is how organisations tell us what the material is for.</td></tr>
                <tr><td>Terms acceptance time</td><td>The record that you accepted these terms, and when.</td></tr>
                <tr><td>Marketing consent time</td><td>Separate from terms acceptance and never pre-ticked. Empty means you did not opt in.</td></tr>
                <tr><td>Two-factor secret and recovery codes</td><td>For administrator accounts only. Encrypted at rest and never included when the account is serialised.</td></tr>
                <tr><td>Phone number, organisation email</td><td>Only if you chose to give them. Both are optional.</td></tr>
                <tr><td>Sign-up IP hash and browser user agent</td><td>Recorded once at sign-up to make abuse of the free-account gate traceable. The IP address is stored as a keyed hash, the same as every other address in the system: it links rows from one address without keeping the address itself.</td></tr>
            </tbody>
        </table></div>
    </section>

    <section class="mt-8" aria-labelledby="downloads-heading"><h2 id="downloads-heading" class="section-title">Downloads, alerts and saved work</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1.5 text-sm text-brand-body">
            <li><span class="font-medium text-brand-navy">Template downloads.</span> Each download records which template, which version and file, when, the referring page, and a hash of your IP address rather than the address itself. You accept the terms each time.</li>
            <li><span class="font-medium text-brand-navy">New-version emails.</span> Only if you tick the box on a template's form: when that template gets a new version, we email you once about it, with its changelog and links to related templates. We keep a record of each such email (the version, your address, when it was sent and whether delivery failed) so nobody is emailed twice. Every email has a one-click unsubscribe; we then keep your address on a do-not-email list so it stays stopped. This does not subscribe you to the digest.</li>
            <li><span class="font-medium text-brand-navy">Remembering you for the next template.</span> When you open an emailed download link, your browser keeps an encrypted cookie (<code>aip_template_reader</code>, {{ \App\Models\TemplateDownloadRequest::KNOWN_DAYS }} days) holding only the number of that request, so the next template takes one click instead of the form again. It is strictly necessary for that, holds no name or address, and clearing your cookies removes it; "Not you, or change details" on any template brings the form back.</li>
            <li><span class="font-medium text-brand-navy">Newsletter.</span> The digest stores your address, the jurisdictions you chose (and whether you also want new templates and guides) and a confirmation token. You accept the terms when you subscribe. Subscription is double opt-in: nothing is sent until you confirm from the address itself, and every email carries a one-click unsubscribe.</li>
            <li><span class="font-medium text-brand-navy">Followed records and alerts.</span> Records you follow are stored against your account and are visible only to you.</li>
            <li><span class="font-medium text-brand-navy">Saved screening profiles.</span> The applicability screener can save your answers to its questionnaire. That profile belongs to one account, is never published, and holds questionnaire answers only. It is not a place to describe your systems, your evidence or your conclusions, and nothing you save there is read by anyone else.</li>
        </ul>
    </section>

    <section class="mt-8" aria-labelledby="contrib-heading"><h2 id="contrib-heading" class="section-title">If you report a correction</h2>
        <p class="prose-policy mt-2">A correction you submit becomes part of the public record: the <a href="{{ route('corrections') }}">corrections log</a> publishes what was reported, what was decided and when, including refusals. <span class="font-medium text-brand-navy">Your name, address and affiliation are never published with it.</span> They are kept so a reviewer can come back to you with a question, and giving them is optional.</p>
    </section>

    <section class="mt-8" aria-labelledby="payment-heading"><h2 id="payment-heading" class="section-title">Payments</h2>
        <p class="prose-policy mt-2">No card or bank details are stored here or pass through this application. Where a paid plan exists, checkout and the billing portal are hosted by the payment provider, who is the merchant of record. This site keeps the provider's identifiers, the plan, the status and dates, the amounts of payments and refunds, and the provider's notifications about them as received, which include the customer's name and email address and can include the billing address given at checkout. They are kept as billing records.</p>
    </section>

    <section class="mt-8" aria-labelledby="retention-heading"><h2 id="retention-heading" class="section-title">How long things are kept</h2>
        <div class="table-wrap mt-3"><table><caption class="sr-only">Retention periods</caption>
            <thead><tr><th scope="col">Record</th><th scope="col">Kept</th></tr></thead>
            <tbody>@foreach($retention as $row)<tr><td>{{ $row['what'] }}</td><td>{{ $row['how_long'] }}</td></tr>@endforeach</tbody>
        </table></div>
    </section>

    <section class="mt-8" aria-labelledby="rights-heading"><h2 id="rights-heading" class="section-title">Your control over it</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1.5 text-sm text-brand-body">
            <li><span class="font-medium text-brand-navy">See and change it.</span> Your <a href="{{ route('profile.edit') }}">account page</a> shows your details, your download history and your consent settings, and lets you change them.</li>
            <li><span class="font-medium text-brand-navy">Delete it.</span> The same page deletes the account. This is not a request queue: it removes the account and the records tied to it.</li>
            <li><span class="font-medium text-brand-navy">Stop the email.</span> Unsubscribe from any digest in one click, or clear marketing consent on the account page. Neither affects your ability to read the site.</li>
            <li><span class="font-medium text-brand-navy">Ask a person.</span> If any of the above does not do what you need, @if($contact)write to <a href="mailto:{{ $contact }}">{{ $contact }}</a>@else use the <a href="{{ route('contribute') }}">contact form</a>@endif and a person will answer.</li>
        </ul>
        <p class="prose-policy mt-3">Depending on where you live you may have statutory rights of access, correction, erasure, portability and objection. We apply the list above to everybody rather than asking where you are.</p>
    </section>

    <section class="mt-8" aria-labelledby="security-heading"><h2 id="security-heading" class="section-title">How it is protected</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1.5 text-sm text-brand-body">
            <li>Traffic is served over HTTPS, with a content security policy and without disclosing server versions.</li>
            <li>Passwords are hashed. Administrator two-factor secrets, recovery codes and provider API keys are encrypted at rest.</li>
            <li>Administrator access needs a second factor, and every change an administrator makes is written to an audit log that records who, what and when, and a hash of the address rather than the address.</li>
            <li>Template files are stored outside the public web root and delivered through short-lived signed links bound to the account that requested them.</li>
        </ul>
        <p class="prose-policy mt-3">No system is beyond compromise. If you believe you have found a vulnerability, report it @if($contact)to <a href="mailto:{{ $contact }}">{{ $contact }}</a>@else through the <a href="{{ route('contribute') }}">contact form</a>@endif rather than disclosing it publicly, and we will work with you on it.</p>
    </section>

    <section class="mt-8" aria-labelledby="processors-heading"><h2 id="processors-heading" class="section-title">Who else touches it</h2>
        <p class="prose-policy mt-2">The site runs on third-party hosting and uses these services. They process data on our instructions to run the service, are not permitted to use it for their own purposes, and none of them is given data for advertising.</p>
        <ul class="mt-2 list-disc pl-5 space-y-1.5 text-sm text-brand-body">
            <li><span class="font-medium text-brand-navy">Cloudflare</span> serves and protects all traffic, runs the automated check on forms (Turnstile) and, where configured and only after you allow analytics, counts visits (Web Analytics).</li>
            <li><span class="font-medium text-brand-navy">Resend</span> delivers the site's email (confirmations, downloads, alerts, digests).</li>
            <li><span class="font-medium text-brand-navy">Google Analytics</span>, where configured, measures usage only after you allow analytics. "Analytics choices" in the footer changes your answer at any time.</li>
            <li><span class="font-medium text-brand-navy">Dodo Payments</span>, the merchant of record, takes payment for paid plans where they are offered.</li>
        </ul>
    </section>

    <section class="mt-8" aria-labelledby="changes-heading"><h2 id="changes-heading" class="section-title">Changes to this policy</h2>
        <p class="prose-policy mt-2">Every change to this page is a commit in the public repository, so its history is the change log. The date at the top is the last substantive update. If a change materially reduces your protection, we will say so on the site rather than only editing the page.</p>
    </section>

    <x-site.disclaimer class="mt-8" />
</div>
@endsection
