<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Seo;
use Illuminate\View\View;

/**
 * The privacy policy and the terms of use.
 *
 * Both were previously links to whatever `SITE_LINK_PRIVACY_POLICY` and
 * `SITE_LINK_TERMS_OF_USE` pointed at, and when those were unset the sign-up
 * form asked readers to accept terms that resolved to the About page. An
 * acceptance checkbox that links to nothing is not consent, and the download
 * gate records `terms_accepted_at` against it.
 *
 * The privacy page is built from `config/legal.php` and from the application's
 * real tables, so what it claims is stored can be checked against the
 * migrations. Where a value cannot be derived from the codebase — the address
 * for privacy requests, the governing law — the clause is omitted rather than
 * invented.
 */
class LegalController extends Controller
{
    public function privacy(): View
    {
        $seo = Seo::make(
            'Privacy policy',
            'What AIPolicyTracker stores about you, why, how long it is kept, and how to see, correct or delete it. Analytics load only after consent and page counts carry no identifier.',
            route('privacy')
        )->withBreadcrumbs([['Home', route('home')], ['Privacy policy', route('privacy')]]);

        return view('site.pages.privacy', [
            'seo' => $seo,
            'effective' => $this->effective(),
            'contact' => $this->privacyContact(),
            'retention' => config('legal.retention', []),
        ]);
    }

    public function terms(): View
    {
        $seo = Seo::make(
            'Terms of use',
            'The terms for using AIPolicyTracker: informational records rather than legal advice, what the open data licence permits, account rules, and the limits of what is warranted.',
            route('terms')
        )->withBreadcrumbs([['Home', route('home')], ['Terms of use', route('terms')]]);

        return view('site.pages.terms', [
            'seo' => $seo,
            'effective' => $this->effective(),
            'contact' => $this->privacyContact(),
        ]);
    }

    private function effective(): ?\Illuminate\Support\Carbon
    {
        $raw = (string) config('legal.effective_from');

        try {
            return $raw === '' ? null : \Illuminate\Support\Carbon::parse($raw);
        } catch (\Throwable) {
            // A malformed date must not take the page down; the date line is
            // simply omitted, which is visible and self-correcting.
            return null;
        }
    }

    /**
     * The address for a privacy request, or null when none is configured.
     *
     * Falls back to the general contact list before giving up, because a reader
     * exercising a data right should not be sent to a form if a mailbox exists.
     */
    private function privacyContact(): ?string
    {
        $explicit = (string) config('legal.privacy_contact');
        if ($explicit !== '') {
            return $explicit;
        }

        $general = config('aipolicytracker.contact_emails', []);

        return $general[0] ?? null;
    }
}
