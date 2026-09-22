<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The address every rate limit and audit row is keyed on comes from
 * X-Forwarded-For only when the peer that sent it is a proxy the deployment
 * named. Anyone else's forwarded header is just a header.
 */
class TrustedProxiesTest extends TestCase
{
    private function ipSeenFor(string $trusted, string $peer, string $forwarded): string
    {
        config(['app.trusted_proxies' => $trusted]);
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => $peer, 'HTTP_X_FORWARDED_FOR' => $forwarded]);
        $seen = null;
        (new TrustProxies)->handle($request, function (Request $r) use (&$seen) {
            $seen = $r->ip();

            return response('ok');
        });

        return $seen;
    }

    public function test_only_a_named_proxy_may_speak_for_the_visitor(): void
    {
        $this->assertSame('203.0.113.9', $this->ipSeenFor('10.0.0.1', '10.0.0.1', '203.0.113.9'));
        $this->assertSame('198.51.100.7', $this->ipSeenFor('10.0.0.1', '198.51.100.7', '203.0.113.9'), 'a direct caller cannot choose its own address');
        $this->assertSame('203.0.113.9', $this->ipSeenFor('10.0.0.0/8, 127.0.0.1', '10.20.30.40', '203.0.113.9'), 'ranges are honoured');
        $this->assertSame('203.0.113.9', $this->ipSeenFor('*', '198.51.100.7', '203.0.113.9'), '* keeps the previous behaviour for a proxy that rewrites the header itself');
    }
}
