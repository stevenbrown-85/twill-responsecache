<?php

namespace StevenBrown85\TwillResponseCache;

use A17\Twill\Facades\TwillRoutes;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use StevenBrown85\TwillResponseCache\CacheProfiles\TwillCacheProfile;
use StevenBrown85\TwillResponseCache\Http\Middleware\ClearCacheAfterAdminWrite;
use StevenBrown85\TwillResponseCache\Support\CacheInvalidator;
use StevenBrown85\TwillResponseCache\Support\Permissions;

class TwillResponseCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/twill-responsecache.php', 'twill-responsecache');

        $this->app->singleton(CacheInvalidator::class);

        $this->configureStore();
        $this->configureSpatie();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/twill-responsecache.php' => config_path('twill-responsecache.php'),
        ], 'twill-responsecache-config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'twill-responsecache');

        $this->registerInvalidation();
        $this->registerRoutes();
        $this->registerDashboardPanel();
    }

    /**
     * Defines the dedicated file store for cached pages unless the app has
     * defined one under that name itself.
     */
    protected function configureStore(): void
    {
        $store = config('twill-responsecache.store');

        if (! config("cache.stores.{$store}")) {
            config(["cache.stores.{$store}" => [
                'driver' => 'file',
                'path' => config('twill-responsecache.path'),
                'lock_path' => config('twill-responsecache.path'),
            ]]);
        }
    }

    /**
     * Points spatie/laravel-responsecache at this package's store, lifetime
     * and cache profile. Runs in register(), before spatie's own provider
     * merges its defaults, so these win unless the app has published
     * config/responsecache.php (in which case that file is respected).
     */
    protected function configureSpatie(): void
    {
        if (file_exists($this->app->configPath('responsecache.php'))) {
            return;
        }

        config([
            'responsecache.enabled' => (bool) config('twill-responsecache.enabled'),
            'responsecache.cache_store' => config('twill-responsecache.store'),
            'responsecache.cache_lifetime_in_seconds' => (int) config('twill-responsecache.lifetime'),
            'responsecache.cache_profile' => TwillCacheProfile::class,
        ]);
    }

    protected function registerInvalidation(): void
    {
        $invalidator = fn (): CacheInvalidator => $this->app->make(CacheInvalidator::class);

        foreach (['saved', 'deleted', 'restored', 'forceDeleted'] as $event) {
            Event::listen("eloquent.{$event}: *", function (string $eventName, array $payload) use ($invalidator) {
                $invalidator()->handleModelEvent($payload[0] ?? null);
            });
        }

        // Queue workers are long-running and never "terminate" between jobs.
        Event::listen(JobProcessed::class, fn () => $invalidator()->flush());

        // Twill's admin shares the `web` middleware group, so the middleware
        // is pushed there and filters to admin routes itself.
        $this->app->booted(function () {
            $group = config('twill.admin_middleware_group', 'web');

            $this->app->make(Router::class)->pushMiddlewareToGroup($group, ClearCacheAfterAdminWrite::class);
        });
    }

    /**
     * Registered synchronously, for the same reason as in twill-templates:
     * Twill's deferred package route registration can end up outside the
     * admin middleware stack (no session / auth).
     */
    protected function registerRoutes(): void
    {
        TwillRoutes::registerRoutes(
            $this->app->make('router'),
            TwillRoutes::getRouteGroupOptions(),
            TwillRoutes::getRouteMiddleware(),
            TwillRoutes::supportSubdomainRouting(),
            null,
            __DIR__.'/../routes/admin.php',
            true
        );
    }

    /**
     * Twill's dashboard has no extension point for custom panels, so the
     * panel is pushed onto the layout's `extra_js` stack whenever the
     * dashboard view renders. Its script moves it into place once Twill's
     * Vue app has mounted. No vendor views are overridden.
     */
    protected function registerDashboardPanel(): void
    {
        if (! config('twill-responsecache.dashboard.enabled', true)) {
            return;
        }

        View::composer('twill::layouts.dashboard', function ($view) {
            if (! Permissions::currentUserIsAllowed()) {
                return;
            }

            $view->getFactory()->startPush(
                'extra_js',
                view('twill-responsecache::dashboard-panel', [
                    'status' => $this->app->make(CacheInvalidator::class)->status(),
                    'enabled' => (bool) config('twill-responsecache.enabled'),
                ])->render()
            );
        });
    }
}
