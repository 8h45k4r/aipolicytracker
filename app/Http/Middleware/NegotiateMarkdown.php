<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a record's Markdown context file at the record's own URL when the
 * client asks for `text/markdown` ahead of HTML, which is how coding agents
 * and answer engines ask for a page they intend to read rather than render.
 *
 * The request is re-pointed at the record's `.md` route before routing, so the
 * stateless route answers it: no session row is written and the 404, the
 * canonical link and the noindex header are the ones that file already sends.
 * A browser never lists `text/markdown`, so it always gets the page.
 */
class NegotiateMarkdown
{
    private const RECORD_PATH = '#^/(policies|jurisdictions|obligations|controls|changes)/[a-z0-9][a-z0-9-]*$#';

    private const ALTERNATE_LINK = '#<link rel="alternate" type="text/markdown" href="([^"<>\s]+)">#';

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true) || preg_match(self::RECORD_PATH, $path) !== 1) {
            return $next($request);
        }

        if ($this->prefersMarkdown($request)) {
            $markdown = $next($this->markdownRequest($request, $path));
            // Anything but a file (a renamed record, a path that is not a record)
            // is left to the page route, which knows how to redirect or refuse it.
            if ($markdown->getStatusCode() === 200) {
                $markdown->headers->set('Vary', 'Accept', false);
                $markdown->headers->set('Content-Location', $path.'.md');
                // The same URL answers browsers with HTML, so no shared cache may keep this copy.
                $markdown->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');

                return $markdown;
            }
        }

        $response = $next($request);
        $response->headers->set('Vary', 'Accept', false);

        $content = $response->getStatusCode() === 200 ? $response->getContent() : false;
        if (is_string($content) && preg_match(self::ALTERNATE_LINK, substr($content, 0, 8192), $m) === 1) {
            $response->headers->set('Link', '<'.$m[1].'>; rel="alternate"; type="text/markdown"', false);
        }

        return $response;
    }

    /** Whichever of Markdown and HTML the client ranks first; a wildcard is not a request for Markdown. */
    private function prefersMarkdown(Request $request): bool
    {
        foreach (AcceptHeader::fromString($request->headers->get('Accept'))->all() as $type => $item) {
            if ($type === 'text/markdown') {
                return $item->getQuality() > 0;
            }
            if ($type === 'text/html' || $type === 'application/xhtml+xml') {
                return false;
            }
        }

        return false;
    }

    private function markdownRequest(Request $request, string $path): Request
    {
        $query = $request->getQueryString();
        $server = $request->server->all();
        $server['REQUEST_URI'] = $request->getBaseUrl().$path.'.md'.($query !== null ? '?'.$query : '');

        return $request->duplicate(null, null, null, null, null, $server);
    }
}
