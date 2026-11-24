<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable / disable
    |--------------------------------------------------------------------------
    |
    | Turns frontend response caching on or off. Invalidation hooks and the
    | dashboard button stay registered either way, so flipping this back on
    | never serves pages that were cached before content changed.
    |
    */

    'enabled' => env('TWILL_RESPONSE_CACHE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Lifetime
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) a cached page is kept. Invalidation is event
    | driven - any CMS change clears the cache - so this can be long.
    |
    */

    'lifetime' => (int) env('TWILL_RESPONSE_CACHE_LIFETIME', 60 * 60 * 24 * 7),

    /*
    |--------------------------------------------------------------------------
    | Cache store
    |--------------------------------------------------------------------------
    |
    | Cached pages are kept in their own store. This matters: clearing the
    | response cache flushes the *whole* store it lives in, so sharing the
    | app's default store would also wipe sessions, rate-limiter counters
    | and anything else kept there.
    |
    | If a store with this name isn't defined in config/cache.php, the
    | package defines it as a file store at `path` below.
    |
    */

    'store' => env('TWILL_RESPONSE_CACHE_STORE', 'responsecache'),

    'path' => storage_path('framework/cache/responsecache'),

    /*
    |--------------------------------------------------------------------------
    | Exclusions
    |--------------------------------------------------------------------------
    |
    | Objects whose changes should NOT clear the cache - typically things
    | that never appear on the frontend.
    |
    | `models`  - Eloquent model classes (subclasses match too). Saves,
    |             deletes and restores of these never clear the cache, from
    |             the CMS, the frontend or the console.
    |
    | `modules` - Twill module names as used in routes/twill.php (e.g.
    |             'contactSubmissions'). Admin actions in these modules -
    |             including reorder and bulk actions, which don't fire model
    |             events - never clear the cache.
    |
    */

    'exclude' => [

        'models' => [
            \A17\Twill\Models\User::class,
            \A17\Twill\Models\Media::class,
            \A17\Twill\Models\File::class,
            \Spatie\Activitylog\Models\Activity::class,
        ],

        'modules' => [
            'users',
            'groups',
            'roles',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored admin routes
    |--------------------------------------------------------------------------
    |
    | Route-name patterns (Str::is syntax, without the Twill route prefix)
    | for admin write requests that never change frontend content.
    |
    */

    'ignore_admin_routes' => [
        'login*',
        'logout',
        'password.*',
        'media-library.*',
        'file-library.*',
        'blocks.preview',
        '*.preview',
        'responsecache.*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | Shows a "Clear site cache" panel on the Twill dashboard.
    |
    */

    'dashboard' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | Twill roles allowed to clear the cache manually. Set to null to allow
    | any logged-in Twill user.
    |
    */

    'permissions' => [
        'allowed_roles' => ['SUPERADMIN', 'ADMIN', 'PUBLISHER'],
    ],

];
