<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Navigation;
use App\Support\Seo;
use Illuminate\View\View;

/**
 * /explore/{group}: every page in one navigation group, under its section headings. The
 * header menu shows five to seven entries per group and links here for the rest, so a
 * shorter menu never makes a page unreachable.
 */
class ExploreController extends Controller
{
    public function show(string $group): View
    {
        $g = Navigation::group($group) ?? abort(404);
        $count = Navigation::items($g)->count();

        $seo = Seo::make(
            $g['label'].': all '.$count.' pages in this section',
            $g['summary'].' Every page in the '.$g['label'].' section of AIPolicyTracker, with what each one is for.',
            route('explore', $group),
            false,
        )->withBreadcrumbs([['Home', route('home')], [$g['label'], route('explore', $group)]]);

        return view('site.explore', ['seo' => $seo, 'group' => $g, 'key' => $group, 'count' => $count]);
    }
}
