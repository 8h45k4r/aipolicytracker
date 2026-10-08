<?php

namespace App\Support\Admin;

use Illuminate\Http\RedirectResponse;

/**
 * What every admin bulk bar shares once the server has acted: the honest count when a
 * cap stopped it short of everything the filter matched, and a redirect back to the
 * part of the list the operator was working on.
 */
final class BulkAction
{
    /**
     * Appended to a success message when an "all matching" action reached its cap, so the
     * page never implies that everything matching was done.
     */
    public static function capNote(int $applied, int $total, int $limit): string
    {
        if ($total <= $applied || $applied < $limit) {
            return '';
        }

        return ' Applied to '.number_format($applied).' of '.number_format($total).'; the limit per action is '.number_format($limit).' — run again for the rest.';
    }

    /** Back to the page the request came from, scrolled to the given anchor. */
    public static function back(string $anchor): RedirectResponse
    {
        $previous = strtok(url()->previous(), '#') ?: url()->previous();

        return redirect()->to($previous.'#'.$anchor);
    }
}
