<?php

namespace App\Support;

class AuthRedirect
{
    public static function customerUrl(): string
    {
        $path = self::intendedPath();

        if ($path !== null && ! self::isAdminPath($path)) {
            session()->forget('reservation.selection');

            return (string) session()->pull('url.intended');
        }

        return route('customer.reservations.index', absolute: false);
    }

    public static function adminUrl(): string
    {
        $path = self::intendedPath();

        if ($path !== null && self::isAdminDestination($path)) {
            return (string) session('url.intended');
        }

        return route('admin.dashboard', absolute: false);
    }

    private static function intendedPath(): ?string
    {
        $intended = session('url.intended');

        if (! is_string($intended) || $intended === '') {
            return null;
        }

        if (str_starts_with($intended, '//') || str_contains($intended, '\\')) {
            return null;
        }
        $host = parse_url($intended, PHP_URL_HOST);
        $scheme = parse_url($intended, PHP_URL_SCHEME);
        if (($scheme !== null && ! in_array($scheme, ['http', 'https'], true)) || ($host === null && ! str_starts_with($intended, '/'))) {
            return null;
        }
        if ($host !== null && $host !== parse_url(url('/'), PHP_URL_HOST)) {
            return null;
        }

        $path = parse_url($intended, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : null;
    }

    private static function isAdminPath(string $path): bool
    {
        return $path === '/admin' || str_starts_with($path, '/admin/');
    }

    private static function isAdminDestination(string $path): bool
    {
        if ($path === '/admin/login' || str_starts_with($path, '/admin/login')) {
            return false;
        }

        return self::isAdminPath($path);
    }
}
