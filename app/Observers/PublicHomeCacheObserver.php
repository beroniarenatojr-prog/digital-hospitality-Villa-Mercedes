<?php

namespace App\Observers;

use App\Support\AdminCatalogCache;
use App\Support\PublicHomeCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps the public home cache fresh regardless of which admin workflow edits
 * facilities, their images, rates, or the public gallery.
 */
class PublicHomeCacheObserver
{
    /**
     * Wait for a successful transaction before invalidating a public response.
     *
     * @var bool
     */
    public $afterCommit = true;

    public function saved(Model $model): void
    {
        PublicHomeCache::forget();
        AdminCatalogCache::forget();
    }

    public function deleted(Model $model): void
    {
        PublicHomeCache::forget();
        AdminCatalogCache::forget();
    }

    public function restored(Model $model): void
    {
        PublicHomeCache::forget();
        AdminCatalogCache::forget();
    }
}
