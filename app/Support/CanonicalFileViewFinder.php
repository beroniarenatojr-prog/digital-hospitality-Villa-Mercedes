<?php

namespace App\Support;

use Illuminate\View\FileViewFinder;

/** Keep request-time Blade cache keys identical to artisan view:cache. */
final class CanonicalFileViewFinder extends FileViewFinder
{
    protected function findInPaths($name, $paths)
    {
        $path = parent::findInPaths($name, $paths);

        // view:cache compiles SplFileInfo::getRealPath(). On Windows the default
        // finder returns mixed separators instead, hashing to a different file.
        // The parent caches this result per view, so realpath runs only once.
        return realpath($path) ?: $path;
    }
}
