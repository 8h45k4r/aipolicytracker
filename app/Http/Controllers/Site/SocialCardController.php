<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Services\PolicyData\PolicyCatalog;
use App\Services\Social\SocialCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Serves the preview image a platform shows when a page is shared.
 *
 * One image per record, drawn from the record. The default card's figures are
 * read from the database rather than painted into a file, because the file that
 * used to carry them had been wrong for months and nothing could tell.
 *
 * A card that cannot be drawn — no font on the host, or cards switched off —
 * redirects to the static image, so the worst case is the behaviour the site
 * had before rather than a broken preview.
 */
class SocialCardController extends Controller
{
    public function __invoke(string $kind, string $slug, SocialCard $cards, PolicyCatalog $catalog): Response|RedirectResponse
    {
        $content = $this->contentFor($kind, $slug, $catalog);
        abort_if($content === null, 404);

        if (! config('social.cards', true)) {
            return redirect(url(config('aipolicytracker.default_og_image')), 302);
        }

        $path = $cards->render($kind.'-'.$slug, $content);
        if ($path === null) {
            // No usable font on this host. Say so once in the log rather than
            // silently serving a different image than the markup promised.
            Log::notice('social.card.unavailable', ['reason' => 'no usable TrueType font; run social:doctor on the host']);

            return redirect(url(config('aipolicytracker.default_og_image')), 302);
        }

        return response(Storage::disk(config('social.cache_disk'))->get($path), 200, [
            'Content-Type' => 'image/png',
            // A day, not a week and not `immutable`: the path has no content hash
            // in it, so the bytes here do change when a record is retitled. The
            // markup appends a version token derived from the record, which is
            // what actually makes a platform fetch the new one.
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** @return array{eyebrow: ?string, title: string, meta: ?string, footer: ?string}|null */
    private function contentFor(string $kind, string $slug, PolicyCatalog $catalog): ?array
    {
        return match ($kind) {
            'policy' => $this->policy($slug),
            'jurisdiction' => $this->jurisdiction($slug),
            'obligation' => $this->obligation($slug),
            'site' => $this->site($catalog),
            default => null,
        };
    }

    private function policy(string $slug): ?array
    {
        $policy = PolicyInstrument::published()->with('jurisdiction')->where('slug', $slug)->first();
        if (! $policy) {
            return null;
        }

        // What a reader most needs from a thumbnail of a law: whose it is, what
        // kind of instrument, and whether it actually binds anybody.
        $meta = array_filter([
            $policy->jurisdiction?->name,
            $policy->typeEnum()->label(),
            $policy->is_binding ? 'Binding' : 'Non-binding',
            $policy->statusEnum()?->label(),
        ]);

        return [
            'eyebrow' => 'AI policy record',
            'title' => $policy->short_title ?: $policy->title,
            'meta' => implode(' · ', $meta),
            'footer' => $this->footerFor($policy->last_verified_at, $policy->review_status),
        ];
    }

    private function jurisdiction(string $slug): ?array
    {
        $jurisdiction = Jurisdiction::published()->where('slug', $slug)->first();
        if (! $jurisdiction) {
            return null;
        }
        $count = $jurisdiction->policyInstruments()->published()->count();

        return [
            'eyebrow' => 'Jurisdiction',
            'title' => 'AI regulation in '.$jurisdiction->name,
            'meta' => $count > 0
                ? $count.' recorded '.Str::plural('instrument', $count).($jurisdiction->region ? ' · '.$jurisdiction->region : '')
                : 'No AI-specific instrument recorded yet'.($jurisdiction->region ? ' · '.$jurisdiction->region : ''),
            'footer' => config('aipolicytracker.site_name').' · every record linked to its official source',
        ];
    }

    private function obligation(string $slug): ?array
    {
        $obligation = Obligation::published()->with('policyInstrument.jurisdiction')->where('slug', $slug)->first();
        if (! $obligation || ! $obligation->policyInstrument) {
            return null;
        }
        $policy = $obligation->policyInstrument;

        return [
            'eyebrow' => $obligation->is_binding ? 'Legal requirement' : 'Voluntary guidance',
            'title' => $obligation->title,
            'meta' => implode(' · ', array_filter([$policy->short_title ?: $policy->title, $policy->jurisdiction?->name])),
            'footer' => config('aipolicytracker.site_name').' · informational, not legal advice',
        ];
    }

    /** The default card, with its figures read rather than remembered. */
    private function site(PolicyCatalog $catalog): array
    {
        $stats = $catalog->stats();
        $parts = array_filter([
            isset($stats['jurisdictions']) ? $stats['jurisdictions'].' jurisdictions' : null,
            isset($stats['policies']) ? $stats['policies'].' instruments' : null,
            isset($stats['obligations']) ? $stats['obligations'].' obligations' : null,
            isset($stats['controls']) && $stats['controls'] ? $stats['controls'].' controls' : null,
        ]);

        return [
            'eyebrow' => 'AI governance intelligence',
            'title' => 'From regulation to evidence.',
            'meta' => $parts === [] ? config('aipolicytracker.supporting') : implode(' · ', $parts),
            'footer' => 'Open data, CC BY 4.0 · AI policy, verified at the source',
        ];
    }

    private function footerFor(?\DateTimeInterface $verifiedAt, ?string $status): string
    {
        $site = config('aipolicytracker.site_name');

        // The card says what the page says. A record nobody has confirmed must not
        // acquire authority by being put on a nice background.
        return $verifiedAt
            ? $site.' · verified '.$verifiedAt->format('j M Y')
            : $site.' · human verification pending';
    }
}
