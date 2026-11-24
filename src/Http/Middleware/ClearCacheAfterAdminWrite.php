<?php

namespace StevenBrown85\TwillResponseCache\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use StevenBrown85\TwillResponseCache\Support\CacheInvalidator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks the response cache for clearing after any successful write in the
 * Twill admin.
 *
 * Model events already catch ordinary saves, but several Twill actions -
 * reordering, bulk publish/feature/delete, some restore paths - run plain
 * query-builder updates that fire no Eloquent events. Treating every
 * successful admin write as a content change covers those too.
 */
class ClearCacheAfterAdminWrite
{
    public function __construct(protected CacheInvalidator $invalidator)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldClear($request, $response)) {
            $this->invalidator->markDirty();
        }

        return $response;
    }

    protected function shouldClear(Request $request, Response $response): bool
    {
        if ($request->isMethodSafe()) {
            return false;
        }

        if ($response->getStatusCode() >= 400) {
            return false;
        }

        $prefix = config('twill.admin_route_name_prefix', 'twill.');
        $routeName = $request->route()?->getName();

        if (! $routeName || ! str_starts_with($routeName, $prefix)) {
            return false;
        }

        $name = Str::after($routeName, $prefix);

        foreach ((array) config('twill-responsecache.ignore_admin_routes', []) as $pattern) {
            if (Str::is($pattern, $name)) {
                return false;
            }
        }

        // Module routes are named "{module}.{action}" (nested modules
        // "{parent}.{module}.{action}") - check each segment so nested
        // modules can be excluded by their own name too.
        $segments = explode('.', $name);
        array_pop($segments);

        foreach ($segments as $segment) {
            if ($this->invalidator->moduleIsExcluded($segment)) {
                return false;
            }
        }

        return true;
    }
}
