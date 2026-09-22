<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Services\PolicyData\FrameworkCrosswalk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * llms.txt, llms-full.txt and the OpenAPI document for the public API.
 * These are discoverability aids for answer engines and crawlers; they do not
 * guarantee citation or ranking.
 */
class MachineReadableController extends Controller
{
    public function llms(): Response
    {
        $jurisdictions = Jurisdiction::published()->withPublishedInstrument()->orderBy('name')->get()->filter->isIndexable();
        $policies = PolicyInstrument::published()->with('jurisdiction')->where('featured', true)->orderBy('title')->get();
        $crosswalks = $this->crosswalkIndex();

        return $this->text(view('site.machine.llms', compact('jurisdictions', 'policies', 'crosswalks'))->render());
    }

    public function llmsFull(): Response
    {
        $jurisdictions = Jurisdiction::published()->withPublishedInstrument()->orderBy('name')->get()->filter->isIndexable();
        $policies = PolicyInstrument::published()->with(['jurisdiction', 'deadlines'])->orderBy('title')->get()->filter->isIndexable();
        $obligations = Obligation::published()->with('policyInstrument')->orderBy('category')->orderBy('title')->get();
        $changes = ChangeEvent::published()->with('jurisdiction')->orderByDesc('occurred_on')->limit(100)->get();
        $controls = Control::published()->with(['evidence', 'obligations'])->orderBy('title')->get();

        return $this->text(view('site.machine.llms-full', compact('jurisdictions', 'policies', 'obligations', 'changes', 'controls'))->render());
    }

    /**
     * The law-to-standard crosswalks worth an agent's attention: frameworks that carry
     * enough mapped duties to be indexed, and the jurisdiction pairs that do the same.
     *
     * @return list<array<string, mixed>>
     */
    private function crosswalkIndex(): array
    {
        $crosswalk = app(FrameworkCrosswalk::class);

        return $crosswalk->summary()
            ->filter(fn (array $f) => $f['obligations'] >= FrameworkCrosswalk::MIN_INDEXABLE_OBLIGATIONS)
            ->map(function (array $f) use ($crosswalk) {
                $pairs = $crosswalk->jurisdictionsFor($f['key'])
                    ->filter(fn (array $row) => $row['rows'] >= FrameworkCrosswalk::MIN_INDEXABLE_ROWS)
                    ->map(fn (array $row) => [
                        'name' => $row['jurisdiction']->name.' AI rules mapped to '.$f['short'],
                        'url' => route('frameworks.crosswalk', [$f['slug'], $row['jurisdiction']->slug]),
                        'mapped' => $row['rows'],
                        'recorded' => $crosswalk->crosswalk($f['key'], $row['jurisdiction'])['total_obligations'],
                    ])->values()->all();

                return [
                    'name' => $f['name'],
                    'url' => route('frameworks.show', $f['slug']),
                    'obligations' => $f['obligations'],
                    'jurisdictions' => $f['jurisdictions'],
                    'pairs' => $pairs,
                ];
            })->values()->all();
    }

    public function openapi(): JsonResponse
    {
        $spec = require base_path('resources/openapi/openapi.php');

        return response()->json($spec, 200, ['Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * RFC 9116 security.txt. The address is the project's official mailbox, the
     * same one on the About page and in the Organization structured data, so a
     * researcher and a reader are pointed at the same person. Expires is set a
     * year out from each request, because a static file with a fixed date goes
     * stale and a stale security.txt is treated as absent.
     */
    public function securityTxt(): Response
    {
        $lines = [
            'Contact: mailto:'.config('aipolicytracker.contact_email'),
            'Contact: '.config('aipolicytracker.github_url').'/security/advisories/new',
            'Expires: '.now()->addYear()->startOfDay()->format('Y-m-d\TH:i:s\Z'),
            'Preferred-Languages: en',
            'Canonical: '.route('security.txt'),
            'Policy: '.config('aipolicytracker.github_url').'/blob/main/SECURITY.md',
            'Acknowledgments: '.route('about'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=86400']);
    }

    private function text(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
