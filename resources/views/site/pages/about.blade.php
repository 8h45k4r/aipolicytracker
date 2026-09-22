@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">About</p>
    <h1 class="mt-2 font-display text-3xl sm:text-4xl font-semibold text-brand-navy max-w-[22ch]">AI policy you can trace back to the source</h1>
    <p class="mt-4 max-w-[64ch] text-lg leading-8 text-brand-body">AIPolicyTracker is an open reference for artificial-intelligence laws, strategies, guidance and the obligations they create. It is built for the people who have to act on policy rather than only read about it: compliance and legal teams, product owners, public-sector buyers, researchers and journalists.</p>

    <div class="mt-10 grid gap-10 lg:grid-cols-12">
        <div class="lg:col-span-8 space-y-10">
            <section aria-labelledby="why-heading">
                <div class="rule-strong pt-3"><h2 id="why-heading" class="section-title">Why it exists</h2></div>
                <p class="prose-policy mt-3">AI regulation is now global, but the tools for following it are uneven. Large jurisdictions are covered by many trackers; South Asia, ASEAN, Africa, the Gulf and Latin America are often reduced to a paragraph. Summaries circulate without links to the instrument they describe, and readers cannot tell whether a date was checked last week or two years ago.</p>
                <p class="prose-policy mt-3">This project takes a narrower position. Every record cites an official source and states when it was last checked. Regulatory status uses a small, explicit vocabulary. Obligations are broken out so they can be mapped to controls and evidence. The whole dataset is open, versioned and reviewable in public.</p>
            </section>

            <section aria-labelledby="how-heading">
                <div class="rule-strong pt-3"><h2 id="how-heading" class="section-title">How to use it</h2></div>
                <dl class="mt-3 divide-y divide-brand-line">
                    <div class="py-3 grid gap-1 sm:grid-cols-12 sm:gap-6"><dt class="sm:col-span-4 font-medium text-brand-navy"><a href="{{ route('jurisdictions.index') }}">By jurisdiction</a></dt><dd class="sm:col-span-8 text-sm text-brand-body">Start from a country or bloc: regulatory status, binding rules versus guidance, regulators and official sources.</dd></div>
                    <div class="py-3 grid gap-1 sm:grid-cols-12 sm:gap-6"><dt class="sm:col-span-4 font-medium text-brand-navy"><a href="{{ route('policies.index') }}">By instrument</a></dt><dd class="sm:col-span-8 text-sm text-brand-body">Filter acts, regulations, strategies and guidance by status, actor, sector and use case, then open the record for dates, scope and obligations.</dd></div>
                    <div class="py-3 grid gap-1 sm:grid-cols-12 sm:gap-6"><dt class="sm:col-span-4 font-medium text-brand-navy"><a href="{{ route('obligations.index') }}">By obligation</a></dt><dd class="sm:col-span-8 text-sm text-brand-body">Read requirements as practical tasks with the article they come from, who they bind and what evidence they imply.</dd></div>
                    <div class="py-3 grid gap-1 sm:grid-cols-12 sm:gap-6"><dt class="sm:col-span-4 font-medium text-brand-navy"><a href="{{ route('risk.index') }}">By risk</a></dt><dd class="sm:col-span-8 text-sm text-brand-body">Move from a category of harm to the incidents recorded for it and the policies that respond, using the MIT AI Risk Repository taxonomy as a shared vocabulary.</dd></div>
                    <div class="py-3 grid gap-1 sm:grid-cols-12 sm:gap-6"><dt class="sm:col-span-4 font-medium text-brand-navy"><a href="{{ route('changes.index') }}">By date</a></dt><dd class="sm:col-span-8 text-sm text-brand-body">Follow the change log and RSS feed for dated, source-linked developments and upcoming application dates.</dd></div>
                    <div class="py-3 grid gap-1 sm:grid-cols-12 sm:gap-6"><dt class="sm:col-span-4 font-medium text-brand-navy"><a href="{{ route('open-data') }}">As data</a></dt><dd class="sm:col-span-8 text-sm text-brand-body">Download the dataset, call the read-only API or point an AI assistant at llms.txt; everything carries its source and licence.</dd></div>
                </dl>
            </section>

            <section aria-labelledby="method-heading">
                <div class="rule-strong pt-3"><h2 id="method-heading" class="section-title">How records are made</h2></div>
                <ol class="mt-3 space-y-2 text-sm text-brand-body list-decimal pl-5">
                    <li>A record is created from an official document (legislation, gazette, regulator or ministry publication) with its URL, publisher and access date.</li>
                    <li>Status, dates, scope and obligations are written in plain language with article references; no legal commentary is copied.</li>
                    <li>A reviewer opens the source, confirms each field and sets the verification date. Until then the record is shown as source-linked, not verified.</li>
                    <li>Changes are logged with what changed and its practical impact, and the dataset is versioned in the open repository.</li>
                </ol>
                <p class="mt-3 text-sm"><a href="{{ route('methodology') }}">Full methodology, status definitions and source tiers</a></p>
            </section>

            <section aria-labelledby="refs-heading">
                <div class="rule-strong pt-3"><h2 id="refs-heading" class="section-title">Datasets and research we build on</h2></div>
                <p class="mt-3 text-sm text-brand-body">The project stands on open work by others. Each is credited where it is used, and the licences below govern reuse of that material.</p>
                <ol class="mt-3 divide-y divide-brand-line text-sm">
                    @foreach($references as $ref)
                    <li class="py-3"><span class="text-brand-body">{{ $ref['authors'] }}.</span> <a href="{{ $ref['url'] }}" rel="noopener">{{ $ref['title'] }}</a>. <span class="meta">{{ $ref['publisher'] }}@if($ref['license']) · {{ $ref['license'] }}@endif</span></li>
                    @endforeach
                </ol>
            </section>

            <x-site.faq :items="$faq" title="Questions people ask" />
        </div>

        <aside class="lg:col-span-4 space-y-8">
            <section aria-labelledby="team-heading">
                <div class="rule-strong pt-3"><h2 id="team-heading" class="section-title">Who is behind it</h2></div>
                <ul class="mt-3 space-y-3 text-sm">
                    @foreach($maintainers as $m)
                    <li><a href="{{ $m['url'] }}" rel="me noopener" class="font-medium text-brand-navy">{{ $m['name'] }}</a><span class="meta block">{{ $m['role'] }}</span>
                        <ul class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                            <li><a href="{{ $m['url'] }}" rel="me noopener" class="text-brand-body hover:text-brand-navy">Website</a></li>
                            @foreach($m['same_as'] ?? [] as $link)
                            @php($host = parse_url($link, PHP_URL_HOST))
                            <li><a href="{{ $link }}" rel="me noopener" class="text-brand-body hover:text-brand-navy">{{ str_contains($host, 'linkedin') ? 'Get connected on LinkedIn' : (str_contains($host, 'x.com') || str_contains($host, 'twitter') ? 'Follow on X (Twitter)' : (str_contains($host, 'github') ? 'GitHub' : $host)) }}</a></li>
                            @endforeach
                        </ul></li>
                    @endforeach
                    <li><a href="{{ $organization['url'] }}" rel="noopener" class="font-medium text-brand-navy">{{ $organization['name'] }}</a><span class="meta block">{{ $organization['tagline'] }}</span></li>
                </ul>
                <p class="mt-3 text-sm text-brand-body">AIPolicyTracker is AI governance intelligence, from regulation to evidence. <a href="{{ config('aipolicytracker.certifyi_url') }}" rel="noopener" data-track="certifyi_click">Certifyi</a> is a separate product for turning obligations into owned tasks and evidence; nothing here requires an account there.</p>
            </section>
            <section aria-labelledby="open-heading">
                <div class="rule-strong pt-3"><h2 id="open-heading" class="section-title">Open by default</h2></div>
                <ul class="mt-3 space-y-2 text-sm text-brand-body">
                    <li>Code: Apache-2.0 on <a href="{{ config('aipolicytracker.github_url') }}" rel="noopener" data-track="github_click">GitHub</a></li>
                    <li>Policy data: {{ config('aipolicytracker.data_license') }}</li>
                    <li>Every change passes public review gates (engineering, UI, documentation, compliance)</li>
                    <li>Corrections: <a href="{{ route('contribute') }}">contribution form</a> or a pull request</li>
                </ul>
            </section>
            <section aria-labelledby="reviewers-heading">
                <div class="rule-strong pt-3"><h2 id="reviewers-heading" class="section-title">Reviewers wanted</h2></div>
                <p class="mt-3 text-sm text-brand-body">We are looking for people with legal, policy or compliance expertise in specific jurisdictions to verify records against official sources. Reviewers are credited on the records they verify. A single record usually takes under an hour.</p>
                <a href="{{ route('contribute', ['type' => 'reviewer_application']) }}" class="btn-primary mt-3">Volunteer as a reviewer</a>
            </section>
            <section aria-labelledby="contact-heading">
                <div class="rule-strong pt-3"><h2 id="contact-heading" class="section-title">Contact</h2></div>
                <p class="mt-3 text-sm text-brand-body">@if($contacts)@foreach($contacts as $c)<a href="mailto:{{ $c }}">{{ $c }}</a>@if(!$loop->last), @endif @endforeach<br>@endif Issues and pull requests on GitHub. Security reports: see SECURITY.md in the repository.</p>
            </section>
        </aside>
    </div>
    <x-site.disclaimer class="mt-10" />
</div>
@endsection
