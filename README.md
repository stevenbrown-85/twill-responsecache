# Twill Response Cache

Full-page frontend caching for [Twill CMS 3](https://twillcms.com) sites, built on
[spatie/laravel-responsecache](https://github.com/spatie/laravel-responsecache).

- Caches successful frontend GET responses in a dedicated **file** store.
- **Clears automatically** when content changes in the CMS: saves, deletes, restores, reordering, bulk actions and settings.
- **Exclusions**: choose models and Twill modules whose changes should *not* clear the cache.
- **"Clear site cache" button** on the Twill dashboard.
- Never serves cached pages to logged-in CMS users or to visitors with flashed session data (form success messages, validation errors).
- CSRF tokens in cached pages are swapped per visitor through spatie's `CsrfTokenReplacer`.

## Installation

Add the repository and require the package:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/stevenbrown-85/twill-responsecache" }
]
```

```bash
composer require stevenbrown-85/twill-responsecache
```

The service provider is auto-discovered.

Add spatie's middleware to the frontend routes you want cached. Don't add it to the Twill admin.

```php
use Spatie\ResponseCache\Middlewares\CacheResponse;

Route::middleware(CacheResponse::class)->group(function () {
    Route::get('/', [PageController::class, 'home']);
    Route::get('{slug}', [PageController::class, 'show']);
});
```

Only GET requests are cached, so POST routes such as form submissions can stay inside the group. Keeping them outside is clearer.

## Configuration

Publish the config:

```bash
php artisan vendor:publish --tag=twill-responsecache-config
```

| Key | Default | |
| --- | --- | --- |
| `enabled` | `env('TWILL_RESPONSE_CACHE_ENABLED', true)` | Turn caching on or off |
| `lifetime` | `env('TWILL_RESPONSE_CACHE_LIFETIME', 604800)` | Seconds a page stays cached |
| `store` | `responsecache` | Cache store name. The package defines it as a file store if it isn't in `config/cache.php` |
| `path` | `storage/framework/cache/responsecache` | Location of the auto-defined file store |
| `exclude.models` | Twill `User`, `Media`, `File`, activity log | Models whose changes never clear the cache |
| `exclude.modules` | `users`, `groups`, `roles` | Twill modules whose admin actions never clear the cache |
| `ignore_admin_routes` | login, logout, media/file library, previews | Admin route names (without the `twill.` prefix) that never clear the cache |
| `dashboard.enabled` | `true` | Show the dashboard panel |
| `permissions.allowed_roles` | `SUPERADMIN`, `ADMIN`, `PUBLISHER` | Roles that can clear the cache manually. `null` allows any CMS user |

### Why a dedicated store?

spatie's `ResponseCache::clear()` flushes the entire store it uses. On the app's default store, that would also wipe sessions, rate-limiter counters and any other cached data. A dedicated file store keeps the flush scoped to cached pages.

### Excluding objects

Add things that never appear on the frontend, such as form submissions, so that changing them doesn't throw away the cache:

```php
'exclude' => [
    'models' => [
        // ...package defaults
        App\Models\ContactSubmission::class,
    ],
    'modules' => [
        // ...package defaults
        'contactSubmissions',
    ],
],
```

- **`models`** covers model events from anywhere: CMS, frontend, console and queue. Subclasses match.
- **`modules`** covers Twill admin requests for that module, including reorder and bulk actions. Those run query-builder updates without model events.

You usually want both for a module.

### Using spatie's own config

If you publish `config/responsecache.php`, it takes precedence and this package won't override its `cache_store`, `cache_lifetime_in_seconds` or `cache_profile`. Set `cache_profile` to `StevenBrown85\TwillResponseCache\CacheProfiles\TwillCacheProfile` to keep this package's rules.

## How invalidation works

1. A wildcard listener on Eloquent `saved` / `deleted` / `restored` / `forceDeleted` events marks the cache dirty, unless the model is excluded.
2. Middleware on the Twill admin marks the cache dirty after any successful POST/PUT/PATCH/DELETE, unless the route is ignored or belongs to an excluded module.
3. The cache is cleared **once** when the request, command or queued job finishes, however many writes happened.

## Manual clearing

- Use the **Clear site cache** button on the Twill dashboard.
- Or `php artisan responsecache:clear`, which is useful in deploy scripts.

## License

MIT
