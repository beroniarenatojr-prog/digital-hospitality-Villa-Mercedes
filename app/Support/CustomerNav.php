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
     * Keep the three primary account destinations available outside the booking
     * wizard, whose own progress header already leads the guest through one flow.
     */
    public static function showsAccountNav(): bool
    {
        return self::isAccountContext() && ! request()->routeIs('customer.booking.*', 'customer.grouped-booking.*');
    }

    /**
     * The compact account navigation. Notifications and profile live in the
     * account menu (and the phone drawer), and the public site remains
     * available in the footer, so repeating those destinations here adds noise.
     *
     * @return list<array{label: string, route: string, active: bool}>
     */
    public static function accountSections(): array
    {
        return [
            [
                'label' => 'My reservations',
                'route' => 'customer.reservations.index',
                'active' => request()->routeIs('customer.reservations.index', 'customer.reservations.show', 'customer.payments.*'),
            ],
            [
                'label' => 'My inquiries',
                'route' => 'customer.inquiries.index',
                'active' => request()->routeIs('customer.inquiries.*'),
            ],
            [
                'label' => 'New reservation',
                'route' => 'customer.grouped-booking.workspace',
                'active' => request()->routeIs('customer.grouped-booking.*', 'customer.home', 'customer.reservations.new', 'customer.facilities.show', 'customer.booking.*'),
            ],
        ];
    }

    /**
     * The five public destinations. FAQs and Reviews live on the About page,
     * so the primary navigation stays focused on browsing the resort.
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
                'label' => 'About',
                'route' => 'about',
                'active' => request()->routeIs('about'),
            ],
        ];
    }
}
