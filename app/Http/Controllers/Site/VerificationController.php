<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Verification\VerificationPolicy;
use App\Support\Seo;
use Illuminate\View\View;

/**
 * Publishes the verification policy and the corpus's standing against it.
 * A reader can see how old every kind of fact may be, how many records are
 * past that age, and which ones. Nothing here is a marketing number: it is
 * the same report the data check runs in continuous integration.
 */
class VerificationController extends Controller
{
    public function show(VerificationPolicy $policy): View
    {
        $report = $policy->report();
        $seo = Seo::make(
            'Verification policy: how current every record is',
            'How old a fact may be before we check it again, by record type, and exactly how many published records are past that age today. The same check runs on every data change.',
            route('verification')
        )->withBreadcrumbs([['Home', route('home')], ['Methodology', route('methodology')], ['Verification', route('verification')]]);

        return view('site.pages.verification', compact('seo', 'report'));
    }
}
