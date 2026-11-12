<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Production serves cached routes, which match the first route registered for an address;
 * without the cache a later GET route replaces an earlier one. So an address defined twice
 * (for example a legacy Route::redirect, which registers every method, and a newer page)
 * can answer differently in production than in tests. Every fixed GET address must reach
 * the same route both ways.
 */
class CachedRoutesMatchTest extends TestCase
{
    public function test_every_fixed_address_reaches_the_same_route_with_and_without_the_route_cache(): void
    {
        $routes = Route::getRoutes();
        $compiled = $routes->compile();
        $cached = (new CompiledRouteCollection($compiled['compiled'], $compiled['attributes']))
            ->setRouter(app('router'))->setContainer(app());

        $checked = 0;
        foreach ($routes->getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{') || $route->getDomain()) {
                continue;
            }
            $path = '/'.ltrim($route->uri(), '/');
            $plain = $routes->match(Request::create($path));
            $fromCache = $cached->match(Request::create($path));
            $this->assertSame(
                [$plain->getName(), $plain->getActionName()],
                [$fromCache->getName(), $fromCache->getActionName()],
                "GET {$path} reaches a different route when routes are cached"
            );
            $checked++;
        }
        $this->assertGreaterThan(100, $checked);
        $this->assertSame('news.index', $cached->match(Request::create('/news'))->getName());
    }
}
