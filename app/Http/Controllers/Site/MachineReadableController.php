<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * llms.txt, llms-full.txt and the OpenAPI document for the public API.
 * These are discoverability aids for AI assistants and crawlers; they do not
 * guarantee citation or ranking.
 */
class MachineReadableController extends Controller
{
    public function llms(): Response
    {
        $jurisdictions = Jurisdiction::published()->orderBy('name')->get()->filter->isIndexable();
        $policies = PolicyInstrument::published()->with('jurisdiction')->where('featured', true)->orderBy('title')->get();

        return $this->text(view('site.machine.llms', compact('jurisdictions', 'policies'))->render());
    }

    public function llmsFull(): Response
    {
        $jurisdictions = Jurisdiction::published()->orderBy('name')->get()->filter->isIndexable();
        $policies = PolicyInstrument::published()->with(['jurisdiction', 'deadlines'])->orderBy('title')->get()->filter->isIndexable();
        $obligations = Obligation::published()->with('policyInstrument')->orderBy('category')->orderBy('title')->get();
        $changes = ChangeEvent::published()->with('jurisdiction')->orderByDesc('occurred_on')->limit(100)->get();

        return $this->text(view('site.machine.llms-full', compact('jurisdictions', 'policies', 'obligations', 'changes'))->render());
    }

    public function openapi(): JsonResponse
    {
        $spec = require base_path('resources/openapi/openapi.php');

        return response()->json($spec, 200, ['Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function text(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
