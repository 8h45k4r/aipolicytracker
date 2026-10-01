@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">People</p>
    <h1 class="mt-2 font-display text-3xl sm:text-4xl font-semibold text-brand-navy max-w-[22ch]">People behind AIPolicyTracker</h1>
    <p class="mt-4 max-w-[64ch] text-lg leading-8 text-brand-body">AIPolicyTracker is an open, source-backed AI policy and governance research platform. The work is maintained independently and strengthened by independent researchers, domain experts and community contributors worldwide.</p>

    <div class="mt-10 grid gap-10 lg:grid-cols-12">
        <div class="lg:col-span-8 space-y-10">
            <section aria-labelledby="core-heading">
                <div class="rule-strong pt-3"><h2 id="core-heading" class="section-title">Maintainer</h2></div>
                <ul class="mt-4 grid gap-4">
                    @foreach($team['core'] as $person)
                    @include('site.pages._person', ['person' => $person])
                    @endforeach
                </ul>
            </section>

            <section aria-labelledby="contributors-heading">
                <div class="rule-strong pt-3"><h2 id="contributors-heading" class="section-title">Research contributors</h2></div>
                <ul class="mt-4 grid gap-4">
                    @foreach($team['contributors'] as $person)
                    @include('site.pages._person', ['person' => $person])
                    @endforeach
                </ul>
            </section>

            <section aria-labelledby="advisors-heading">
                <div class="rule-strong pt-3"><h2 id="advisors-heading" class="section-title">Independent advisors</h2></div>
                @if($team['advisors'])
                <ul class="mt-4 grid gap-4">
                    @foreach($team['advisors'] as $person)
                    @include('site.pages._person', ['person' => $person + ['role' => 'Advisor — '.$person['specialism'], 'bio' => $person['scope']]])
                    @endforeach
                </ul>
                @else
                <p class="mt-3 text-sm text-brand-body">No independent advisors are listed at present. Advisors are named here once appointed, with the scope of their advice.</p>
                @endif
                <p class="mt-3 text-xs text-brand-muted">Advisors act in an independent capacity. AIPolicyTracker does not provide legal advice.</p>
            </section>

            <section aria-labelledby="community-heading">
                <div class="rule-strong pt-3"><h2 id="community-heading" class="section-title">Community contributors</h2></div>
                <p class="prose-policy mt-3">We welcome source submissions, corrections, translations, policy research and technical contributions. Contributors are credited where appropriate and may ask to remain unlisted.</p>
                <p class="mt-3 text-sm"><a href="{{ route('contribute') }}" class="btn-primary">Contribute to the project</a> <a href="{{ config('aipolicytracker.github_url') }}" rel="noopener" class="btn-secondary ml-2">View the repository</a></p>
            </section>
        </div>

        <aside class="lg:col-span-4 space-y-8">
            <section aria-labelledby="independence-heading">
                <div class="rule-strong pt-3"><h2 id="independence-heading" class="section-title">Independence and attribution</h2></div>
                <p class="mt-3 text-sm text-brand-body">AIPolicyTracker is maintained independently by {{ rtrim($organization['name'], '.') }}. Individual affiliations are listed for identification only and do not imply institutional endorsement, sponsorship, partnership or responsibility for AIPolicyTracker's content. All policy records remain subject to the platform's <a href="{{ route('methodology') }}">source-verification methodology</a>.</p>
            </section>
            <section aria-labelledby="roster-heading">
                <div class="rule-strong pt-3"><h2 id="roster-heading" class="section-title">Who verifies records</h2></div>
                <p class="mt-3 text-sm text-brand-body">Only a reviewer published on the <a href="{{ route('reviewers') }}">reviewer roster</a>, with a declaration of interest, may mark a record verified. Being listed on this page is not the same as being on the roster.</p>
            </section>
        </aside>
    </div>
</div>
@endsection
