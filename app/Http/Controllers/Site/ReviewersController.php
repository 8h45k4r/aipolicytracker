<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Reviewers\ReviewerRoster;
use App\Support\Seo;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The reviewer roster: who checks the records, what they have declared, and
 * what they have actually verified.
 *
 * The page is built to be uncomfortable when it should be. It leads with how
 * much of the corpus has been through a named reviewer, so a roster of
 * impressive names cannot stand in for work that has not happened, and it says
 * plainly when nobody has verified anything yet.
 */
class ReviewersController extends Controller
{
    public function show(ReviewerRoster $roster): View
    {
        $seo = Seo::make(
            'Reviewers: who checks the records, and what they have declared',
            'The people who verify AI policy records against official sources, the interests each of them has declared, and how many records each has actually verified.',
            route('reviewers')
        )->withBreadcrumbs([['Home', route('home')], ['Methodology', route('methodology')], ['Reviewers', route('reviewers')]])
            ->withPageType('CollectionPage');

        return view('site.pages.reviewers', [
            'seo' => $seo,
            'reviewers' => $roster->published(),
            'standing' => $roster->standing(),
        ]);
    }

    public function person(string $slug, ReviewerRoster $roster): View
    {
        $reviewer = $roster->find($slug);
        abort_unless($reviewer, 404);
        $verified = $roster->verifiedRecords((string) $reviewer['name']);
        $seo = Seo::make(
            $reviewer['name'].': reviewer profile, declared interests and verified records',
            Str::limit(($reviewer['bio'] ?? $reviewer['name'].' reviews AI policy records against official sources on this site.').' Declared interests and every record verified are listed.', 155),
            route('reviewers.show', $slug)
        )->withBreadcrumbs([['Home', route('home')], ['Reviewers', route('reviewers')], [$reviewer['name'], route('reviewers.show', $slug)]])
            ->withPageType('ProfilePage', ['mainEntity' => ['@id' => route('reviewers.show', $slug).'#person']])
            ->withJsonLd(array_filter([
                '@type' => 'Person',
                '@id' => route('reviewers.show', $slug).'#person',
                'name' => $reviewer['name'],
                'url' => route('reviewers.show', $slug),
                'jobTitle' => ucfirst($reviewer['role'] ?? 'reviewer'),
                'description' => $reviewer['bio'] ?? null,
                'knowsAbout' => $reviewer['expertise'] ?? null,
                'sameAs' => array_values(array_map(fn ($l) => $l['url'], $reviewer['links'] ?? [])) ?: null,
                'affiliation' => array_values(array_map(fn ($a) => ['@type' => 'Organization', 'name' => $a['organisation']], $reviewer['affiliations'] ?? [])) ?: null,
                'memberOf' => ['@id' => url('/').'#organization'],
            ]));

        return view('site.pages.reviewer', compact('seo', 'reviewer', 'verified'));
    }
}
