<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use App\Support\Admin\AdminNav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The sidebar is the back office's only map. Everything here fails for a reason
 * a person would recognise: a link to a route that was renamed, a heading with
 * nothing under it, a highlight that can never light, and — the one that matters
 * most — a screen that exists but that no staff member can walk to.
 *
 * The map itself is `App\Support\Admin\AdminNav`, so this test reads the same
 * list the sidebar renders rather than a copy of it.
 */
class AdminSidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Back office routes that are deliberately kept off the map. Each one is here
     * for a reason, because a route that no entry lights is otherwise
     * indistinguishable from a route somebody forgot to add.
     *
     * @var array<string, string>
     */
    private const OFF_MAP = [
        'admin.' => 'the bare /admin path only sends a visitor on to the dashboard',
        'admin.dashboard.*' => 'the dashboard panels fetch these on a timer, and the sidebar offers screens rather than the contents of one',
        'admin.login' => 'the sign-in form is reached by being turned away, not by being offered',
        'admin.login.store' => 'the sign-in post, reached from that form alone',
        'admin.notifications.*' => 'the notification bell in the header, already on every screen',
        'admin.logout' => 'the way out of the back office, offered in the sidebar footer as a button',
    ];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);
    }

    public function test_every_entry_resolves_to_a_page_staff_can_open(): void
    {
        foreach (AdminNav::groups() as $group) {
            foreach ($group['items'] as $item) {
                $name = $item['route'];

                $this->assertTrue(
                    Route::has($name),
                    "The sidebar offers {$item['label']} at [{$name}], which is not a route."
                );

                $route = Route::getRoutes()->getByName($name);

                // Every entry is an <a href>, so a POST- or PATCH-only route here
                // would render a link that answers with a method-not-allowed.
                $this->assertContains(
                    'GET',
                    $route->methods(),
                    "The sidebar offers {$item['label']} as a link, but [{$name}] does not answer a GET."
                );

                $this->assertNotSame(
                    '',
                    route($name),
                    "[{$name}] cannot be turned into a URL, so {$item['label']} would link nowhere."
                );
            }
        }
    }

    public function test_no_group_is_empty_and_no_entry_is_half_written(): void
    {
        $groups = AdminNav::groups();

        $this->assertNotSame([], $groups, 'The sidebar has no groups at all.');

        $labels = [];
        $destinations = [];

        foreach ($groups as $group) {
            $heading = $group['heading'];

            $this->assertTrue(
                $heading === null || $heading !== '',
                'A sidebar group has an empty heading, which renders as a gap above its links.'
            );

            $this->assertNotSame(
                [],
                $group['items'],
                'The sidebar group '.($heading === null ? 'without a heading' : "\"{$heading}\"").' has no items, so it prints a heading over nothing.'
            );

            foreach ($group['items'] as $item) {
                foreach (['label', 'icon', 'route', 'pattern'] as $key) {
                    $this->assertArrayHasKey($key, $item, "A sidebar entry is missing its '{$key}'.");
                    $this->assertNotSame('', $item[$key], "A sidebar entry has an empty '{$key}'.");
                }

                $this->assertArrayNotHasKey(
                    $item['label'],
                    $labels,
                    "Two sidebar entries are both called \"{$item['label']}\", so neither name says which screen it opens."
                );
                $labels[$item['label']] = true;

                $this->assertArrayNotHasKey(
                    $item['route'],
                    $destinations,
                    "[{$item['route']}] is listed twice in the sidebar."
                );
                $destinations[$item['route']] = true;
            }
        }
    }

    public function test_every_highlight_pattern_matches_a_route(): void
    {
        $names = $this->adminRouteNames();

        foreach (AdminNav::patterns() as $pattern) {
            $matched = array_values(array_filter(
                $names,
                static fn (string $name): bool => Str::is($pattern, $name)
            ));

            $this->assertNotSame(
                [],
                $matched,
                "The pattern [{$pattern}] matches no route, so the entry it belongs to can never light up."
            );
        }
    }

    public function test_every_back_office_route_is_reachable_from_the_sidebar(): void
    {
        $onMap = AdminNav::patterns();
        $offMap = array_keys(self::OFF_MAP);

        $missing = [];

        foreach ($this->adminRouteNames() as $name) {
            if (Str::is($onMap, $name) || Str::is($offMap, $name)) {
                continue;
            }

            $missing[] = $name;
        }

        $this->assertSame([], $missing, implode("\n", [
            'These back office routes exist but no sidebar entry leads to them:',
            '',
            ...$missing,
            '',
            'Add an entry to App\Support\Admin\AdminNav::groups(), or name it in',
            'AdminSidebarNavigationTest::OFF_MAP with the reason staff are not offered it.',
            'A screen with no way in is a screen the back office does not have.',
        ]));
    }

    public function test_every_off_map_exception_still_describes_a_real_route(): void
    {
        $names = $this->adminRouteNames();

        foreach (self::OFF_MAP as $pattern => $reason) {
            $matched = array_values(array_filter(
                $names,
                static fn (string $name): bool => Str::is($pattern, $name)
            ));

            $this->assertNotSame(
                [],
                $matched,
                "OFF_MAP keeps [{$pattern}] off the map because {$reason} — but no route matches it any more, so the exception is stale."
            );
        }
    }

    public function test_the_sidebar_leaves_through_the_back_office_door(): void
    {
        $sidebar = $this->renderedSidebar();

        // Staff sign out to the staff sign-in form. Posting to the guest logout
        // instead would drop them on the public home page, which is the one
        // place a back office session should never end.
        $this->assertStringContainsString(
            'action="'.route('admin.logout').'"',
            $sidebar,
            'The sidebar does not sign staff out through [admin.logout].'
        );

        $this->assertStringNotContainsString(
            'action="'.route('logout').'"',
            $sidebar,
            'The sidebar posts to the guest logout, which sends staff to the public home page.'
        );
    }

    public function test_the_sidebar_asks_before_ending_a_shift(): void
    {
        $sidebar = $this->renderedSidebar();

        // Ending a shift costs whatever is typed and unsaved, so it asks first,
        // on a real dialog: labelled, modal, and with the safe answer offered.
        $this->assertStringContainsString('role="dialog"', $sidebar, 'The sign-out confirmation is not a dialog.');
        $this->assertStringContainsString('aria-modal="true"', $sidebar, 'The sign-out dialog does not announce itself as modal.');
        $this->assertStringContainsString('Sign out of the back office?', $sidebar, 'The sign-out dialog does not ask anything.');
        $this->assertStringContainsString('Stay signed in', $sidebar, 'The sign-out dialog offers no way to stay signed in.');

        // The rail keeps the same control as an icon, so neither layout loses it.
        $this->assertStringContainsString('aria-label="Log out"', $sidebar, 'The icon-rail sidebar has no sign-out control.');
    }

    public function test_the_sign_out_question_can_never_hold_a_staff_member_inside(): void
    {
        $sidebar = $this->renderedSidebar();

        // The trigger is a plain submit button in a form that posts the logout,
        // and asking the question is what the script adds on top. A confirmation
        // that only works while the script runs could leave someone signed in
        // with no way out — the same failure the drawer avoids by closing in CSS.
        $this->assertMatchesRegularExpression(
            '/<form method="POST" action="'.preg_quote(route('admin.logout'), '/').'">.*?<button[^>]*type="submit"[^>]*@click\.prevent/s',
            $sidebar,
            'The sign-out control is not a submit button that JavaScript merely improves, so it would only work with the script running.'
        );

        // …and that form has to be able to post on its own.
        $this->assertMatchesRegularExpression(
            '/name="_token" value="[A-Za-z0-9]{40}"/',
            $sidebar,
            'The sign-out form carries no CSRF token, so submitting it without the script would be refused.'
        );
    }

    public function test_the_sidebar_renders_the_map(): void
    {
        $sidebar = $this->renderedSidebar();

        $labels = [
            'Dashboard', 'Reservation Calendar', 'Front desk',
            'Reservations', 'Payment review', 'Checked-in', 'Customers',
            'Resort', 'Facilities', 'Rates', 'Resort Services', 'Photos',
            'Support', 'Resort QR accounts', 'Guest reviews', 'FAQs',
            'Records', 'Front desk reports', 'Reports and insights', 'Staff activity',
        ];
        $offset = 0;
        foreach ($labels as $label) {
            $matched = preg_match('/>\s*'.preg_quote($label, '/').'\s*</', $sidebar, $match, PREG_OFFSET_CAPTURE, $offset);
            $this->assertSame(1, $matched, "The sidebar must show {$label} in the requested order.");
            $offset = $match[0][1] + strlen($match[0][0]);
        }

        foreach (AdminNav::groups() as $group) {
            if ($group['heading'] !== null) {
                $this->assertStringContainsString(
                    $group['heading'],
                    $sidebar,
                    "The sidebar does not print its \"{$group['heading']}\" heading."
                );
            }

            foreach ($group['items'] as $item) {
                $this->assertStringContainsString(
                    $item['label'],
                    $sidebar,
                    "The sidebar map lists {$item['label']}, but the rendered sidebar does not."
                );

                $this->assertStringContainsString(
                    'href="'.route($item['route']).'"',
                    $sidebar,
                    "The sidebar does not link {$item['label']} to [{$item['route']}]."
                );
            }
        }
    }

    /**
     * The sidebar as the browser receives it, so the checks read the markup a
     * staff member actually gets rather than the component source.
     */
    private function renderedSidebar(): string
    {
        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->getContent();

        $this->assertMatchesRegularExpression(
            '/<aside\b[^>]*id="admin-sidebar"/',
            $html,
            'The dashboard has no admin sidebar, so nothing can be checked on a real page.'
        );

        preg_match('/<aside\b[^>]*id="admin-sidebar".*?<\/aside>/s', $html, $matches);

        return $matches[0];
    }

    /** @return list<string> every named back office route, in a stable order */
    private function adminRouteNames(): array
    {
        $names = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name !== null && Str::startsWith($name, 'admin.')) {
                $names[] = $name;
            }
        }

        sort($names);

        return array_values(array_unique($names));
    }
}
