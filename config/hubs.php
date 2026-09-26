<?php

/*
|--------------------------------------------------------------------------
| Country and regional hubs, and the comparison pairs
|--------------------------------------------------------------------------
|
| A country in `countries` is served at /ai-regulation-<slug> instead of
| /jurisdictions/<slug>, which redirects there. The page is the jurisdiction
| record, so nothing here is content: the list says which countries have a
| hub address, and the guard below says when a hub may be indexed.
|
| `regions` are served at /ai-regulation-<region> from the jurisdictions'
| `region` column. `compare_pairs` are the jurisdiction pairs whose
| /compare/<a>-vs-<b> page is indexed and listed in the sitemap; any other
| pair is served but not offered to search engines. Order inside a pair is
| canonical alphabetical by slug; the other order redirects.
|
*/

return [

    'countries' => [
        'thailand', 'indonesia', 'brazil', 'ghana', 'sri-lanka', 'philippines', 'peru', 'mexico', 'pakistan', 'vietnam',
        'malaysia', 'japan', 'south-korea', 'saudi-arabia', 'egypt', 'nigeria', 'kenya', 'canada', 'new-zealand', 'nepal',
    ],

    // Hub slug => the value in jurisdictions.region.
    'regions' => [
        'africa' => 'Africa',
        'asia' => 'Asia',
        'europe' => 'Europe',
        'americas' => 'Americas',
        'oceania' => 'Oceania',
    ],

    // A hub is indexed with at least this many published instruments that carry an
    // official source, or one verified strategy. Below that it is served noindex.
    'min_sourced_instruments' => 2,

    // A regional hub is indexed with at least this many indexable country pages.
    'min_region_countries' => 3,

    // Pairs indexed and listed (canonical order is applied at runtime).
    'compare_pairs' => [
        ['eu', 'japan'], ['eu', 'south-korea'], ['eu', 'brazil'], ['eu', 'canada'], ['uk', 'canada'], ['uk', 'australia'],
        ['us', 'canada'], ['us', 'uk'], ['japan', 'south-korea'], ['singapore', 'malaysia'], ['indonesia', 'malaysia'],
        ['thailand', 'vietnam'], ['brazil', 'mexico'], ['brazil', 'peru'], ['saudi-arabia', 'uae'], ['egypt', 'saudi-arabia'],
        ['nigeria', 'kenya'], ['ghana', 'nigeria'], ['india', 'pakistan'], ['nepal', 'sri-lanka'], ['australia', 'new-zealand'],
        ['china', 'japan'], ['eu', 'china'],
    ],
];
