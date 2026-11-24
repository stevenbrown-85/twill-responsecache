<?php

namespace StevenBrown85\TwillResponseCache\CacheProfiles;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\ResponseCache\CacheProfiles\CacheAllSuccessfulGetRequests;

/**
 * Caches successful GET requests, except when the page could differ for the
 * person viewing it. When enabled() returns false the cache is neither read
 * from nor written to, so those visitors always get a freshly rendered page.
 */
class TwillCacheProfile extends CacheAllSuccessfulGetRequests
{
    public function enabled(Request $request): bool
    {
        if (! config('twill-responsecache.enabled', true)) {
            return false;
        }

        // CMS users see edit prompts / unpublished previews on the frontend.
        if (Auth::guard('twill_users')->check()) {
            return false;
        }

        // Never cache (or serve a cached copy of) the CMS itself.
        if ($request->routeIs(config('twill.admin_route_name_prefix', 'twill.').'*')) {
            return false;
        }

        // Flashed data (form success messages, validation errors, old input)
        // is per-visitor and must not be baked into, or hidden by, the cache.
        if ($request->hasSession() && $this->sessionHasFlashData($request)) {
            return false;
        }

        return parent::enabled($request);
    }

    protected function sessionHasFlashData(Request $request): bool
    {
        $session = $request->session();

        return ! empty($session->get('_flash.old', []))
            || ! empty($session->get('_flash.new', []))
            || $session->has('errors')
            || $session->has('_old_input');
    }
}
