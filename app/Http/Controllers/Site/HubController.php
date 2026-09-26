<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Services\Hubs\HubCatalog;
use App\Services\PolicyData\PolicyCatalog;
use App\Support\PageTitle;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * /ai-regulation-<country> is the jurisdiction page at its hub address;
 * /ai-regulation-<region> is a page over every jurisdiction in that region,
 * computed from the records: counts, binding law, latest changes, deadlines.
 */
class HubController extends Controller
{
    public function show(string $hub, PolicyCatalog $catalog, JurisdictionController $jurisdictions): View|RedirectResponse
    {
        if ($country = HubCatalog::countryFor($hub)) {
            $jurisdiction = Jurisdiction::where('slug', $country)->firstOrFail();

            return $jurisdictions->show($jurisdiction, $catalog);
        }
        $regionName = HubCatalog::regionFor($hub);
        abort_unless($regionName, 404);

        $members = Jurisdiction::published()->where('region', $regionName)->whereIn('jurisdiction_type', ['country', 'supranational'])
            ->withPublishedInstrument()
            ->withCount([
                'policyInstruments as instruments_count' => fn ($q) => $q->published(),
                'policyInstruments as binding_count' => fn ($q) => $q->published()->where('is_binding', true),
            ])
            ->orderBy('name')->get();
        $ids = $members->pluck('id');
        $policyIds = PolicyInstrument::published()->whereIn('jurisdiction_id', $ids)->pluck('id');
        $binding = PolicyInstrument::published()->with('jurisdiction')->whereIn('jurisdiction_id', $ids)->where('is_binding', true)->orderBy('applies_from')->orderBy('title')->get();
        $duties = Obligation::published()->whereIn('policy_instrument_id', $policyIds)->count();
        $changes = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])->whereIn('jurisdiction_id', $ids)->orderByDesc('occurred_on')->limit(8)->get();
        $deadlines = $catalog->upcomingDeadlines(8)->filter(fn ($d) => $ids->contains($d->policyInstrument?->jurisdiction_id))->values();
        $withInstruments = $members->filter(fn ($j) => $j->instruments_count > 0);
        $withBinding = $members->filter(fn ($j) => $j->binding_count > 0);
        $indexableMembers = $members->filter->isPageIndexable();
        $indexable = $indexableMembers->count() >= (int) config('hubs.min_region_countries', 3);
        $lastModified = collect([$members->max('updated_at'), $changes->max('updated_at')])->filter()->max();

        $answer = sprintf(
            '%s has %d recorded %s with AI policy on record, %d of them with at least one binding AI-specific instrument. Across the region this site records %d %s and %d %s, each linked to its official source. Most countries in the region regulate AI through strategies, guidance and existing law rather than a dedicated AI act; the table below shows which is which.',
            $regionName, $withInstruments->count(), Str::plural('jurisdiction', $withInstruments->count()), $withBinding->count(),
            $policyIds->count(), Str::plural('instrument', $policyIds->count()), $duties, Str::plural('recorded duty', $duties)
        );
        $faq = [
            ['question' => 'Which countries in '.$regionName.' have binding AI law?', 'answer' => $withBinding->isEmpty() ? 'None of the recorded jurisdictions in '.$regionName.' has an AI-specific binding instrument on record; AI is governed through existing law, strategies and guidance.' : $withBinding->pluck('name')->join(', ', ' and ').' each have at least one binding AI-specific instrument on record. The others govern AI through strategies, guidance and existing law.'],
            ['question' => 'How many AI policy instruments are recorded for '.$regionName.'?', 'answer' => $policyIds->count().' published instruments across '.$withInstruments->count().' jurisdictions, with '.$duties.' duties broken out from them. Every record links to its official source and states its verification status.'],
            ['question' => 'How current is this page?', 'answer' => 'It is computed from the records at every request. The latest change recorded in the region is dated '.($changes->first()?->occurred_on?->format('j F Y') ?? 'not yet recorded').'; the change log and the weekly digest carry each new one.'],
        ];

        $seo = Seo::make(PageTitle::regionHub($regionName), PageTitle::description($answer), route('hubs.show', $hub), $indexable)
            ->withBreadcrumbs([['Home', route('home')], ['Jurisdictions', route('jurisdictions.index')], [$regionName, route('hubs.show', $hub)]])
            ->withModified($lastModified)
            ->withPageType('CollectionPage', [
                'name' => 'AI regulation in '.$regionName,
                'description' => $answer,
                'mainEntity' => Seo::itemList($withInstruments, fn ($j) => 'AI regulation in '.$j->name, fn ($j) => $j->url(), 'Jurisdictions in '.$regionName),
            ])
            ->withFaq($faq);

        return view('site.hubs.region', compact('seo', 'hub', 'regionName', 'members', 'withInstruments', 'withBinding', 'binding', 'duties', 'changes', 'deadlines', 'answer', 'policyIds', 'indexableMembers'));
    }
}
