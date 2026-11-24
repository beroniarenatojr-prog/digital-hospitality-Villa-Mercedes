<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Facility;
use App\Models\Inquiry;
use App\Models\Payment;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every screen in the back office writes its own name twice: once in the top
 * bar, which is the same strip on every screen, and once as the page's only
 * <h1>. They are two renderings of one fact, so they are asserted together —
 * a screen that gained a heading but never named itself in the bar, or kept a
 * bar but lost its heading, fails here.
 *
 * The sweep walks the router rather than a hand-kept list, so a screen added
 * later is covered on the day it is routed. A new GET route under admin. is
 * either a page, and named once the same way in both places, or it is named in
 * NOT_A_PAGE with the reason it is not a screen.
 */
class AdminPageTitleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Back office GET routes that are not screens. Each is here for a reason,
     * because a route that answers with no page is otherwise indistinguishable
     * from one whose title nobody remembered to write.
     *
     * @var array<string, string>
     */
    private const NOT_A_PAGE = [
        'admin.' => 'the bare /admin path only sends a visitor on to the dashboard',
        'admin.login' => 'the sign-in form belongs to the signed-out layout, which titles itself',
        'admin.notifications.open' => 'the bell sends staff on to the record the notification is about',
        'admin.dashboard.*' => 'the panels on the dashboard fetch their own contents on a timer, so these answers are pieces of a screen rather than screens',
        'admin.payments.view' => 'the proof is an image response, not a page',
        'admin.payments.download' => 'the proof download is a file, not a page',
        'admin.reports.export.csv' => 'the export is a file, not a page',
        'admin.reports.export.pdf' => 'the export is a print document, and prints its own heading',
        'admin.reservations.guest-information-sheet' => 'the sheet is a print document, and prints its own heading',
        'admin.reservation-groups.print' => 'the group summary is a print document, and prints its own heading',
        'admin.reservations.lookup-guest' => 'JSON endpoint for looking up existing guests at the front desk',
    ];

    private User $admin;

    /** @var array<string, Model> */
    private array $fixtures = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $group = \App\Models\ReservationGroup::factory()->create([
            'user_id' => $customer->id,
        ]);

        $reservation = Reservation::factory()->create([
            'reservation_group_id' => $group->id,
        ]);

        $this->fixtures = [
            'customer' => $customer,
            'facility' => Facility::factory()->create(),
            'rate' => Rate::factory()->create(),
            'reservation' => $reservation,
            'group' => $group,
            'payment' => Payment::factory()->create(['reservation_id' => $reservation->id]),
            'inquiry' => Inquiry::factory()->create(),
        ];
    }

    public function test_every_back_office_page_is_named_once_and_the_same_way_twice(): void
    {
        $problems = [];

        foreach ($this->pageRoutes() as $name => $route) {
            $bindings = [];

            foreach ($route->parameterNames() as $parameter) {
                if (! array_key_exists($parameter, $this->fixtures)) {
                    $problems[] = "[{$name}] answers with a page that needs a {{$parameter}}, which this test has no fixture for.";

                    continue 2;
                }

                $bindings[$parameter] = $this->fixtures[$parameter];
            }

            $response = $this->actingAs($this->admin)->get(route($name, $bindings));

            if ($response->getStatusCode() !== 200) {
                $problems[] = "[{$name}] answered {$response->getStatusCode()} instead of a page, so nothing on it can be read.";

                continue;
            }

            foreach ($this->namingProblems($name, $response->getContent()) as $problem) {
                $problems[] = $problem;
            }
        }

        $this->assertSame([], $problems, implode("\n", [
            'These back office pages do not name themselves exactly once, or do not agree on the name:',
            '',
            ...$problems,
            '',
            'The top bar and the heading are the same fact written twice, so they have to match:',
            'a page with no heading has no anchor, and a page whose bar and heading disagree',
            'says two things about which screen a staff member is looking at.',
            '',
            'Fix the page, rather than the test: the name a staff member reads in the bar is',
            'the same name the page has to answer to.',
        ]));
    }

    public function test_the_guard_catches_a_page_that_lost_its_name(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->getContent();

        // The page as it ships passes, so the control below is about the guard
        // and not about a page that was already broken.
        $this->assertSame([], $this->namingProblems('admin.dashboard', $html));

        // Losing the heading — the failure this test exists for.
        $this->assertNotSame(
            [],
            $this->namingProblems('admin.dashboard', preg_replace('/<h1\b[^>]*>.*?<\/h1>/s', '', $html))
        );

        // A heading nobody remembered to fill in.
        $this->assertNotSame(
            [],
            $this->namingProblems('admin.dashboard', preg_replace('/<h1\b[^>]*>.*?<\/h1>/s', '<h1>   </h1>', $html))
        );

        // A heading that is there, but names a different screen than the bar does.
        $this->assertNotSame(
            [],
            $this->namingProblems('admin.dashboard', preg_replace('/<h1\b[^>]*>.*?<\/h1>/s', '<h1>Reservations</h1>', $html))
        );

        // A page that never filled its header slot: the bar falls back to
        // "Administration", which no heading will agree with.
        $this->assertNotSame(
            [],
            $this->namingProblems('admin.dashboard', preg_replace(
                '/(<header\b[^>]*>)(.*?)(<\/header>)/s',
                '$1$3',
                $html
            ))
        );
    }

    public function test_every_route_that_is_not_a_page_still_is_not_a_page(): void
    {
        $names = $this->adminRouteNames();

        foreach (self::NOT_A_PAGE as $pattern => $reason) {
            $matched = array_values(array_filter(
                $names,
                static fn (string $name): bool => Str::is($pattern, $name)
            ));

            $this->assertNotSame(
                [],
                $matched,
                "NOT_A_PAGE leaves [{$pattern}] out of the sweep because {$reason} — but no route matches it any more, so the exception is stale."
            );
        }
    }

    /**
     * What is wrong with how this page names itself, in a person's words.
     *
     * @return list<string>
     */
    private function namingProblems(string $name, string $html): array
    {
        $problems = [];
        $headings = $this->headingsIn($html);

        if (count($headings) !== 1) {
            $problems[] = sprintf(
                '[%s] renders %d headings%s. A screen names itself once, so a staff member arriving mid-page still knows where they are.',
                $name,
                count($headings),
                $headings === [] ? '' : ': "'.implode('", "', $headings).'"'
            );
        } elseif ($headings[0] === '') {
            $problems[] = "[{$name}] renders an empty heading, which anchors nothing.";
        }

        $bar = $this->topBarNameIn($html);

        if ($bar === null) {
            $problems[] = "[{$name}] has no top bar, so the screen's name is written nowhere but its heading.";
        } elseif ($headings !== [] && $headings[0] !== $bar) {
            $problems[] = "[{$name}] calls itself \"{$bar}\" in the top bar but \"{$headings[0]}\" in its heading.";
        }

        return $problems;
    }

    /**
     * Every <h1> on the page, as a reader would see it.
     *
     * @return list<string>
     */
    private function headingsIn(string $html): array
    {
        preg_match_all('/<h1\b[^>]*>(.*?)<\/h1>/s', $html, $matches);

        return array_map($this->readableText(...), $matches[1]);
    }

    /**
     * The name the top bar prints, or null where there is no top bar at all.
     */
    private function topBarNameIn(string $html): ?string
    {
        if (! preg_match('/<header\b[^>]*>(.*?)<\/header>/s', $html, $header)) {
            return null;
        }

        if (! preg_match('/<p\b[^>]*>(.*?)<\/p>/s', $header[1], $bar)) {
            return null;
        }

        return $this->readableText($bar[1]);
    }

    /**
     * Markup, entities and view-source line breaks as the text on the screen.
     */
    private function readableText(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)) ?? '');
    }

    /**
     * Every back office GET route that answers with a page, by name.
     *
     * @return array<string, \Illuminate\Routing\Route>
     */
    private function pageRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! Str::startsWith($name, 'admin.')) {
                continue;
            }

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (Str::is(array_keys(self::NOT_A_PAGE), $name)) {
                continue;
            }

            $routes[$name] = $route;
        }

        ksort($routes);

        return $routes;
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
