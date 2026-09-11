@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">AI risk</p>
    <p class="eyebrow mt-3">AI risk, evidenced</p>
    <h1 class="mt-2 font-display text-3xl sm:text-4xl font-semibold text-brand-navy max-w-[26ch]">Advanced AI must be handled with great responsibility. Here is what has actually gone wrong, who it hurt, and which rules answer it.</h1>
    <p class="mt-4 max-w-[68ch] text-brand-body leading-7">Concern about AI risk is now shared by researchers, boards, regulators and heads of state. This page keeps that concern honest: every number below comes from the AI Incident Database (recorded harms) and the MIT AI Risk Repository (how experts classify risk), is dated, and links to the record behind it and to the policy instruments that respond.</p>

    <dl class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5 text-sm">
        @foreach([['Recorded incidents', number_format($aiid['totals']['incidents'] ?? 0), route('risk.incidents.browse'), 'since 2012, AI Incident Database'], ['Last 12 months', number_format($narrative['last12']), route('risk.incidents.browse', ['year' => now()->year]), $narrative['growth'] === null ? 'vs previous 12 months: —' : ($narrative['growth'] >= 0 ? '+' : '').$narrative['growth'].'% vs previous 12 months'], ['Classified to a risk domain', number_format($narrative['classified']), route('risk.incidents'), 'MIT taxonomy applied by AIID'], ['Risk entries', number_format($incidentTotals['risks']), route('risk.risks'), 'from 74 frameworks, MIT AI Risk Repository'], ['Instruments tracked', number_format(\App\Models\PolicyInstrument::published()->count()), route('policies.index'), 'across '.\App\Models\Jurisdiction::published()->count().' jurisdictions']] as [$label, $value, $href, $sub])
        <div class="card-flat p-4"><dt class="meta">{{ $label }}</dt><dd class="mt-1 font-mono tabular-nums text-2xl text-brand-navy">{{ $value }}</dd><a href="{{ $href }}" class="text-xs text-brand-muted hover:text-brand-navy">{{ $sub }}</a></div>
        @endforeach
    </dl>

    <section class="mt-12" aria-labelledby="story-heading">
        <div class="rule-strong pt-3"><h2 id="story-heading" class="section-title">1. Harm is rising, and its shape is changing</h2></div>
        <p class="mt-2 max-w-[68ch] text-brand-body leading-7">Recorded incidents grow year on year while the mix shifts: generative systems moved misinformation, impersonation and fraud from the margins to the centre. The timeline marks the policy milestones that followed; each bar opens the incidents of that year.</p>
        <x-site.timeline-chart class="mt-4" :series="collect($aiid['incidents_per_year'] ?? [])->filter(fn ($v, $y) => $y >= 2016)" :milestones="$narrative['milestones']" title="AI incidents recorded per year, with policy milestones" note="Incident date; the current year is partial. Milestones are adoption or application dates recorded in the policy tracker." />
        @if($narrative['domain_share'])
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            @foreach($narrative['domain_share'] as $year => $share)
            <x-site.bar-chart :series="collect($share)->mapWithKeys(fn ($v, $k) => [\Illuminate\Support\Str::limit(preg_replace('/^(AI system safety).*/', '$1…', $k), 24) => $v])" :title="'Share of classified incidents by domain, '.$year.' (%)'" :height="140" note="Percent of incidents classified in that year" />
            @endforeach
        </div>
        @endif
    </section>

    <section class="mt-12" aria-labelledby="who-heading">
        <div class="rule-strong pt-3"><h2 id="who-heading" class="section-title">2. Who is harmed, and who deploys the systems involved</h2></div>
        <p class="mt-2 max-w-[68ch] text-brand-body leading-7">Harm concentrates on identifiable groups: minors, women, the public, workers and specific communities. The organisations named as deployers repeat, which is where obligations for deployers, transparency and post-market monitoring bite.</p>
        <div class="mt-4 grid gap-6 lg:grid-cols-3">
            <x-site.bar-chart :series="collect($narrative['top_harmed'])->mapWithKeys(fn ($v, $k) => [\Illuminate\Support\Str::limit($k, 22) => $v])" title="Most frequently named harmed parties" :height="200" :scale="true" note="Alleged harmed parties as recorded by AIID" />
            <x-site.bar-chart :series="collect($narrative['top_deployers'])->mapWithKeys(fn ($v, $k) => [\Illuminate\Support\Str::limit($k, 22) => $v])" title="Most frequently named deployers" :height="200" :scale="true" note="Alleged deployers; a name is an allegation in a report, not a finding" />
            <x-site.bar-chart :series="collect($aiid['by_harm_level'] ?? [])->mapWithKeys(fn ($v, $k) => [\Illuminate\Support\Str::limit(ucfirst($k), 22) => $v])" title="Assessed harm level (CSET)" :height="200" note="Only incidents with a CSET assessment" />
        </div>
    </section>

    <section class="mt-12" aria-labelledby="gap-heading">
        <div class="rule-strong pt-3"><h2 id="gap-heading" class="section-title">3. Where harm is recorded versus where rules exist</h2></div>
        <p class="mt-2 max-w-[68ch] text-brand-body leading-7">For policymakers and funders the question is coverage: do the places where incidents are recorded have binding AI rules? Only {{ number_format($narrative['with_country']) }} incidents carry a country code, and reporting is biased toward English-language media, so read this as a prompt for enquiry rather than a ranking.</p>
        <div class="table-wrap mt-4"><table><caption class="sr-only">Incidents by country and the AI instruments recorded there</caption><thead><tr><th scope="col">Country</th><th scope="col" class="text-right">Incidents</th><th scope="col" class="text-right">Instruments tracked</th><th scope="col" class="text-right">Binding</th><th scope="col">Status</th></tr></thead><tbody>
            @foreach($narrative['gap'] as $row)
            <tr><td>@if($row['jurisdiction'])<a href="{{ $row['jurisdiction']->url() }}">{{ $row['jurisdiction']->name }}</a>@else<span class="font-mono">{{ $row['code'] }}</span> <span class="meta">(no record)</span>@endif</td><td class="text-right font-mono">{{ $row['incidents'] }}</td><td class="text-right font-mono">{{ $row['jurisdiction'] ? ($row['jurisdiction']->instruments ?: '—') : '—' }}</td><td class="text-right font-mono">{{ $row['jurisdiction'] ? ($row['jurisdiction']->binding ?: '—') : '—' }}</td><td class="text-brand-body">{{ $row['jurisdiction'] ? \Illuminate\Support\Str::limit($row['jurisdiction']->regulatory_status_summary, 110) : 'Not yet recorded' }}</td></tr>
            @endforeach
        </tbody></table></div>
    </section>

    <section class="mt-12" aria-labelledby="respond-heading">
        <div class="rule-strong pt-3"><h2 id="respond-heading" class="section-title">4. How policy responds, domain by domain</h2></div>
        <p class="mt-2 max-w-[68ch] text-brand-body leading-7">Each domain links to the instruments whose recorded use cases address it, and to the obligations, deadlines and templates behind them. Click a domain for its subdomains, frameworks and incidents.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($mit['domains'] ?? [] as $d)
            <a href="{{ route('risk.domain', $d['id']) }}" class="card-flat p-4 no-underline hover:border-brand-navy">
                <p class="eyebrow">Domain {{ $d['id'] }}</p><p class="mt-1 font-semibold text-brand-navy">{{ $d['name'] }}</p>
                <dl class="mt-2 grid grid-cols-3 gap-1 text-xs text-brand-muted"><div><dt>Incidents</dt><dd class="font-mono text-brand-navy">{{ isset($aiid['by_mit_domain'][$d['aiid_domain_label']]) ? number_format($aiid['by_mit_domain'][$d['aiid_domain_label']]) : '—' }}</dd></div><div><dt>Risk entries</dt><dd class="font-mono text-brand-navy">{{ isset($riskByDomain[$d['id']]) ? number_format($riskByDomain[$d['id']]) : '—' }}</dd></div><div><dt>Instruments</dt><dd class="font-mono text-brand-navy">{{ $narrative['domain_instruments'][$d['id']] ?: '—' }}</dd></div></dl>
            </a>
            @endforeach
        </div>
    </section>

    <section class="mt-12" aria-labelledby="persona-heading">
        <div class="rule-strong pt-3"><h2 id="persona-heading" class="section-title">5. What to do with this, depending on who you are</h2></div>
        <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4 text-sm">
            <div class="card-flat p-4"><p class="font-semibold text-brand-navy">AI CISO or compliance lead</p><p class="mt-1 text-brand-body">Start from the domains your systems touch, then the obligations with dates.</p><ul class="mt-2 space-y-1"><li><a href="{{ route('tools.applicability') }}">Applicability check</a></li><li><a href="{{ route('obligations.index') }}">Obligations with deadlines</a></li><li><a href="{{ route('tools.show', 'ai-risk-register-template') }}">AI risk register template</a></li></ul></div>
            <div class="card-flat p-4"><p class="font-semibold text-brand-navy">Researcher</p><p class="mt-1 text-brand-body">Filter, drill down and export with licence and citation attached.</p><ul class="mt-2 space-y-1"><li><a href="{{ route('risk.incidents.browse') }}">Browse incidents</a></li><li><a href="{{ route('risk.risks') }}">Browse risk entries</a></li><li><a href="{{ route('open-data') }}">Open data and API</a></li></ul></div>
            <div class="card-flat p-4"><p class="font-semibold text-brand-navy">Policymaker or diplomat</p><p class="mt-1 text-brand-body">Compare jurisdictions and see which harms remain unaddressed.</p><ul class="mt-2 space-y-1"><li><a href="{{ route('compare.index') }}">Compare jurisdictions</a></li><li><a href="{{ route('jurisdictions.show', 'international') }}">International instruments</a></li><li><a href="{{ route('changes.index') }}">Dated change log</a></li></ul></div>
            <div class="card-flat p-4"><p class="font-semibold text-brand-navy">Civil society, donor, journalist</p><p class="mt-1 text-brand-body">Evidence of who is harmed and where governance is missing, with sources.</p><ul class="mt-2 space-y-1"><li><a href="{{ route('risk.incidents') }}">Incident summary</a></li><li><a href="{{ route('jurisdictions.index') }}">Every jurisdiction, honestly recorded</a></li><li><a href="{{ route('subscribe.show') }}">Weekly digest</a></li></ul></div>
        </div>
    </section>

    <section class="mt-12 grid gap-8 lg:grid-cols-12" aria-labelledby="causal-heading">
        <div class="lg:col-span-7">
            <div class="rule-strong pt-3"><h2 id="causal-heading" class="section-title">Causal taxonomy: who causes the risk, and was it intended?</h2></div>
            <p class="mt-2 meta">Risk entries in the MIT database by responsible entity and intent. Timing: @foreach($timing as $t => $n){{ $t }} {{ number_format($n) }}@if(!$loop->last) · @endif @endforeach.</p>
            @if($matrix)<x-site.matrix-chart class="mt-3" :rows="['Human', 'AI', 'Other', 'Not coded']" :cols="['Intentional', 'Unintentional', 'Other', 'Not coded']" :cells="$matrix" title="Risk entries by entity × intent" rowLabel="Entity" colLabel="Intent" />@else<x-site.empty class="mt-3" title="Risk database not imported yet" />@endif
        </div>
        <aside class="lg:col-span-5">
            <div class="rule-strong pt-3"><h2 class="section-title">For researchers</h2></div>
            <ul class="mt-3 divide-y divide-brand-line text-sm">
                <li class="py-3"><a href="{{ route('risk.risks') }}" class="font-display text-lg text-brand-navy no-underline hover:underline">Browse {{ number_format($incidentTotals['risks']) }} risk entries</a><p class="meta">Filter by domain, subdomain, entity, intent, timing, evidence level and framework; export CSV/JSON.</p></li>
                <li class="py-3"><a href="{{ route('risk.incidents.browse') }}" class="font-display text-lg text-brand-navy no-underline hover:underline">Browse {{ number_format($incidentTotals['incidents']) }} incidents</a><p class="meta">Filter by year, domain, subdomain, country, sector, harm level and keyword; export CSV/JSON.</p></li>
                <li class="py-3"><a href="{{ route('risk.frameworks') }}" class="font-display text-lg text-brand-navy no-underline hover:underline">Frameworks and papers</a><p class="meta">The 74 documents synthesised by the repository, with entry counts.</p></li>
                <li class="py-3"><a href="{{ route('open-data') }}" class="font-display text-lg text-brand-navy no-underline hover:underline">Open data</a><p class="meta">Full datasets, taxonomy JSON and citation guidance.</p></li>
            </ul>
        </aside>
    </section>

    <section class="mt-12" aria-labelledby="explore-heading">
        <div class="rule-strong pt-3"><h2 id="explore-heading" class="section-title">Explore: domains and subdomains</h2></div>
        <p class="mt-2 max-w-[64ch] text-sm text-brand-body">Each band is a domain; each block a subdomain, sized by how much of the evidence it carries. Click a block for its definition, causal breakdowns, frameworks, risk entries and incidents.</p>
        <div class="mt-4 grid gap-6 lg:grid-cols-2">
            <x-site.treemap :rows="$treemapRisks" title="Risk entries by subdomain (MIT AI Risk Repository)" note="2,500 entries from 74 frameworks, coded to a subdomain" />
            <x-site.treemap :rows="$treemapIncidents" title="Recorded incidents by subdomain (AI Incident Database)" note="Incidents classified with the MIT taxonomy only" />
        </div>
    </section>
    <x-site.attribution class="mt-12" :name="$mit['source'] ?? 'MIT AI Risk Repository'" :url="$mit['source_url'] ?? 'https://airisk.mit.edu/'" :license="$mit['license'] ?? 'CC BY 4.0'" :licenseUrl="$mit['license_url'] ?? 'https://creativecommons.org/licenses/by/4.0/'" :citation="$mit['citation'] ?? null" :note="$mit['changes_note'] ?? null" />
    <x-site.attribution class="mt-3" :name="$aiid['source'] ?? 'AI Incident Database'" :url="$aiid['source_url'] ?? 'https://incidentdatabase.ai/'" :license="$aiid['license'] ?? 'CC BY-SA 4.0'" :licenseUrl="$aiid['license_url'] ?? 'https://creativecommons.org/licenses/by-sa/4.0/'" :citation="$aiid['citation'] ?? null" :date="$aiid['snapshot_date'] ?? null" note="Aggregates only; report texts are not reproduced." />
    <x-site.disclaimer class="mt-6" />
</div>
@endsection
