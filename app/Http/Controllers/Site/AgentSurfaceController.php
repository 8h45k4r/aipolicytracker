<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Services\Completeness\CompletenessReport;
use App\Services\MachineReadable\BulkExport;
use App\Services\MachineReadable\RecordContext;
use App\Services\Reviewers\ReviewerRoster;
use App\Services\Verification\VerificationPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Surfaces built for something other than a browser: one Markdown file per
 * record, flat CSV and newline-delimited JSON exports of the whole corpus, and
 * the JSON Schemas at the URLs the schema files themselves claim.
 *
 * That last one was a defect, not a feature: every schema in data/schema
 * declares `$id: https://aipolicytracker.org/schema/<name>.schema.json` and
 * nothing was ever served there, so a validator resolving the identifier got a
 * 404. A test now asserts each schema is reachable at the URL it names itself.
 */
class AgentSurfaceController extends Controller
{
    public function policy(string $slug, RecordContext $context): Response
    {
        $record = PolicyInstrument::published()->with(['jurisdiction', 'obligations', 'deadlines'])->where('slug', $slug)->firstOrFail();

        return $this->markdown($context->render($record), $slug);
    }

    public function jurisdiction(string $slug, RecordContext $context): Response
    {
        $record = Jurisdiction::published()->where('slug', $slug)->firstOrFail();

        return $this->markdown($context->render($record), $slug);
    }

    public function obligation(string $slug, RecordContext $context): Response
    {
        $record = Obligation::published()->with('policyInstrument.jurisdiction')->where('slug', $slug)->firstOrFail();

        return $this->markdown($context->render($record), $slug);
    }

    public function change(string $slug, RecordContext $context): Response
    {
        $record = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])->where('slug', $slug)->firstOrFail();

        return $this->markdown($context->render($record), $slug);
    }

    /** One JSON Schema, served at the URL its own `$id` declares. */
    public function schema(string $name): Response
    {
        // The route already constrains the name, but the corpus of schemas is a fixed set of
        // files on disk and a path is never built from user input beyond this basename.
        $path = base_path('data/schema/'.$name.'.schema.json');
        abort_unless(is_file($path), 404);

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'application/schema+json',
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function exportCsv(string $dataset, BulkExport $export): StreamedResponse
    {
        abort_unless($export->exists($dataset), 404);

        return response()->streamDownload(function () use ($dataset, $export) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $export->columns($dataset));
            $export->each($dataset, function (array $row) use ($handle) {
                fputcsv($handle, array_map(fn ($v) => match (true) {
                    is_bool($v) => $v ? 'true' : 'false',
                    // CSV has no nesting, so a list cell carries JSON rather than losing
                    // the links inside it.
                    is_array($v) => json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    default => $v,
                }, $row));
            });
            fclose($handle);
        }, 'aipolicytracker-'.$dataset.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'public, max-age=900',
        ]);
    }

    /** Newline-delimited JSON: one self-contained record per line, streamable. */
    public function exportNdjson(string $dataset, BulkExport $export): StreamedResponse
    {
        abort_unless($export->exists($dataset), 404);

        return response()->stream(function () use ($dataset, $export) {
            $export->each($dataset, function (array $row) {
                echo json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
            });
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="aipolicytracker-'.$dataset.'.ndjson"',
            'Cache-Control' => 'public, max-age=900',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * How far the corpus can be trusted, in one document.
     *
     * The three quality policies already publish this as pages a person reads.
     * A caller deciding whether to act on a record needs the same numbers before
     * it quotes one, and asking it to scrape three HTML pages for them is how a
     * caller ends up not asking at all.
     */
    public function health(VerificationPolicy $verification, CompletenessReport $completeness, ReviewerRoster $roster): JsonResponse
    {
        $freshness = $verification->report();
        $coverage = $completeness->report();
        $standing = $roster->standing();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'records_published' => $coverage['records'],
            'freshness' => [
                'covered' => $freshness['covered'],
                'overdue' => $freshness['overdue'],
                'critical_overdue' => $freshness['critical_overdue'],
                'never_verified' => $freshness['never'],
                'budget' => $freshness['budget'],
                'pass' => $freshness['pass'],
                'policy_url' => route('verification'),
            ],
            'completeness' => [
                'complete' => $coverage['complete'],
                'incomplete' => $coverage['incomplete'],
                'required_gaps' => $coverage['required_gaps'],
                'expected_gaps' => $coverage['expected_gaps'],
                'budget' => $coverage['budget'],
                'pass' => $coverage['pass'],
                'policy_url' => route('coverage'),
                'queue_url' => route('gaps'),
            ],
            'review' => [
                'verified_by_a_named_reviewer' => $standing['verified'],
                'reviewers_on_the_roster' => $standing['reviewers'],
                'verified_by_an_unlisted_name' => $standing['unattributed'],
                'roster_url' => route('reviewers'),
            ],
            'notice' => 'Records are structured summaries with links to official texts, not legal advice. A record whose review status is not "verified" has not been confirmed against its official source by a named reviewer.',
            'licence' => config('aipolicytracker.data_license_url'),
        ], 200, ['Cache-Control' => 'public, max-age=900', 'Access-Control-Allow-Origin' => '*'], JSON_UNESCAPED_SLASHES);
    }

    private function markdown(string $body, string $slug): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$slug.'.md"',
            'Cache-Control' => 'public, max-age=900',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
