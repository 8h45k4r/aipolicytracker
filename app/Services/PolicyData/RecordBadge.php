<?php

namespace App\Services\PolicyData;

use App\Enums\PolicyStatus;
use App\Enums\ReviewStatus;
use App\Models\PolicyInstrument;

/**
 * A small status badge for a policy record, in the shape of the badges code
 * hosts show (a grey label, a coloured value), for a site or README that cites
 * the record. The value is the legal status and the review state as recorded,
 * so a record still awaiting review says so on every page that shows it.
 */
class RecordBadge
{
    public const LABEL = 'AIPolicyTracker';

    /** Approximate Verdana 11px advance widths; textLength makes the text fit the box exactly. */
    private const WIDTHS = [
        ' ' => 3.9, '.' => 3.9, ',' => 3.9, ':' => 4.7, '·' => 3.9, '-' => 4.6, '(' => 4.5, ')' => 4.5, '/' => 4.9,
        'f' => 3.9, 'i' => 3.0, 'j' => 3.8, 'l' => 3.0, 'r' => 4.7, 't' => 4.3, 'm' => 10.7, 'w' => 9.0,
        'I' => 4.6, 'J' => 5.0, 'M' => 9.0, 'W' => 10.9,
    ];

    public function svg(PolicyInstrument $policy): string
    {
        $value = $this->value($policy);
        $lw = $this->width(self::LABEL) + 10;
        $vw = $this->width($value) + 10;
        $w = $lw + $vw;
        $title = $this->escape(self::LABEL.': '.$policy->title.', '.$value);
        $label = $this->escape(self::LABEL);
        $text = $this->escape($value);
        $colour = $this->colour($policy);
        $lx = $lw / 2;
        $vx = $lw + $vw / 2;
        $ltl = $lw - 10;
        $vtl = $vw - 10;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="20" role="img" aria-label="{$title}"><title>{$title}</title><linearGradient id="s" x2="0" y2="100%"><stop offset="0" stop-color="#bbb" stop-opacity=".1"/><stop offset="1" stop-opacity=".1"/></linearGradient><clipPath id="r"><rect width="{$w}" height="20" rx="3" fill="#fff"/></clipPath><g clip-path="url(#r)"><rect width="{$lw}" height="20" fill="#14213d"/><rect x="{$lw}" width="{$vw}" height="20" fill="{$colour}"/><rect width="{$w}" height="20" fill="url(#s)"/></g><g fill="#fff" text-anchor="middle" font-family="Verdana,Geneva,DejaVu Sans,sans-serif" font-size="11"><text x="{$lx}" y="15" fill="#010101" fill-opacity=".3" textLength="{$ltl}">{$label}</text><text x="{$lx}" y="14" textLength="{$ltl}">{$label}</text><text x="{$vx}" y="15" fill="#010101" fill-opacity=".3" textLength="{$vtl}">{$text}</text><text x="{$vx}" y="14" textLength="{$vtl}">{$text}</text></g></svg>
SVG;
    }

    /** "In force · verified 12 Oct 2026", or the review state when nobody has verified it. */
    public function value(PolicyInstrument $policy): string
    {
        $status = $policy->statusEnum()->label();
        $review = ReviewStatus::tryFrom((string) $policy->review_status);
        $state = match (true) {
            $review === ReviewStatus::Verified && $policy->last_verified_at !== null => 'verified '.$policy->last_verified_at->format('j M Y'),
            $review === ReviewStatus::Verified => 'verified',
            $review === ReviewStatus::NeedsUpdate => 'needs update',
            $review === ReviewStatus::Draft => 'draft',
            default => 'pending review',
        };

        return $status.' · '.$state;
    }

    private function colour(PolicyInstrument $policy): string
    {
        return match ($policy->statusEnum()) {
            PolicyStatus::InForce, PolicyStatus::PartiallyApplicable => '#1f7a3d',
            PolicyStatus::Adopted => '#1d5fa8',
            PolicyStatus::Proposed, PolicyStatus::UnderConsultation => '#9a5b00',
            PolicyStatus::EnforcementAction => '#a52a2a',
            default => '#5b6270',
        };
    }

    private function width(string $text): int
    {
        $w = 0.0;
        foreach (mb_str_split($text) as $ch) {
            $w += self::WIDTHS[$ch] ?? (ctype_upper($ch) || ctype_digit($ch) ? 7.3 : 6.6);
        }

        return (int) ceil($w);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
