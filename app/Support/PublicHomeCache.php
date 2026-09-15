<?php

namespace App\Support;

use App\Models\Facility;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Small, public-only fragments used by the marketing home page.
 *
 * These entries never contain account, reservation, or payment data. They are
 * deliberately short-lived and explicitly invalidated when their source
 * content changes so visitors do not repeatedly hydrate the same records.
 */
final class PublicHomeCache
{
    private const SERVICE_MEDIA_KEY = 'public-home:service-media:v1';

    private const GALLERY_KEY = 'public-home:gallery:v1';

    private const FEATURED_ROOMS_KEY = 'public-home:featured-rooms:v2';

    /**
     * @param  Closure(): array<string, string|null>  $resolver
     * @return array<string, string|null>
     */
    public static function serviceMedia(Closure $resolver): array
    {
        /** @var array<string, string|null> $media */
        $media = Cache::remember(self::SERVICE_MEDIA_KEY, now()->addMinutes(15), $resolver);

        return $media;
    }

    /**
     * @param  Closure(): array<int, array{title: string, caption: string|null, url: string|null}>  $resolver
     * @return array<int, array{title: string, caption: string|null, url: string|null}>
     */
    public static function gallery(Closure $resolver): array
    {
        /** @var array<int, array{title: string, caption: string|null, url: string|null}> $items */
        $items = Cache::remember(self::GALLERY_KEY, now()->addMinutes(15), $resolver);

        return $items;
    }

    /**
     * @param  Closure(): Collection<int, Facility>  $resolver
     * @return Collection<int, Facility>
     */
    public static function featuredRooms(Closure $resolver): Collection
    {
        /** @var Collection<int, Facility> $facilities */
        $facilities = Cache::remember(self::FEATURED_ROOMS_KEY, now()->addMinutes(5), $resolver);

        return $facilities;
    }

    public static function forget(): void
    {
        Cache::forget(self::SERVICE_MEDIA_KEY);
        Cache::forget(self::GALLERY_KEY);
        Cache::forget(self::FEATURED_ROOMS_KEY);
    }
}
