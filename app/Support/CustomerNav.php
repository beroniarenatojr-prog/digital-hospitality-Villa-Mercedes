<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Distinguishes marketing browsing from the signed-in customer account shell.
 */
final class CustomerNav
{
    public static function isCustomer(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ! $user->hasRole(Role::Administrator->value);
    }

    public static function isAccountContext(): bool
    {
        return self::isCustomer() && request()->routeIs('customer.*', 'profile.*');
    }

    /**
     * Public marketing links in landing-page order. About is last.
     *
     * @return list<array{label: string, route: string, active: bool}>
     */
    public static function marketingLinks(): array
    {
        return [
            [
                'label' => 'Home',
                'route' => 'home',
                'active' => request()->routeIs('home'),
            ],
            [
                'label' => 'Services',
                'route' => 'rooms',
                'active' => request()->routeIs('reserve-space', 'rooms', 'packages', 'halls'),
            ],
            [
                'label' => 'Resort & Pools',
                'route' => 'resort.show',
                'active' => request()->routeIs('resort.*', 'cottages', 'pools'),
            ],
            [
                'label' => 'Gallery',
                'route' => 'gallery',
                'active' => request()->routeIs('gallery'),
            ],
            [
                'label' => 'FAQs',
                'route' => 'faq.index',
                'active' => request()->routeIs('faq.*'),
            ],
            [
                'label' => 'Reviews',
                'route' => 'reviews',
                'active' => request()->routeIs('reviews'),
            ],
            [
                'label' => 'About',
                'route' => 'about',
                'active' => request()->routeIs('about'),
            ],
        ];
    }
}
