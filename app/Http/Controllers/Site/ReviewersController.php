<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Reviewers\ReviewerRoster;
use App\Support\Seo;
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
}
