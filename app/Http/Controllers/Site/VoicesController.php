<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Services\Voices\VoiceCatalog;
use App\Support\PageTitle;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * AI Voices: people who hold an office or formal role that shapes AI policy, safety or
 * governance, chosen by role and never ranked. Each profile says what an official source
 * says about the role, with that source, and is pending review until a reviewer confirms it.
 */
class VoicesController extends Controller
{
    public function index(Request $request): View
    {
        $all = VoiceCatalog::all();
        $category = array_key_exists((string) $request->query('category'), VoiceCatalog::CATEGORIES) ? (string) $request->query('category') : null;
        $topic = array_key_exists((string) $request->query('topic'), VoiceCatalog::TOPICS) ? (string) $request->query('topic') : null;
        $jurisdictions = Jurisdiction::whereIn('slug', $all->pluck('jurisdiction')->unique())->pluck('name', 'slug');
        $voices = $all->when($category, fn ($c) => $c->where('category', $category))
            ->when($topic, fn ($c) => $c->filter(fn ($v) => in_array($topic, $v['topics'] ?? [], true)));
        $byCategory = $all->groupBy('category')->map->count();

        $seo = Seo::make('AI Voices: who shapes AI policy, safety and governance', 'The people who hold the offices that shape AI law, safety and governance worldwide: regulators, ministers, legislators, safety institutes and international bodies. Chosen by role, each with official sources.', route('voices.index'), ! $category && ! $topic)
            ->withBreadcrumbs([['Home', route('home')], ['AI Voices', route('voices.index')]])
            ->withPageType('CollectionPage', ['mainEntity' => Seo::itemList($voices->take(100), fn ($v) => $v['name'].', '.$v['role'], fn ($v) => route('voices.show', $v['slug']), 'AI Voices')]);

        return view('site.voices.index', compact('seo', 'voices', 'all', 'category', 'topic', 'jurisdictions', 'byCategory'));
    }

    public function show(string $slug): View
    {
        $voice = VoiceCatalog::find($slug);
        abort_unless($voice, 404);
        $jurisdiction = Jurisdiction::where('slug', $voice['jurisdiction'])->first();
        $policies = PolicyInstrument::published()->with('jurisdiction')->whereIn('slug', $voice['related_policies'] ?? [])->get();
        $peers = VoiceCatalog::all()->filter(fn ($v) => $v['slug'] !== $slug && ($v['jurisdiction'] === $voice['jurisdiction'] || array_intersect($v['related_policies'] ?? [], $voice['related_policies'] ?? [])))->take(6);
        $checked = Carbon::parse($voice['last_checked_at']);

        // A person in a public role, as the official source names it. No image, no private details.
        $person = array_filter([
            '@type' => 'Person',
            'name' => $voice['name'],
            'jobTitle' => $voice['role'],
            'worksFor' => ['@type' => 'Organization', 'name' => $voice['organisation']],
            'sameAs' => [$voice['official_profile_url']],
            'url' => route('voices.show', $slug),
            // The photo with its licence and author, as image search asks for an openly licensed image.
            'image' => ($photo = VoiceCatalog::photoUrl($voice)) ? array_filter([
                '@type' => 'ImageObject', 'contentUrl' => $photo, 'license' => $voice['photo']['license_url'] ?: $voice['photo']['source_url'],
                'acquireLicensePage' => $voice['photo']['source_url'], 'creditText' => $voice['photo']['author'] ?: null, 'creator' => $voice['photo']['author'] ? ['@type' => 'Person', 'name' => $voice['photo']['author']] : null,
            ]) : null,
        ]);
        $seo = Seo::make($voice['name'].': '.$voice['role'], PageTitle::description($voice['summary']), route('voices.show', $slug))
            ->withBreadcrumbs([['Home', route('home')], ['AI Voices', route('voices.index')], [$voice['name'], route('voices.show', $slug)]])
            ->withModified($checked)
            ->withCard('voice', $slug, hash('crc32b', json_encode($voice)))
            ->withPageType('ProfilePage', ['mainEntity' => $person]);

        return view('site.voices.show', compact('seo', 'voice', 'jurisdiction', 'policies', 'peers', 'checked'));
    }
}
