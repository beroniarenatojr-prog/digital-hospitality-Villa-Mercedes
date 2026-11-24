<?php

namespace App\Support\Admin;

use App\Support\LayoutBadges;

/**
 * The back office navigation, in the order staff work through it.
 *
 * The whole map lives here on purpose: it is the one list that says which
 * screens the back office has, so a reviewer can read it in a single pass, the
 * sidebar can render it without guessing a route, and a test can hold it against
 * the router. Two keys per entry answer where it goes — plus optional `badge`
 * (how much is waiting) and `badge_tone` (whether any of it has waited too long):
 *
 * - `route` is the page the link opens.
 * - `pattern` is every route name that keeps that link lit. It is wider than
 *   `route` whenever a section owns detail pages of its own, so a staff member
 *   reading a payment does not lose the sidebar highlight they arrived with.
 *
 * A destination reachable only by typing a URL is invisible to everyone who
 * needs it, which is how `AdminSidebarNavigationTest` treats an admin route with
 * no entry here.
 */
final class AdminNav
{
    /**
     * @return list<array{heading: string|null, items: list<array{label: string, icon: string, route: string, pattern: string, badge?: int, badge_tone?: string|null}>}>
     */
    public static function groups(): array
    {
        $pendingReservationCount = LayoutBadges::pendingReservationCount();
        $overdueReservationCount = LayoutBadges::overdueReservationCount();
        $pendingPaymentCount = LayoutBadges::pendingPaymentCount();
        $overduePaymentCount = LayoutBadges::overduePaymentCount();

        return [
            ['heading' => null, 'items' => [
                ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard'],
                ['label' => 'Reservation Calendar', 'icon' => 'reservations', 'route' => 'admin.decision-board', 'pattern' => 'admin.decision-board'],
            ]],
            ['heading' => 'Front desk', 'items' => [
                ['label' => 'Reservations', 'icon' => 'reservations', 'route' => 'admin.reservations.index', 'pattern' => 'admin.reservations.*||admin.reservation-groups.*', 'badge' => $pendingReservationCount, 'badge_tone' => $overdueReservationCount > 0 ? 'warning' : null],
                ['label' => 'Payment review', 'icon' => 'payments', 'route' => 'admin.payments.index', 'pattern' => 'admin.payments.*', 'badge' => $pendingPaymentCount, 'badge_tone' => $overduePaymentCount > 0 ? 'warning' : null],
                ['label' => 'Checked-in', 'icon' => 'reservations', 'route' => 'admin.checked-in.index', 'pattern' => 'admin.checked-in.*'],
                ['label' => 'Customers', 'icon' => 'customers', 'route' => 'admin.customers.index', 'pattern' => 'admin.customers.*'],
            ]],
            ['heading' => 'Resort', 'items' => [
                ['label' => 'Facilities', 'icon' => 'facilities', 'route' => 'admin.facilities.index', 'pattern' => 'admin.facilities.*'],
                ['label' => 'Rates', 'icon' => 'rates', 'route' => 'admin.rates.index', 'pattern' => 'admin.rates.*'],
                // Stock inventory is parked: its ledger tables, models and data are
                // still here, but no staff screen offers it until it is restored.
                // The screen itself waits in docs/parked/stock-inventory/README.md.
                ['label' => 'Resort Services', 'icon' => 'resort-services', 'route' => 'admin.resort-services.edit', 'pattern' => 'admin.resort-services.*'],
                ['label' => 'Photos', 'icon' => 'photos', 'route' => 'admin.photos.index', 'pattern' => 'admin.photos.*'],
            ]],
            ['heading' => 'Support', 'items' => [
                ['label' => 'Resort QR accounts', 'icon' => 'payment-destinations', 'route' => 'admin.payment-destinations.index', 'pattern' => 'admin.payment-destinations.*'],
                ['label' => 'Guest reviews', 'icon' => 'reviews', 'route' => 'admin.reviews.index', 'pattern' => 'admin.reviews.*'],
                ['label' => 'FAQs', 'icon' => 'faqs', 'route' => 'admin.faqs.index', 'pattern' => 'admin.faqs.*||admin.faq-categories.*'],
            ]],
            ['heading' => 'Records', 'items' => [
                ['label' => 'Front desk reports', 'icon' => 'reports', 'route' => 'admin.reports.operational', 'pattern' => 'admin.reports.operational'],
                ['label' => 'Reports and insights', 'icon' => 'reports', 'route' => 'admin.reports.index', 'pattern' => 'admin.reports.index||admin.reports.export.*'],
                ['label' => 'Staff activity', 'icon' => 'activity-log', 'route' => 'admin.activity-log.index', 'pattern' => 'admin.activity-log.*'],
            ]],
        ];
    }

    /**
     * Every route name any entry claims, split the way the sidebar splits it.
     *
     * @return list<string>
     */
    public static function patterns(): array
    {
        $patterns = [];

        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                foreach (explode('||', $item['pattern']) as $pattern) {
                    $patterns[] = $pattern;
                }
            }
        }

        return $patterns;
    }
}
