<?php

/*
|--------------------------------------------------------------------------
| Social preview cards
|--------------------------------------------------------------------------
|
| Every page shared a single static image, and that image had counts painted
| into it: it read "117 jurisdictions · 182 instruments" while the corpus held
| 212 and 186. A number baked into a PNG cannot be kept true, and a site whose
| argument is that its figures can be trusted should not publish a stale one on
| every share.
|
| Cards are now drawn per record from the record itself, and the figures on the
| default card are read from the database at render time.
|
*/

return [

    // Off turns every page back to the static image in `aipolicytracker.default_og_image`.
    'cards' => (bool) env('SOCIAL_CARDS_ENABLED', true),

    // The dimensions every major platform crops from.
    'width' => 1200,
    'height' => 630,

    /*
     | Drawing text needs a TrueType font, and the brand faces are served from a
     | font CDN rather than vendored here. These are the faces Debian-based PHP
     | images and Alpine's font-dejavu package carry, tried in order.
     |
     | If none of them exists the card is not drawn at all and the static image is
     | served instead, so a host without fonts behaves exactly as the site did
     | before rather than serving a broken image. `php artisan social:doctor`
     | reports which face resolved on a given host.
     */
    'fonts' => [
        'bold' => array_values(array_filter([
            env('SOCIAL_FONT_BOLD'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
        ])),
        'regular' => array_values(array_filter([
            env('SOCIAL_FONT_REGULAR'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
        ])),
    ],

    // Brand tokens, matching tailwind.config.js. Kept here as integers because
    // GD wants components, not hex strings.
    'palette' => [
        'background' => [255, 255, 255], // the page is white; so is the card
        'panel' => [0, 33, 71],          // brand navy: the footer band
        'accent' => [0, 156, 224],       // brand cyan: the rule
        'text' => [0, 33, 71],           // navy on white, as on the site
        'muted' => [93, 107, 126],       // brand muted
        'footer' => [255, 255, 255],     // white on the navy band
        'line' => [216, 222, 232],       // hairline
    ],

    // The brand as raster, for the drawing routine: the wordmark top-left, the
    // mark as a large watermark on the right. Both transparent PNGs rendered
    // from the SVGs in public/brand.
    'wordmark' => public_path('brand/logo-on-light@2x.png'),
    'mark' => public_path('brand/mark-512.png'),

    // Rendered cards are cached here and keyed by their content, so a record
    // whose title changes gets a new file and the old one is simply unused.
    'cache_disk' => env('SOCIAL_CARD_DISK', 'local'),
    'cache_path' => 'social-cards',

    /*
     | Posting changes to X. Off until an owner turns it on and gives the four
     | OAuth 1.0a keys of the posting account (developer.x.com → the app → Keys
     | and tokens, with "Read and write" permission).
     |
     | Every post carries a link to the change, and X bills a post with a link
     | at a higher rate than one without (about $0.20 against $0.015 since April
     | 2026), so the monthly cap is a spending limit as much as a volume one.
     | "review" holds each post as a draft until someone approves it in the admin.
     */
    'x' => [
        'enabled' => (bool) env('X_POSTING', false),
        'mode' => env('X_MODE', 'auto'), // auto | review
        'api_key' => env('X_API_KEY'),
        'api_secret' => env('X_API_SECRET'),
        'access_token' => env('X_ACCESS_TOKEN'),
        'access_secret' => env('X_ACCESS_SECRET'),
        'mentions' => env('X_MENTIONS', '@aipolicytracker @8h45k4r'),
        'hashtags' => env('X_HASHTAGS', '#AIPolicy'),
        'monthly_cap' => (int) env('X_MONTHLY_CAP', 40),
        'min_impact' => env('X_MIN_IMPACT', 'routine'), // routine | high | urgent
        // A change is posted only while it is news: dated, and first published
        // here, within this many days. It also has to be verified, so a change
        // confirmed a few days after it went up is still posted. A rebuilt
        // database stamps every change as new and must not post the archive.
        'max_age_days' => 21,
        'per_run' => 2,
        'endpoint' => env('X_ENDPOINT', 'https://api.x.com/2/tweets'),
    ],
];
