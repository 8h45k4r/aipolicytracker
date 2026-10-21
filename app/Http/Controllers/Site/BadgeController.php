<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PolicyInstrument;
use App\Services\PolicyData\RecordBadge;
use Illuminate\Http\Response;

/**
 * /badges/{policy}.svg: a record's status badge for other sites and READMEs.
 * The snippet on the record page wraps it in a link to the record's permanent
 * address, which is the attribution CC BY asks for.
 */
class BadgeController extends Controller
{
    public function policy(string $policy, RecordBadge $badge): Response
    {
        $record = PolicyInstrument::published()->where('slug', $policy)->firstOrFail();

        return response($badge->svg($record), 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
