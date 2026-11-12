<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Keeps email addresses on a page as they were written.
 *
 * The edge (Cloudflare "Email Address Obfuscation") replaces every address it finds in an
 * HTML page with a placeholder that a script of its own decodes. The content security
 * policy allows only scripts carrying this response's nonce, so that script never runs and
 * the reader is left with "[email protected]": in the admin (subscribers, accounts,
 * enquiries), on the pricing form and on the legal pages. The edge leaves alone whatever
 * sits between <!--email_off--> and <!--/email_off-->, so every HTML page is given one such
 * region around its whole body. Markers already inside are removed first, because a nested
 * closing marker would switch the rewriting back on for the rest of the page.
 */
class KeepEmailAddressesReadable
{
    public const OPEN = '<!--email_off-->';

    public const CLOSE = '<!--/email_off-->';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse
            || ! str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html')) {
            return $response;
        }
        $html = $response->getContent();
        if (! is_string($html) || ($open = stripos($html, '<body')) === false || ($close = strripos($html, '</body>')) === false) {
            return $response;
        }
        $bodyStart = strpos($html, '>', $open);
        if ($bodyStart === false || $bodyStart > $close) {
            return $response;
        }

        $inner = str_replace([self::OPEN, self::CLOSE], '', substr($html, $bodyStart + 1, $close - $bodyStart - 1));
        // setContent() replaces the response's `original` (the rendered view) with the string;
        // keep it, so code and tests that read the view's data still can.
        $original = property_exists($response, 'original') ? $response->original : null;
        $response->setContent(substr($html, 0, $bodyStart + 1).self::OPEN.$inner.self::CLOSE.substr($html, $close));
        if ($original !== null) {
            $response->original = $original;
        }

        return $response;
    }
}
