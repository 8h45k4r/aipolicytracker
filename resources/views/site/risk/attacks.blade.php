@extends('site.layouts.app')
@section('content')
@php
    use App\Services\ExternalData\AttackLandscape;
    use Illuminate\Support\Carbon;

    // The findings are printed in section 6, so the layout does not print them again.
    request()->attributes->set('faq.rendered', true);

    $t = $d['totals'];
    $meta = AttackLandscape::LENSES[$lens];
    $to = $d['window']['through'] ? Carbon::parse($d['window']['through'].'-01')->format('F Y') : null;
    $period = $to ? '12 months to '.$to : 'last 12 months';
    $pct = fn (array $counts, string $key) => array_sum($counts) ? (int) round(100 * ($counts[$key] ?? 0) / array_sum($counts)) : 0;
    $change = $t['growth'] === null ? 'no earlier period to compare' : ($t['growth'] > 0 ? '+' : '').$t['growth'].'% on the 12 months before ('.number_format($t['prev12']).')';

    $trendHeading = match ($t['trend']) {
        'rising' => 'Rising: up '.abs((int) $t['growth']).'% in the '.$period,
        'falling' => 'Falling: down '.abs((int) $t['growth']).'% in the '.$period,
        'flat' => 'Level: within '.\App\Http\Controllers\Site\RiskController::TREND_FLAT_WITHIN.'% of the year before, in the '.$period,
        default => 'Incidents month by month',
    };

    // The thickest band of the flow chart, stated in words.
    $pairs = [];
    foreach ($d['flow']['entity_intent'] as $entity => $row) {
        foreach ($row as $intent => $n) {
            $pairs[] = [$entity, $intent, $n];
        }
    }
    usort($pairs, fn ($a, $b) => $b[2] <=> $a[2]);
    $intentWord = ['Intentional' => 'deliberate', 'Unintentional' => 'accidental', 'Other' => 'unclear'];
    $entityWord = ['Human' => 'a person', 'AI' => 'an AI system', 'Other' => 'another cause'];

    $causal = [];
    $causal['Responsible'] = collect(['Human' => 'A person', 'AI' => 'An AI system', 'Other' => 'Other or unclear'])->mapWithKeys(fn ($label, $k) => [$label => [$pct($d['causal']['incidents']['entity'], $k), $pct($d['causal']['risks']['entity'], $k)]])->all();
    if ($lens !== 'deliberate') {
        $causal['Intent'] = collect(['Intentional' => 'Deliberate', 'Unintentional' => 'Accidental', 'Other' => 'Other or unclear'])->mapWithKeys(fn ($label, $k) => [$label => [$pct($d['causal']['incidents']['intent'], $k), $pct($d['causal']['risks']['intent'], $k)]])->all();
    }
    $causal['Timing'] = collect(['Post-deployment' => 'After release', 'Pre-deployment' => 'Before release', 'Other' => 'Other or unclear'])->mapWithKeys(fn ($label, $k) => [$label => [$pct($d['causal']['incidents']['timing'], $k), $pct($d['causal']['risks']['timing'], $k)]])->all();

    $signalTone = ['rising' => '#9B1C2E', 'emerging' => '#7A4B00', 'steady' => '#002147', 'easing' => '#0B6B4F', 'sparse' => '#5D6B7E'];
    $asOf = $d['fresh']['as_of'] ? Carbon::parse($d['fresh']['as_of']) : null;
@endphp
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">AI risk</p>
    <h1 class="mt-2 font-display text-3xl sm:text-4xl font-semibold text-brand-navy max-w-[28ch]">AI attacks and incidents: the record in charts</h1>
    <p class="mt-4 max-w-[68ch] text-brand-body leading-7">What has gone wrong with AI, who caused it, whether it was meant, and how that compares with the risks that frameworks describe. Every figure is a count of records in the <a href="{{ route('risk.incidents') }}">AI Incident Database</a> or the <a href="{{ route('risk.risks') }}">MIT AI Risk Repository</a>. "Attack" here means an incident coded as deliberate. This page has no list of records: those are on the pages it links to.</p>

    <div data-attack-panel data-version="{{ $version }}" data-pulse="{{ route('attacks.pulse') }}">
        <nav class="mt-6 flex flex-wrap items-center gap-2" aria-label="Choose what to look at">
            @foreach(AttackLandscape::LENSES as $key => $l)
            <a href="{{ route('attacks.index', $key === 'all' ? [] : ['lens' => $key]) }}" class="chip {{ $key === $lens ? 'chip-active' : '' }}" data-attack-lens @if($key === $lens) aria-current="true" @endif>{{ $l['label'] }}</a>
            @endforeach
        </nav>
        <p class="mt-2 max-w-[80ch] text-sm text-brand-body">{{ $meta['definition'] }}</p>

        <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 meta" data-attack-fresh>
            <span class="inline-flex items-center gap-1.5 text-brand-navy"><svg width="8" height="8" aria-hidden="true" class="{{ $d['fresh']['live'] ? 'pulse-dot' : '' }}"><circle cx="4" cy="4" r="4" fill="#0B6B4F" /></svg>@if($asOf){{ $d['fresh']['live'] ? 'Synced' : 'Snapshot of' }} <time datetime="{{ $asOf->toIso8601String() }}">{{ $asOf->format($d['fresh']['live'] ? 'j M Y, H:i' : 'j M Y') }}{{ $d['fresh']['live'] ? ' UTC' : '' }}</time>@else Not imported yet @endif</span>
            <span>{{ number_format($t['corpus']) }} incidents on record</span>
            @if($d['fresh']['latest_incident'])<span>newest dated {{ Carbon::parse($d['fresh']['latest_incident'])->format('j M Y') }}</span>@endif
            @if($to)<span>classified to {{ $to }}</span>@endif
            <span>synced from the source once a day; this page checks for new records every five minutes while it is open<span data-attack-checked></span></span>
        </p>

        <x-site.answer-box class="mt-6" :text="$answer" />

        <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-site.stat :label="$lens === 'all' ? 'Recorded incidents' : 'Incidents in view'" :value="number_format($t['lens'])" ":note="$t['share'] === null ? 'in the AI Incident Database' : $t['share'].'% of the '.number_format($t['classified_corpus']).' classified'" />
            <x-site.stat :label="ucfirst($period)" :value="number_format($t['last12'])" :note="$change" />
            @if($lens === 'deliberate')
            <x-site.stat label="Carried out by a person" :value="$pct($d['causal']['incidents']['entity'], 'Human').'%'" :note="$pct($d['causal']['incidents']['entity'], 'AI').'% attributed to an AI system'" />
            @else
            <x-site.stat label="Coded deliberate" :value="$pct($d['causal']['incidents']['intent'], 'Intentional').'%'" :note="'of '.number_format(array_sum($d['causal']['incidents']['intent'])).' incidents with an intent code'" />
            @endif
            <x-site.stat label="Risk entries, same coding" :value="number_format($t['risks'])" :href="route('risk.risks')" :note="'from '.$t['frameworks'].' frameworks, MIT AI Risk Repository'" />
            <x-site.stat label="Not yet classified" :value="number_format($d['window']['unclassified_since'])" :note="$to ? 'incidents dated after '.$to : 'newest incidents'" />
        </dl>

        <section class="mt-12" aria-labelledby="pulse-heading">
            <div class="rule-strong pt-3"><h2 id="pulse-heading" class="section-title" data-trend="{{ $t['trend'] }}">1. {{ $trendHeading }}</h2></div>
            <p class="mt-2 max-w-[72ch] text-brand-body leading-7">{{ number_format($t['last12']) }} {{ $meta['noun'] }} are dated in the {{ $period }}, against {{ number_format($t['prev12']) }} in the 12 months before. @if($d['window']['unclassified_since'])The comparison stops at {{ $to }} on purpose: {{ number_format($d['window']['unclassified_since']) }} newer incidents are on record without a classification, and counting those months would show a fall that is only a backlog.@endif</p>
            <x-site.pulse-chart class="mt-4" :months="$d['months']" :title="'Incidents per month, '.count($d['months']).' months'" :lensLabel="$lens === 'all' ? 'Classified' : $meta['label']" note="By date of the incident. A month counts as classified once {{ AttackLandscape::CLASSIFIED_SHARE }}% of its incidents carry a code." />
        </section>

        <section class="mt-12" aria-labelledby="flow-heading">
            <div class="rule-strong pt-3"><h2 id="flow-heading" class="section-title">2. Who was responsible, was it meant, and what kind of harm</h2></div>
            @if($pairs)
            <p class="mt-2 max-w-[72ch] text-brand-body leading-7">The widest path is {{ $entityWord[$pairs[0][0]] ?? $pairs[0][0] }} acting with {{ $intentWord[$pairs[0][1]] ?? $pairs[0][1] }} effect: {{ number_format($pairs[0][2]) }} of the {{ number_format($t['classified']) }} classified incidents in view ({{ $t['classified'] ? round(100 * $pairs[0][2] / $t['classified']) : 0 }}%). Follow a colour from left to right to see where deliberate and accidental incidents end up.</p>
            <x-site.flow-chart class="mt-4" :left="$d['flow']['entity_intent']" :right="$d['flow']['intent_domain']" :domains="$d['flow']['domains']" title="From cause to harm: responsible entity, intent and risk domain" note="MIT causal and domain taxonomies, as applied by the AI Incident Database" />
            @else
            <x-site.empty class="mt-4" title="No classified incidents in view" />
            @endif
        </section>

        <section class="mt-12" aria-labelledby="heat-heading">
            <div class="rule-strong pt-3"><h2 id="heat-heading" class="section-title">3. Where incidents concentrate, year by year</h2></div>
            <p class="mt-2 max-w-[72ch] text-brand-body leading-7">The eight kinds of incident with the most records in view. A darker cell is a larger count. The last column is a part year, and its newest incidents are not classified yet.</p>
            @if($d['heat']['rows'])
            <x-site.matrix-chart class="mt-4" :rows="array_keys($d['heat']['rows'])" :cols="$d['heat']['years']" :cells="$d['heat']['rows']" title="Incidents by risk subdomain and year of the incident" rowLabel="Subdomain" colLabel="Year" />
            @endif
        </section>

        <section class="mt-12" aria-labelledby="gap-heading">
            <div class="rule-strong pt-3"><h2 id="gap-heading" class="section-title">4. What is described against what is recorded</h2></div>
            <p class="mt-2 max-w-[72ch] text-brand-body leading-7">The MIT AI Risk Repository codes {{ number_format($t['risks']) }} risk entries from {{ $t['frameworks'] }} frameworks to the same subdomains as the incidents in view. Where the two shares differ, either the frameworks are ahead of the record or the record is ahead of the frameworks.@if($d['rho'] !== null) Across {{ count($d['points']) }} subdomains the two rankings agree {{ abs($d['rho']) >= 0.7 ? 'closely' : (abs($d['rho']) >= 0.4 ? 'moderately' : 'weakly') }} (Spearman's rank correlation {{ number_format($d['rho'], 2) }}).@endif</p>
            <x-site.quadrant-chart class="mt-4" :points="$d['points']" title="Risk subdomains: share of risk entries against share of incidents" xLabel="Share of risk entries described by frameworks" yLabel="Share of incidents recorded" note="Select a bubble to open the subdomain" />
            <x-site.paired-bars class="mt-6" :groups="$causal" title="How incidents arise, against how frameworks expect them to" aLabel="Incidents recorded" bLabel="Risk entries described" note="Percent of the records that carry the code" />
        </section>

        <section class="mt-12" aria-labelledby="who-heading">
            <div class="rule-strong pt-3"><h2 id="who-heading" class="section-title">5. Who is affected</h2></div>
            <p class="mt-2 max-w-[72ch] text-brand-body leading-7">The parties named most often as harmed, or nearly harmed, in the incidents in view. A name is an allegation in a report, not a finding.@if($d['sectors']['coded'] >= 30) Only {{ number_format($d['sectors']['coded']) }} of the {{ number_format($t['lens']) }} incidents carry a sector, so read that chart as a sample.@endif</p>
            <div class="mt-4 grid gap-6 {{ $d['sectors']['coded'] >= 30 ? 'lg:grid-cols-2' : '' }}">
                @if($d['harmed'])<x-site.rank-bars :series="$d['harmed']" title="Most often named as harmed" note="Alleged harmed parties, as recorded" unit="incidents" />@endif
                @if($d['sectors']['coded'] >= 30)<x-site.rank-bars :series="collect($d['sectors']['top'])->mapWithKeys(fn ($v, $k) => [ucfirst($k) => $v])->all()" title="Sector of deployment, where coded" :note="number_format($d['sectors']['coded']).' incidents with a sector code'" unit="incidents" />@endif
            </div>
        </section>

        <section class="mt-12" aria-labelledby="board-heading">
            <div class="rule-strong pt-3"><h2 id="board-heading" class="section-title">6. Where to look first</h2></div>
            <p class="mt-2 max-w-[72ch] text-brand-body leading-7">The questions a risk owner brings to this record, answered from the counts above. Each answer describes the record, not your exposure: use it to choose which subdomain to open.</p>
            @if($findings)
            <div class="mt-4 grid gap-4 md:grid-cols-2" data-attack-findings>
                @foreach($findings as $f)
                <div class="panel-tinted">
                    <h3 class="font-semibold text-brand-navy">{{ $f['question'] }}</h3>
                    <p class="mt-1 text-sm leading-6 text-brand-body">{{ trim($f['answer']) }}</p>
                    @if($f['href'])<p class="mt-2 text-sm"><a href="{{ $f['href'] }}">{{ $f['cta'] }}: definition, frameworks and incidents</a></p>@endif
                </div>
                @endforeach
            </div>
            @endif
            <h3 class="mt-8 font-semibold text-brand-navy">The board: one card for each kind of incident</h3>
            <p class="mt-1 max-w-[72ch] text-sm leading-6 text-brand-body">Every kind with at least {{ AttackLandscape::VECTOR_MIN }} records in view, busiest first. The label on each card is worked out from two counts, by the rules under the cards.</p>
            @if($d['vectors'])
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($d['vectors'] as $v)
                @php($peak = max(1, max($v['years'])))
                <a href="{{ $v['url'] }}" class="card-link block p-4 no-underline" data-signal="{{ $v['signal'] }}">
                    <div class="flex items-start justify-between gap-3">
                        <p class="eyebrow">{{ $v['id'] }} · {{ $v['domain_name'] }}</p>
                        <span class="inline-flex shrink-0 items-center gap-1.5 text-xs font-semibold text-brand-navy"><svg width="8" height="8" aria-hidden="true"><circle cx="4" cy="4" r="4" fill="{{ $signalTone[$v['signal']] }}" /></svg>{{ AttackLandscape::SIGNALS[$v['signal']]['label'] }}</span>
                    </div>
                    <p class="mt-1 font-semibold leading-snug text-brand-navy">{{ $v['name'] }}</p>
                    <svg viewBox="0 0 336 46" role="img" aria-label="Incidents per year, {{ array_key_first($v['years']) }} to {{ array_key_last($v['years']) }}: {{ implode(', ', $v['years']) }}" class="mt-3 w-full h-auto">
                        @foreach($v['years'] as $year => $n)
                        <rect x="{{ $loop->index * 42 }}" y="{{ 32 - max(1, round(30 * $n / $peak)) }}" width="38" height="{{ max(1, round(30 * $n / $peak)) }}" fill="{{ $loop->last ? '#5D6B7E' : '#002147' }}" class="chart-bar" data-tip="{{ $year }}{{ $loop->last ? ' (part year)' : '' }}: {{ $n }}" />
                        @endforeach
                        <text x="0" y="44" font-size="10" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ array_key_first($v['years']) }}</text>
                        <text x="332" y="44" font-size="10" text-anchor="end" fill="#5D6B7E" font-family="ui-monospace, monospace">{{ array_key_last($v['years']) }}, part year</text>
                    </svg>
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-xs text-brand-muted">
                        <div><dt>{{ $to ? 'Year to '.Carbon::parse($d['window']['through'].'-01')->format('M Y') : 'Last 12 months' }}</dt><dd class="tabular-nums text-base text-brand-navy">{{ number_format($v['last12']) }}</dd><dd class="tabular-nums">{{ $v['growth'] === null ? 'from 0' : ($v['growth'] > 0 ? '+' : '').$v['growth'].'% (from '.$v['prev12'].')' }}</dd></div>
                        <div><dt>All years</dt><dd class="tabular-nums text-base text-brand-navy">{{ number_format($v['total']) }}</dd><dd class="tabular-nums">{{ AttackLandscape::pct($v['incident_share']) }}% of view</dd></div>
                        <div><dt>Deliberate</dt><dd class="tabular-nums text-base text-brand-navy">{{ $v['intentional_share'] === null ? '—' : $v['intentional_share'].'%' }}</dd><dd class="tabular-nums">{{ AttackLandscape::pct($v['risk_share']) }}% of risk entries</dd></div>
                    </dl>
                    <p class="mt-3 border-t border-brand-line pt-2 text-xs text-brand-muted">{{ $v['instruments'] ? number_format($v['instruments']).' instruments on record for this risk domain' : 'No instrument on record for this risk domain' }}</p>
                </a>
                @endforeach
            </div>
            <dl class="mt-4 grid gap-x-6 gap-y-1 text-xs text-brand-muted sm:grid-cols-2">
                @foreach(AttackLandscape::SIGNALS as $key => $s)
                <div class="flex items-baseline gap-2"><dt class="inline-flex shrink-0 items-baseline gap-1.5 font-semibold text-brand-navy"><svg width="8" height="8" aria-hidden="true"><circle cx="4" cy="4" r="4" fill="{{ $signalTone[$key] }}" /></svg>{{ $s['label'] }}</dt><dd>{{ $s['rule'] }}.</dd></div>
                @endforeach
            </dl>
            @else
            <x-site.empty class="mt-4" title="No kind of incident has enough records in view" />
            @endif

        </section>

        <section class="mt-12" aria-labelledby="policy-heading">
            <div class="rule-strong pt-3"><h2 id="policy-heading" class="section-title">7. What the rules on record cover</h2></div>
            <p class="mt-2 max-w-[72ch] text-brand-body leading-7">The same seven risk domains twice: the incidents in view, and the policy instruments in this tracker whose recorded use cases fall in that domain. An instrument can count under more than one domain. A long bar on the left beside a short one on the right is a question to ask, not a finding that the rules are missing.</p>
            <div class="mt-4 grid gap-6 lg:grid-cols-2">
                <x-site.rank-bars :series="collect($d['domains'])->mapWithKeys(fn ($x) => [$x['id'].' '.$x['name'] => $x['incidents']])->all()" title="Incidents in view, by risk domain" note="Classified incidents only" unit="incidents" />
                <x-site.rank-bars :series="collect($d['domains'])->mapWithKeys(fn ($x) => [$x['id'].' '.$x['name'] => $x['instruments']])->all()" title="Instruments on record, by risk domain" note="Published instruments, by recorded use case" unit="instruments" />
            </div>
            <ul class="mt-4 flex flex-wrap gap-2 text-sm">
                <li><a class="chip" href="{{ route('tools.applicability') }}">Check which rules apply to you</a></li>
                <li><a class="chip" href="{{ route('obligations.index') }}">Obligations with dates</a></li>
                <li><a class="chip" href="{{ route('templates.show', 'ai-risk-register') }}">AI risk register template</a></li>
                <li><a class="chip" href="{{ route('risk.incidents.browse') }}">Browse the incidents</a></li>
                <li><a class="chip" href="{{ route('risk.index') }}">AI risk domains</a></li>
            </ul>
        </section>
    </div>

    <section class="mt-12" aria-labelledby="method-heading">
        <div class="rule-strong pt-3"><h2 id="method-heading" class="section-title">How to read this page</h2></div>
        <ul class="mt-3 max-w-[76ch] list-disc space-y-2 pl-5 text-sm leading-6 text-brand-body">
            <li><strong>These are reports, not a census.</strong> An incident is on record because someone wrote about it and an editor added it. Coverage leans toward English-language media, and a count is not a rate.</li>
            <li><strong>Classification trails the record.</strong> The codes for intent, entity and risk domain are added after an incident is recorded. A month is compared only when {{ AttackLandscape::CLASSIFIED_SHARE }}% of its incidents carry a code, which is why the year-on-year figures here end before the newest incident and differ from the calendar figure on <a href="{{ route('risk.index') }}">AI risk</a>.</li>
            <li><strong>"Deliberate" is a code, not a verdict.</strong> It is the MIT causal taxonomy's "Intentional": the outcome was the expected result of pursuing a goal. It covers fraud and impersonation as well as attacks on AI systems themselves.</li>
            <li><strong>Risk entries are counted the same way as the incidents in view.</strong> For "Deliberate misuse" only entries coded Intentional are counted; for "Security and malicious use" only entries in domains 2 and 4.</li>
            <li><strong>Updates.</strong> Incidents are synced from the AI Incident Database once a day and from its weekly export. Nothing on this page is fetched from the source while you read it.</li>
        </ul>
    </section>

    <x-site.attribution class="mt-12" :name="$aiid['source'] ?? 'AI Incident Database'" :url="$aiid['source_url'] ?? 'https://incidentdatabase.ai/'" :license="$aiid['license'] ?? 'CC BY-SA 4.0'" :licenseUrl="$aiid['license_url'] ?? 'https://creativecommons.org/licenses/by-sa/4.0/'" :citation="$aiid['citation'] ?? null" :date="$aiid['snapshot_date'] ?? null" note="Counts only; report texts are not reproduced." />
    <x-site.attribution class="mt-3" :name="$mit['source'] ?? 'MIT AI Risk Repository'" :url="$mit['source_url'] ?? 'https://airisk.mit.edu/'" :license="$mit['license'] ?? 'CC BY 4.0'" :licenseUrl="$mit['license_url'] ?? 'https://creativecommons.org/licenses/by/4.0/'" :citation="$mit['citation'] ?? null" :note="$mit['changes_note'] ?? null" />
    <x-site.disclaimer class="mt-6" />
</div>
@endsection
