<?php

namespace StevenBrown85\TwillResponseCache\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * Collects "content changed" signals during a request (or console command /
 * queued job) and clears the response cache once at the end of it.
 *
 * Saving a single Twill page touches the page, its slugs, blocks, related
 * items, revisions... - clearing on every one of those writes would flush
 * the store dozens of times per save, so they're batched into one clear.
 */
class CacheInvalidator
{
    protected bool $dirty = false;

    protected bool $flushScheduled = false;

    public function __construct(protected Application $app)
    {
    }

    public function handleModelEvent(mixed $model): void
    {
        if ($model instanceof Model && ! $this->modelIsExcluded($model)) {
            $this->markDirty();
        }
    }

    public function modelIsExcluded(Model $model): bool
    {
        foreach ((array) config('twill-responsecache.exclude.models', []) as $class) {
            if (is_string($class) && $model instanceof $class) {
                return true;
            }
        }

        return false;
    }

    public function moduleIsExcluded(?string $module): bool
    {
        return $module !== null
            && in_array($module, (array) config('twill-responsecache.exclude.modules', []), true);
    }

    public function markDirty(): void
    {
        $this->dirty = true;

        if ($this->flushScheduled) {
            return;
        }

        $this->flushScheduled = true;

        $this->app->terminating(fn () => $this->flush());
    }

    /**
     * Clears the cache if anything changed since the last flush. Called on
     * app termination, and after each queued job (long-running workers never
     * terminate between jobs).
     */
    public function flush(): void
    {
        if (! $this->dirty) {
            return;
        }

        $this->dirty = false;

        $this->clear();
    }

    public function clear(?string $clearedBy = null): void
    {
        ResponseCache::clear();

        $this->writeStatus($clearedBy);
    }

    /**
     * Last-cleared info lives in a small JSON file rather than in a cache
     * store: the response cache's own store is what gets flushed, and the
     * app's default store may be the database.
     */
    public function status(): ?array
    {
        $path = $this->statusPath();

        if (! File::exists($path)) {
            return null;
        }

        $data = json_decode((string) File::get($path), true);

        return is_array($data) ? $data : null;
    }

    protected function writeStatus(?string $clearedBy): void
    {
        $path = $this->statusPath();

        try {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode([
                'cleared_at' => now()->toIso8601String(),
                'cleared_by' => $clearedBy,
            ]));
        } catch (\Throwable) {
            // Status is informational only - never fail a save over it.
        }
    }

    protected function statusPath(): string
    {
        return storage_path('framework/cache/twill-responsecache.json');
    }
}
