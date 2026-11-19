<?php

namespace App\Services\ExternalData;

use App\Http\Controllers\Site\RiskController;
use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Models\PolicyInstrument;
use App\Support\ContentCache;
use App\Support\RiskTaxonomy;
use Illuminate\Support\Carbon;

/**
 * The figures behind /ai-attacks: recorded incidents (AI Incident Database) set against
 * the risks that frameworks describe (MIT AI Risk Repository) and the instruments on
 * record here, seen through one of three lenses.
 *
 * Everything is a count of records that already exist in the two imported tables. No
 * record is scored, ranked or re-classified: the lenses select on the classification the
 * AI Incident Database applies, and each signal is a threshold on two counts that the
 * page prints beside it.
 */
class AttackLandscape
{
    /**
     * A lens selects incidents and, for like-for-like comparison, the risk entries with
     * the same coding. "Deliberate" is the MIT causal taxonomy's "Intentional": the
     * outcome was the expected result of pursuing a goal.
     */
    public const LENSES = [
        'all' => [
            'label' => 'All incidents',
            'noun' => 'recorded AI incidents',
            'definition' => 'Every incident in the AI Incident Database.',
        ],
        'deliberate' => [
            'label' => 'Deliberate misuse',
            'noun' => 'incidents coded as deliberate',
            'definition' => 'Incidents coded "Intentional" in the MIT causal taxonomy: the outcome was the expected result of someone, or something, pursuing a goal.',
        ],
        'security' => [
            'label' => 'Security and malicious use',
            'noun' => 'incidents under the security and malicious-use domains',
            'definition' => 'Incidents classified under MIT domain 2 (Privacy & Security) or domain 4 (Malicious Actors & Misuse): privacy compromise, attacks on AI systems, fraud and scams, disinformation and surveillance, cyberattacks and weapons.',
        ],
    ];

    /** MIT domains that make up the security lens. */
    public const SECURITY_DOMAINS = ['2', '4'];

    /** Months shown in the monthly chart. */
    public const PULSE_MONTHS = 36;

    /** Harmed groups shown in the matrix of who is harmed by which kind of risk. */
    public const HARM_GROUPS = 8;

    /** Days without a new record from the daily sync before the page says the sync has stalled. */
    public const STALE_AFTER_DAYS = 3;

    /** Colour of each MIT risk domain, used by every chart on the page that shows domains. */
    public const DOMAIN_COLOURS = ['1' => '#A4366B', '2' => '#00738A', '3' => '#B45309', '4' => '#9B1C2E', '5' => '#5145CD', '6' => '#2F7D32', '7' => '#44546A'];

    /** Calendar years shown in the heat table and the small charts on each vector. */
    public const YEARS = 8;

    /** A vector is shown on the decision board once it has this many incidents in the lens. */
    public const VECTOR_MIN = 10;

    /** Incidents in the last 12 months before a rise or fall is called a direction. */
    public const SIGNAL_MIN = 10;

    /** Percent of a month's incidents that must be classified for the month to count as settled. */
    public const CLASSIFIED_SHARE = 75;

    /** Percent change on the previous 12 months that counts as rising or easing. */
    public const SIGNAL_CHANGE = 25;

    public const SIGNALS = [
        'rising' => ['label' => 'Rising', 'rule' => 'at least '.self::SIGNAL_MIN.' incidents in the last 12 months and up '.self::SIGNAL_CHANGE.'% or more on the 12 before'],
        'steady' => ['label' => 'Steady', 'rule' => 'at least '.self::SIGNAL_MIN.' incidents in the last 12 months and within '.self::SIGNAL_CHANGE.'% of the 12 before'],
        'easing' => ['label' => 'Easing', 'rule' => 'at least '.self::SIGNAL_MIN.' incidents in the 12 months before and down '.self::SIGNAL_CHANGE.'% or more since'],
        'emerging' => ['label' => 'Emerging', 'rule' => 'fewer than '.self::SIGNAL_MIN.' incidents in the last 12 months, at least three, and more than in the 12 before'],
        'sparse' => ['label' => 'Sparse', 'rule' => 'too few recent incidents to state a direction'],
    ];

    public function __construct(private readonly ExternalDataset $data) {}

    public static function lens(?string $lens): string
    {
        return isset(self::LENSES[$lens]) ? $lens : 'all';
    }

    /** What the page compares against to know that the data has moved. */
    public function version(): string
    {
        return substr(sha1(ExternalIncident::count().'|'.ExternalIncident::max('updated_at').'|'.ExternalRisk::count()), 0, 12);
    }

    /** @return array<string, mixed> */
    public function build(string $lens): array
    {
        $lens = self::lens($lens);

        return ContentCache::remember('attack-landscape-v2.'.$lens.'.'.$this->version().'.'.now()->format('Ymd'), 3600, fn () => $this->compute($lens));
    }

    /** @return array<string, mixed> */
    private function compute(string $lens): array
    {
        $mit = $this->data->mitRisk();
        $aiid = $this->data->aiid();

        // Taxonomy lookups. The incident table carries the names, the risk table the codes.
        $domainByLabel = [];
        $subByName = [];
        $subs = [];
        foreach ($mit['domains'] ?? [] as $d) {
            $domainByLabel[$d['aiid_domain_label']] = ['id' => (string) $d['id'], 'name' => $d['name']];
            foreach ($d['subdomains'] ?? [] as $s) {
                $subs[$s['id']] = ['id' => $s['id'], 'name' => trim($s['name']), 'domain' => (string) $d['id'], 'domain_name' => $d['name'], 'url' => RiskTaxonomy::subdomainUrl($d['id'], $s['id'])];
                $subByName[mb_strtolower(trim($s['name']))] = $s['id'];
            }
        }

        $inLens = match ($lens) {
            'deliberate' => fn (array $r) => $r['intent'] === 'Intentional',
            'security' => fn (array $r) => in_array($r['domain'], self::SECURITY_DOMAINS, true),
            default => fn (array $r) => true,
        };

        // One pass over the table: every incident, with its codes resolved.
        $today = now()->toDateString();
        $all = [];
        $perMonth = [];
        $classifiedCorpus = 0;
        $latest = null;
        foreach (ExternalIncident::query()->toBase()->get(['occurred_on', 'year', 'mit_domain', 'mit_subdomain', 'entity', 'intent', 'timing', 'harmed', 'sectors']) as $i) {
            $date = $i->occurred_on ? substr((string) $i->occurred_on, 0, 10) : null;
            $row = [
                'date' => $date, 'year' => $i->year ? (int) $i->year : null,
                'domain' => $domainByLabel[$i->mit_domain]['id'] ?? null,
                'sub' => $subByName[mb_strtolower(trim((string) $i->mit_subdomain))] ?? null,
                'entity' => $i->entity ?: null, 'intent' => $i->intent ?: null, 'timing' => $i->timing ?: null,
                'harmed' => $i->harmed, 'sectors' => $i->sectors,
            ];
            $all[] = $row;
            $classifiedCorpus += $row['entity'] !== null ? 1 : 0;
            if ($date !== null && $date <= $today) {
                $latest = $latest === null || $date > $latest ? $date : $latest;
                $perMonth[substr($date, 0, 7)] ??= ['all' => 0, 'classified' => 0];
                $perMonth[substr($date, 0, 7)]['all']++;
                $perMonth[substr($date, 0, 7)]['classified'] += $row['entity'] !== null ? 1 : 0;
            }
        }
        $corpus = count($all);

        // Classification trails the record: the newest incidents carry no codes yet. Every
        // comparison of one year with the one before ends at the last month in which most
        // incidents are classified, or each vector would appear to be falling.
        krsort($perMonth);
        $through = null;
        $unclassifiedSince = 0;
        foreach ($perMonth as $month => $n) {
            if (self::CLASSIFIED_SHARE * $n['all'] <= $n['classified'] * 100) {
                $through = $month;
                break;
            }
            $unclassifiedSince += $n['all'] - $n['classified'];
        }
        $end = Carbon::parse(($through ?? substr($today, 0, 7)).'-01')->endOfMonth();
        $unclassifiedSince = $through === null ? 0 : $unclassifiedSince;
        $d0 = $end->toDateString();
        $d12 = $end->copy()->startOfMonth()->subMonths(11)->subDay()->toDateString();
        $d24 = $end->copy()->startOfMonth()->subMonths(23)->subDay()->toDateString();

        $lastMonth = Carbon::parse(substr($latest ?? $today, 0, 7).'-01');
        $months = [];
        for ($i = self::PULSE_MONTHS - 1; $i >= 0; $i--) {
            $key = $lastMonth->copy()->subMonths($i)->format('Y-m');
            $months[$key] = ['all' => $perMonth[$key]['all'] ?? 0, 'lens' => 0, 'settled' => $through !== null && $key <= $through];
        }

        $rows = [];
        foreach ($all as $row) {
            // The lens "all" is charted as the classified part of the record, so the
            // monthly chart shows where classification stops.
            $counts = $lens === 'all' ? $row['entity'] !== null : $inLens($row);
            $month = $row['date'] ? substr($row['date'], 0, 7) : null;
            if ($counts && $month !== null && isset($months[$month])) {
                $months[$month]['lens']++;
            }
            if ($inLens($row)) {
                $rows[] = $row;
            }
        }

        $total = count($rows);
        $window = fn (array $r) => $r['date'] === null || $r['date'] > $d0 ? null : ($r['date'] > $d12 ? 'last' : ($r['date'] > $d24 ? 'prev' : null));
        $last12 = 0;
        $prev12 = 0;
        $classified = 0;
        $coded = ['entity' => [], 'intent' => [], 'timing' => []];
        $recentIntent = ['last' => [], 'prev' => []];
        $flowA = [];
        $flowB = [];
        $bySub = [];
        $byDomain = [];
        $harmed = [];
        $harmDomain = [];
        $intentByYear = [];
        $sectors = [];
        $sectorCoded = 0;
        $maxYear = (int) substr($latest ?? $today, 0, 4);
        $years = range($maxYear - self::YEARS + 1, $maxYear);

        foreach ($rows as $r) {
            $w = $window($r);
            $last12 += $w === 'last' ? 1 : 0;
            $prev12 += $w === 'prev' ? 1 : 0;
            if ($r['entity'] !== null) {
                $classified++;
                foreach (['entity', 'intent', 'timing'] as $k) {
                    if ($r[$k] !== null) {
                        $coded[$k][$r[$k]] = ($coded[$k][$r[$k]] ?? 0) + 1;
                    }
                }
                if ($r['intent'] !== null) {
                    $flowA[$r['entity']][$r['intent']] = ($flowA[$r['entity']][$r['intent']] ?? 0) + 1;
                    if ($r['year'] !== null && in_array($r['year'], $years, true)) {
                        $intentByYear[$r['year']][$r['intent']] = ($intentByYear[$r['year']][$r['intent']] ?? 0) + 1;
                    }
                    if ($w !== null) {
                        $recentIntent[$w][$r['intent']] = ($recentIntent[$w][$r['intent']] ?? 0) + 1;
                    }
                    if ($r['domain'] !== null) {
                        $flowB[$r['intent']][$r['domain']] = ($flowB[$r['intent']][$r['domain']] ?? 0) + 1;
                    }
                }
            }
            if ($r['domain'] !== null) {
                $byDomain[$r['domain']] ??= ['total' => 0, 'last12' => 0];
                $byDomain[$r['domain']]['total']++;
                $byDomain[$r['domain']]['last12'] += $w === 'last' ? 1 : 0;
            }
            if ($r['sub'] !== null) {
                $bySub[$r['sub']] ??= ['total' => 0, 'last12' => 0, 'prev12' => 0, 'intentional' => 0, 'intent_coded' => 0, 'years' => array_fill_keys($years, 0)];
                $bySub[$r['sub']]['total']++;
                $bySub[$r['sub']]['last12'] += $w === 'last' ? 1 : 0;
                $bySub[$r['sub']]['prev12'] += $w === 'prev' ? 1 : 0;
                $bySub[$r['sub']]['intent_coded'] += $r['intent'] !== null ? 1 : 0;
                $bySub[$r['sub']]['intentional'] += $r['intent'] === 'Intentional' ? 1 : 0;
                if ($r['year'] !== null && isset($bySub[$r['sub']]['years'][$r['year']])) {
                    $bySub[$r['sub']]['years'][$r['year']]++;
                }
            }
            foreach (self::names($r['harmed']) as $name) {
                $harmed[$name] = ($harmed[$name] ?? 0) + 1;
                if ($r['domain'] !== null) {
                    $harmDomain[$name][$r['domain']] = ($harmDomain[$name][$r['domain']] ?? 0) + 1;
                }
            }
            $s = self::names($r['sectors']);
            $sectorCoded += $s ? 1 : 0;
            foreach ($s as $name) {
                $sectors[$name] = ($sectors[$name] ?? 0) + 1;
            }
        }
        arsort($harmed);
        arsort($sectors);

        // The risk entries coded the same way, for like-for-like comparison.
        $riskQuery = fn () => match ($lens) {
            'deliberate' => ExternalRisk::query()->where('intent', 'Intentional'),
            'security' => ExternalRisk::query()->whereIn('domain', array_map('intval', self::SECURITY_DOMAINS)),
            default => ExternalRisk::query(),
        };
        $riskBySub = $riskQuery()->whereNotNull('subdomain')->selectRaw('subdomain, COUNT(*) as n')->groupBy('subdomain')->pluck('n', 'subdomain')->map(fn ($n) => (int) $n)->all();
        $riskTotal = array_sum($riskBySub);
        $riskCoded = [];
        foreach (['entity', 'intent', 'timing'] as $k) {
            $riskCoded[$k] = $riskQuery()->whereIn($k, ExternalRisk::CAUSAL[$k])->where($k, '!=', 'Not coded')->selectRaw("{$k} as k, COUNT(*) as n")->groupBy($k)->pluck('n', 'k')->map(fn ($n) => (int) $n)->all();
        }

        $scope = array_keys($lens === 'security' ? array_filter($subs, fn ($s) => in_array($s['domain'], self::SECURITY_DOMAINS, true)) : $subs);
        $subTotal = array_sum(array_column($bySub, 'total'));
        $points = [];
        foreach ($scope as $id) {
            $inc = $bySub[$id]['total'] ?? 0;
            $risk = $riskBySub[$id] ?? 0;
            if ($inc === 0 && $risk === 0) {
                continue;
            }
            $incShare = $subTotal ? round(100 * $inc / $subTotal, 1) : 0.0;
            $riskShare = $riskTotal ? round(100 * $risk / $riskTotal, 1) : 0.0;
            $points[] = $subs[$id] + ['incidents' => $inc, 'risks' => $risk, 'incident_share' => $incShare, 'risk_share' => $riskShare, 'gap' => round($incShare - $riskShare, 1), 'last12' => $bySub[$id]['last12'] ?? 0];
        }

        $instruments = [];
        foreach ($mit['domains'] ?? [] as $d) {
            $instruments[(string) $d['id']] = $d['use_cases'] ? PolicyInstrument::published()->withTerm('use_case', $d['use_cases'])->count() : 0;
        }

        $vectors = [];
        foreach ($bySub as $id => $v) {
            if ($v['total'] < self::VECTOR_MIN) {
                continue;
            }
            $growth = $v['prev12'] ? (int) round(100 * ($v['last12'] - $v['prev12']) / $v['prev12']) : null;
            $vectors[] = $subs[$id] + [
                'total' => $v['total'], 'last12' => $v['last12'], 'prev12' => $v['prev12'], 'growth' => $growth,
                'signal' => self::signal($v['last12'], $v['prev12'], $growth),
                'intentional_share' => $v['intent_coded'] ? (int) round(100 * $v['intentional'] / $v['intent_coded']) : null,
                'years' => $v['years'], 'instruments' => $instruments[$subs[$id]['domain']] ?? 0,
                'incident_share' => $subTotal ? round(100 * $v['total'] / $subTotal, 1) : 0.0,
                'risk_share' => $riskTotal ? round(100 * ($riskBySub[$id] ?? 0) / $riskTotal, 1) : 0.0,
            ];
        }
        usort($vectors, fn ($a, $b) => [$b['last12'], $b['total']] <=> [$a['last12'], $a['total']]);

        $heatRows = collect($bySub)->sortByDesc('total')->take(8);
        $heat = [];
        foreach ($heatRows as $id => $v) {
            $heat[$id.' '.$subs[$id]['name']] = $v['years'];
        }

        $domains = [];
        foreach ($mit['domains'] ?? [] as $d) {
            $id = (string) $d['id'];
            $domains[$id] = ['id' => $id, 'name' => $d['name'], 'url' => RiskTaxonomy::domainUrl($d['id']), 'incidents' => $byDomain[$id]['total'] ?? 0, 'last12' => $byDomain[$id]['last12'] ?? 0, 'instruments' => $instruments[$id] ?? 0];
        }

        // Who is harmed by which kind of risk: the groups named most often on classified
        // incidents, each with its incidents spread over the seven domains.
        $harmMatrix = collect($harmDomain)->map(fn ($row) => ['total' => array_sum($row), 'cells' => $row])
            ->sortByDesc('total')->take(self::HARM_GROUPS)->all();

        $intentYears = [];
        foreach ($years as $y) {
            $intentYears[$y] = ['Intentional' => $intentByYear[$y]['Intentional'] ?? 0, 'Unintentional' => $intentByYear[$y]['Unintentional'] ?? 0, 'Other' => $intentByYear[$y]['Other'] ?? 0];
        }

        $growth = $prev12 ? (int) round(100 * ($last12 - $prev12) / $prev12) : null;
        $synced = ExternalIncident::max('synced_at');
        $asOf = $synced ?: ($aiid['snapshot_date'] ?? null);

        return [
            'lens' => $lens,
            'totals' => [
                'lens' => $total, 'corpus' => $corpus, 'classified' => $classified, 'classified_corpus' => $classifiedCorpus,
                'share' => $lens === 'all' || ! $classifiedCorpus ? null : (int) round(100 * $total / $classifiedCorpus),
                'last12' => $last12, 'prev12' => $prev12, 'growth' => $growth, 'trend' => RiskController::trend($growth),
                'risks' => $riskTotal, 'frameworks' => count($this->data->mitRisksMeta()['papers'] ?? []),
            ],
            'months' => $months,
            'flow' => ['entity_intent' => $flowA, 'intent_domain' => $flowB, 'domains' => array_map(fn ($d) => $d['name'], $domains)],
            'heat' => ['years' => $years, 'rows' => $heat],
            'points' => $points,
            'rho' => count($points) >= 8 ? self::spearman(array_column($points, 'risks'), array_column($points, 'incidents')) : null,
            'causal' => ['incidents' => $coded, 'risks' => $riskCoded],
            'recent_intent' => $recentIntent,
            'harmed' => array_slice($harmed, 0, 8, true),
            'harm_matrix' => $harmMatrix,
            'intent_years' => $intentYears,
            'sectors' => ['coded' => $sectorCoded, 'top' => array_slice($sectors, 0, 8, true)],
            'vectors' => $vectors,
            'domains' => $domains,
            'window' => [
                'through' => $through, 'end' => $d0, 'last_from' => Carbon::parse($d12)->addDay()->toDateString(),
                'prev_from' => Carbon::parse($d24)->addDay()->toDateString(), 'unclassified_since' => $unclassifiedSince,
            ],
            'fresh' => [
                'as_of' => $asOf ? Carbon::parse($asOf)->toIso8601String() : null,
                'live' => (bool) $synced,
                // Whole days since the newest record arrived from the live sync.
                'days' => $synced ? (int) Carbon::parse($synced)->startOfDay()->diffInDays(now()->startOfDay()) : null,
                'latest_incident' => $latest,
                'snapshot' => $aiid['snapshot_date'] ?? null,
            ],
        ];
    }

    /**
     * The questions a reader comes with, answered from the figures above. Each answer
     * is a sentence built from counts on the page; none adds a judgement of its own.
     *
     * @param  array<string, mixed>  $d  the result of build()
     * @return list<array{question: string, answer: string, href: ?string, cta: ?string}>
     */
    public static function findings(array $d): array
    {
        $noun = self::LENSES[$d['lens']]['noun'];
        $to = $d['window']['through'] ? Carbon::parse($d['window']['through'].'-01')->format('F Y') : null;
        $period = $to ? 'the 12 months to '.$to : 'the last 12 months';
        $short = fn (array $v) => $v['id'].' '.$v['name'];
        $out = [];

        $top = $d['vectors'][0] ?? null;
        if ($top && $top['last12'] > 0 && $d['totals']['last12'] > 0) {
            $out[] = [
                'question' => 'Which kind of incident is recorded most?',
                'answer' => sprintf('%s: %s of the %s %s dated in %s (%d%%).', $short($top), number_format($top['last12']), number_format($d['totals']['last12']), $noun, $period, round(100 * $top['last12'] / $d['totals']['last12'])),
                'href' => $top['url'], 'cta' => 'Open '.$top['id'],
            ];
        }

        $movers = array_values(array_filter($d['vectors'], fn ($v) => in_array($v['signal'], ['rising', 'emerging'], true) && $v['growth'] !== null));
        usort($movers, fn ($a, $b) => $b['growth'] <=> $a['growth']);
        $out[] = $movers
            ? [
                'question' => 'Which is growing fastest?',
                'answer' => sprintf('%s: %d incidents in %s, against %d in the 12 months before. %s', $short($movers[0]), $movers[0]['last12'], $period, $movers[0]['prev12'], $movers[0]['last12'] < self::SIGNAL_MIN ? 'The counts are small, so read it as a signal to watch and not as a rate.' : ''),
                'href' => $movers[0]['url'], 'cta' => 'Open '.$movers[0]['id'],
            ]
            : [
                'question' => 'Which is growing fastest?',
                'answer' => sprintf('None. No kind of incident with at least %d records rose by %d%% or more in %s.', self::VECTOR_MIN, self::SIGNAL_CHANGE, $period),
                'href' => null, 'cta' => null,
            ];

        $byGap = $d['points'];
        usort($byGap, fn ($a, $b) => $b['gap'] <=> $a['gap']);
        $over = $byGap[0] ?? null;
        $under = $byGap ? end($byGap) : null;
        if ($over && $over['gap'] > 0) {
            $out[] = [
                'question' => 'What is recorded more often than the frameworks describe?',
                'answer' => sprintf('%s is %s%% of the classified incidents here and %s%% of the %s matching risk entries in the MIT AI Risk Repository.', $short($over), self::pct($over['incident_share']), self::pct($over['risk_share']), number_format($d['totals']['risks'])),
                'href' => $over['url'], 'cta' => 'Open '.$over['id'],
            ];
        }
        if ($under && $under['gap'] < 0) {
            $out[] = [
                'question' => 'What is described far more than it is recorded?',
                'answer' => sprintf('%s: %s%% of the risk entries and %s%% of the incidents (%d). A low count is not a low risk: a harm that has not happened yet, or is not reported, leaves no record.', $short($under), self::pct($under['risk_share']), self::pct($under['incident_share']), $under['incidents']),
                'href' => $under['url'], 'cta' => 'Open '.$under['id'],
            ];
        }

        $share = function (array $counts): ?array {
            $n = array_sum($counts);

            return $n ? [(int) round(100 * ($counts['Intentional'] ?? 0) / $n), $counts['Intentional'] ?? 0, $n] : null;
        };
        $last = $share($d['recent_intent']['last'] ?? []);
        $prev = $share($d['recent_intent']['prev'] ?? []);
        if ($d['lens'] !== 'deliberate' && $last) {
            $out[] = [
                'question' => 'Is deliberate misuse or accident the larger share?',
                'answer' => sprintf('In %s, %d%% of the classified incidents were coded deliberate (%s of %s)%s.', $period, $last[0], number_format($last[1]), number_format($last[2]), $prev ? sprintf(', against %d%% in the 12 months before', $prev[0]) : ''),
                'href' => null, 'cta' => null,
            ];
        }

        return $out;
    }

    /** A share as it is printed: one decimal below ten, none above. */
    public static function pct(float|int $share): string
    {
        return $share < 10 ? rtrim(rtrim(number_format($share, 1), '0'), '.') : (string) round($share);
    }

    /**
     * The direction of one vector, from two counts. The thresholds are public
     * constants and the page prints the rule next to the board.
     *
     * @return 'rising'|'steady'|'easing'|'emerging'|'sparse'
     */
    public static function signal(int $last12, int $prev12, ?int $growth): string
    {
        return match (true) {
            $last12 >= self::SIGNAL_MIN && $growth !== null && $growth >= self::SIGNAL_CHANGE => 'rising',
            $last12 >= self::SIGNAL_MIN && $growth === null => 'rising',
            $prev12 >= self::SIGNAL_MIN && $growth !== null && $growth <= -self::SIGNAL_CHANGE => 'easing',
            $last12 >= self::SIGNAL_MIN => 'steady',
            $last12 >= 3 && $last12 > $prev12 => 'emerging',
            default => 'sparse',
        };
    }

    /**
     * Spearman's rank correlation, with tied values sharing their average rank.
     *
     * @param  list<int|float>  $x
     * @param  list<int|float>  $y
     */
    public static function spearman(array $x, array $y): ?float
    {
        $n = count($x);
        if ($n < 3 || $n !== count($y)) {
            return null;
        }
        $rank = function (array $v): array {
            $order = $v;
            asort($order);
            $ranks = [];
            $keys = array_keys($order);
            for ($i = 0; $i < count($keys);) {
                $j = $i;
                while ($j + 1 < count($keys) && $order[$keys[$j + 1]] == $order[$keys[$i]]) {
                    $j++;
                }
                for ($k = $i; $k <= $j; $k++) {
                    $ranks[$keys[$k]] = ($i + $j) / 2 + 1;
                }
                $i = $j + 1;
            }

            return $ranks;
        };
        $rx = $rank(array_values($x));
        $ry = $rank(array_values($y));
        $mx = array_sum($rx) / $n;
        $my = array_sum($ry) / $n;
        $num = $dx = $dy = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $num += ($rx[$i] - $mx) * ($ry[$i] - $my);
            $dx += ($rx[$i] - $mx) ** 2;
            $dy += ($ry[$i] - $my) ** 2;
        }

        return $dx > 0 && $dy > 0 ? round($num / sqrt($dx * $dy), 2) : null;
    }

    /**
     * Names from one of the incident table's JSON lists. An element can itself be a
     * comma-joined list, so each is split.
     *
     * @return list<string>
     */
    private static function names(mixed $json): array
    {
        $list = is_string($json) ? json_decode($json, true) : $json;
        $out = [];
        foreach (is_array($list) ? $list : [] as $item) {
            foreach (explode(',', (string) (is_array($item) ? ($item['name'] ?? '') : $item)) as $part) {
                if (($part = trim($part)) !== '') {
                    $out[] = $part;
                }
            }
        }

        return array_values(array_unique($out));
    }
}
