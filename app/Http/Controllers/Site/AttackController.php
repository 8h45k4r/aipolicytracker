<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\ExternalData\AttackLandscape;
use App\Services\ExternalData\ExternalDataset;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * /ai-attacks: recorded AI incidents as a set of charts, with no list of records. The
 * lists live on /ai-risk/incidents and /ai-risk/risks; this page is for reading the
 * shape of the record and deciding where to look first.
 */
class AttackController extends Controller
{
    public function __construct(private readonly AttackLandscape $landscape, private readonly ExternalDataset $data) {}

    public function index(Request $request): View
    {
        $lens = AttackLandscape::lens($request->query('lens'));
        $d = $this->landscape->build($lens);
        $findings = AttackLandscape::findings($d);
        $modified = $d['fresh']['as_of'] ? Carbon::parse($d['fresh']['as_of']) : null;
        $aiid = $this->data->aiid();
        $mit = $this->data->mitRisk();

        $seo = Seo::make(
            'AI Attacks and Incidents: Threat Landscape in Charts',
            'Recorded AI incidents in charts: deliberate misuse, fraud and security, how the record compares with the risks frameworks describe, and where to look first.',
            route('attacks.index', $lens === 'all' ? [] : ['lens' => $lens])
        )->withBreadcrumbs([['Home', route('home')], ['AI risk', route('risk.index')], ['AI attacks', route('attacks.index')]])
            ->withModified($modified);

        // One address is offered to search engines: the page without a lens. A lens, or
        // any other query string, is the same page seen another way.
        if ($lens !== 'all' || $request->query()) {
            $seo->noindex();
        } else {
            $seo->withJsonLd(array_filter([
                '@type' => 'Dataset',
                'name' => 'AI attacks and incidents: counts by intent, risk domain and year',
                'description' => 'Counts of incidents in the AI Incident Database by intent, responsible entity, MIT risk domain and subdomain, month and year, set against the number of risk entries in the MIT AI Risk Repository coded the same way. Year-on-year comparisons end at the last month in which most incidents are classified.',
                'url' => route('attacks.index'),
                'license' => $aiid['license_url'] ?? null,
                'isBasedOn' => array_values(array_filter([$aiid['source_url'] ?? null, $mit['source_url'] ?? null])),
                'dateModified' => $modified?->toDateString(),
                'isAccessibleForFree' => true,
                'keywords' => ['AI attacks', 'AI incidents', 'AI security', 'AI risk'],
            ], fn ($v) => $v !== null && $v !== '' && $v !== []))
                ->withFaq(array_map(fn ($f) => ['question' => $f['question'], 'answer' => trim($f['answer'])], $findings));
        }

        return view('site.risk.attacks', [
            'seo' => $seo, 'd' => $d, 'lens' => $lens, 'findings' => $findings,
            'answer' => self::answer($d), 'aiid' => $aiid, 'mit' => $mit, 'version' => $this->landscape->version(),
        ]);
    }

    /** What the open page asks every few minutes, to know whether to draw itself again. */
    public function pulse(): JsonResponse
    {
        return response()->json(['version' => $this->landscape->version()], 200, ['X-Robots-Tag' => 'noindex', 'Cache-Control' => 'public, max-age=60']);
    }

    /**
     * The page in one paragraph, from the same figures as the charts.
     *
     * @param  array<string, mixed>  $d
     */
    public static function answer(array $d): string
    {
        $t = $d['totals'];
        $to = $d['window']['through'] ? Carbon::parse($d['window']['through'].'-01')->format('F Y') : null;
        $change = match (true) {
            $t['growth'] === null => '',
            $t['trend'] === 'flat' => sprintf(', level with the %s of the 12 months before', number_format($t['prev12'])),
            default => sprintf(', %s %d%% on the %s of the 12 months before', $t['growth'] > 0 ? 'up' : 'down', abs($t['growth']), number_format($t['prev12'])),
        };

        return implode(' ', array_filter([
            match ($d['lens']) {
                'deliberate' => sprintf('%s of the %s classified incidents in the AI Incident Database (%d%%) are coded as deliberate: the outcome was the expected result of pursuing a goal.', number_format($t['lens']), number_format($t['classified_corpus']), $t['share']),
                'security' => sprintf('%s of the %s classified incidents in the AI Incident Database (%d%%) fall under the two MIT risk domains for security and malicious use.', number_format($t['lens']), number_format($t['classified_corpus']), $t['share']),
                default => sprintf('The AI Incident Database records %s incidents, %s of them classified with the MIT risk taxonomy.', number_format($t['corpus']), number_format($t['classified_corpus'])),
            },
            $to ? sprintf('%s are dated in the 12 months to %s%s.', number_format($t['last12']), $to, $change) : null,
            $d['window']['unclassified_since'] ? sprintf('%s newer incidents are recorded but not yet classified, so comparisons stop at %s.', number_format($d['window']['unclassified_since']), $to) : null,
        ]));
    }
}
