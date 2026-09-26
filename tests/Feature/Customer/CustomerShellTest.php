<?php

namespace Tests\Feature\Customer;

use App\Enums\FacilityStatus;
use App\Enums\RateType;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Inquiry;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\User;
use App\Support\CustomerNav;
use Database\Seeders\FacilityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The account shell: one navigation, one header, no invented way back.
 *
 * The account area is a shell around a set of pages, and the shell is only worth
 * anything if every page wears the same one. It has drifted four ways before:
 *
 *  1. The signed-in header carried a second copy of the account sections while
 *     each page added its own "back to …" link, so "where am I" was answered two
 *     or three times in different words.
 *  2. Four page headers disagreed on typeface and scale (`text-4xl md:text-5xl`
 *     on reservations, `text-3xl font-bold` on inquiries), so one product read
 *     as four.
 *  3. The space detail page hand-rolled its own breadcrumb *and* its own
 *     `text-4xl md:text-5xl lg:text-6xl` heading while sitting inside the account.
 *  4. The wizard's pages were the documented exception, and nothing said so
 *     except a comment in `CustomerNav`.
 *
 * So the rules are enforced twice, because they fail twice: statically, over the
 * files of the account area, and live, over every GET route the account actually
 * serves — enumerated from the router, so a new page cannot quietly opt out by
 * being forgotten here (see `test_every_account_route_is_covered_by_the_shell_rules`).
 *
 * A booking in progress is the one declared exception: a guest following one task
 * gets the flow's own header and step controls instead of the account sections, so
 * those pages are marked `flow` and checked for the opposite of everything else.
 * The cottage form steps back to "Choose another cottage" for the same reason.
 */
class CustomerShellTest extends TestCase
{
    use RefreshDatabase;

    /** The shared header's marker; `x-customer.page-header` prints it once per page. */
    private const HEADER_MARKER = 'data-customer-page-header';

    /** The layout's account navigation, addressed by the label it is read under. */
    private const ACCOUNT_NAV_MARKER = 'aria-label="Account"';

    private User $customer;

    private Facility $room;

    private Facility $cottage;

    private Reservation $reservation;

    private Inquiry $inquiry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FacilityTypeSeeder::class);

        $this->customer = User::factory()->create();
        $this->room = $this->facility('room');
        $this->cottage = $this->facility('cottage');

        $this->reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->room->id,
        ]);

        $this->inquiry = Inquiry::factory()->create(['user_id' => $this->customer->id]);
    }

    /**
     * A page written by hand rather than the shared shell: an arrow link, a
     * "Back to …" link, or a breadcrumb of its own.
     *
     * Each entry names the file and the reason, so the exception stays local and
     * visible instead of loosening the rule for every page.
     *
     * @var array<string, string>
     */
    private const OWN_WAY_BACK_ALLOWED = [
        'customer/booking/create.blade.php' => 'the wizard keeps its own breadcrumb back to My reservations: its hero is the flow\'s header, and the account navigation is deliberately absent there',
        'customer/booking/cottage.blade.php' => 'the booking flow steps between its own panes ("Choose another cottage"): a guest mid-booking is following one task, not a section of the account',
        'customer/booking/unified-workspace.blade.php' => 'unified booking flow has its own hero header and step controls',
    ];

    /**
     * The three ways a page writes its own way back, each with an example.
     *
     * The examples exist so the guard can be shown to fire: a pattern nobody has
     * watched catch its own mistake is a pattern that may be catching nothing.
     * The `clean` examples are the sanctioned idioms — the shared header's `back`
     * slot, and a forward arrow, which is not a way back at all.
     *
     * @var array<string, array{pattern: string, example: string, clean: string}>
     */
    private const OWN_WAY_BACK = [
        'an arrow link' => [
            'pattern' => '/<a\b[^>]*>\s*(?:←|&larr;|&leftarrow;)/u',
            'example' => '<a href="{{ route(\'customer.reservations.new\') }}" class="text-sm">← Choose another cottage</a>',
            'clean' => '<a href="{{ $url }}" class="text-sm">Payment history &rarr;</a>',
        ],
        'a "Back to …" link' => [
            'pattern' => '/<a\b[^>]*>\s*Back to\b/',
            'example' => '<a href="{{ route(\'customer.inquiries.index\') }}">Back to my inquiries</a>',
            'clean' => '<x-customer.page-header :back="[\'label\' => \'Back to my inquiries\', \'href\' => $url]" />',
        ],
        'a breadcrumb of its own' => [
            'pattern' => '/aria-label="Breadcrumb"/',
            'example' => '<nav class="flex mb-6 text-sm" aria-label="Breadcrumb">',
            'clean' => '<x-customer.page-header :breadcrumbs="[\'label\' => \'My reservations\']" />',
        ],
    ];

    /**
     * Account routes that are not pages.
     *
     * @var array<string, string>
     */
    private const NOT_A_PAGE = [
        'customer.booking.magic-link.consume' => 'kept only so an old link explains that the flow moved; it answers 410',
        'customer.booking.legacy' => 'a redirect into the wizard',
        'customer.booking.resume' => 'the link from a "finish your booking" email: it puts the guest\'s draft back into this session and redirects into the wizard, so it has no page of its own',
        'dashboard' => 'a redirect into the account',
        'customer.payments.return' => 'the payment gateway returning the guest; it redirects',
        'customer.payments.cancel' => 'the payment gateway cancelling; it redirects',
        'customer.payments.view' => 'serves a stored proof file, not a page',
        'customer.payments.download' => 'serves a stored proof file, not a page',
        'customer.notifications.open' => 'marks one notification read and redirects to its target',
    ];

    public function test_only_the_shared_header_renders_the_account_navigation(): void
    {
        $offences = [];

        foreach ($this->accountViewFiles() as $path => $contents) {
            if (Str::contains($contents, 'x-customer.account-nav')) {
                $offences[] = $path;
            }
        }

        $this->assertSame([], $offences, implode("\n", [
            'These views render the account navigation themselves.',
            'The shared header owns the three primary account destinations so individual',
            'pages cannot add a different or duplicated set of links.',
            '',
            ...$offences,
        ]));
    }

    public function test_no_account_view_hand_rolls_a_way_back(): void
    {
        $offences = [];

        foreach ($this->accountViewFiles() as $path => $contents) {
            if (array_key_exists($path, self::OWN_WAY_BACK_ALLOWED)) {
                continue;
            }

            foreach (self::OWN_WAY_BACK as $description => $rule) {
                if (preg_match($rule['pattern'], $contents) === 1) {
                    $offences[] = $path.': '.$description;
                }
            }
        }

        $this->assertSame([], $offences, implode("\n", [
            'These views write their own way back.',
            'An account page says where it is through `x-customer.page-header` — a',
            'breadcrumb or the single-step `back` slot — and the account navigation',
            'above it is the only other way out. A hand-written link is how the area',
            'ended up answering "where am I" in three different vocabularies.',
            '',
            ...$offences,
        ]));
    }

    public function test_each_shell_guard_catches_the_mistake_it_exists_for(): void
    {
        foreach (self::OWN_WAY_BACK as $description => $rule) {
            $this->assertSame(1, preg_match($rule['pattern'], $rule['example']), 'The "'.$description.'" guard does not catch its own example.');
            $this->assertSame(0, preg_match($rule['pattern'], $rule['clean']), 'The "'.$description.'" guard flags the sanctioned idiom.');
        }

    }

    public function test_every_account_page_wears_the_same_shell(): void
    {
        foreach ($this->accountPages() as $page) {
            $this->actingAs($this->customer);

            $pageLabel = $page['note'] ?? $page['route'];
            $response = $this->get(route($page['route'], $page['params']));
            $response->assertOk();
            $html = (string) $response->getContent();
            $isFlow = $page['flow'] ?? false;

            // The three primary destinations stay in one shared account navigation.
            $this->assertSame(
                $isFlow ? 0 : 1,
                substr_count($html, self::ACCOUNT_NAV_MARKER),
                $isFlow
                    ? $pageLabel.' is part of the booking flow and must not add a second navigation.'
                    : $pageLabel.' must carry the shared account navigation exactly once.'
            );

            // One shared header, which is also the page's only heading.
            $this->assertSame(
                $isFlow ? 0 : 1,
                substr_count($html, self::HEADER_MARKER),
                $isFlow
                    ? $pageLabel.' is part of the booking flow: it announces itself with its own hero header.'
                    : $pageLabel.' must announce itself through x-customer.page-header exactly once.'
            );

            $this->assertSame(
                1,
                substr_count(strtolower($html), '<h1'),
                $pageLabel.' must have exactly one h1: the one the shared header prints (or, in the booking flow, its own hero).'
            );

            if ($isFlow) {
                continue;
            }

            $accountNav = Str::before(Str::after($html, self::ACCOUNT_NAV_MARKER), '</nav>');

            $this->assertLessThanOrEqual(
                1,
                substr_count($accountNav, 'aria-current="page"'),
                $pageLabel.' marks more than one account destination as current.'
            );

            foreach (CustomerNav::accountSections() as $section) {
                $this->assertStringContainsString(
                    'href="'.route($section['route']).'"',
                    $accountNav,
                    $pageLabel.' is missing the "'.$section['label'].'" account destination.'
                );
            }

            $this->assertStringContainsString(
                'href="'.route('customer.notifications.index').'"',
                $accountNav,
                $pageLabel.' is missing Notifications from the phone account menu.'
            );

            $this->assertStringContainsString('href="'.route('profile.edit').'"', $accountNav, $pageLabel.' is missing Profile from the phone account menu.');
            $this->assertStringContainsString('action="'.route('logout').'"', $accountNav, $pageLabel.' is missing Log out from the phone account menu.');
            $this->assertStringNotContainsString('>Browse the resort<', $accountNav, $pageLabel.' restored Browse the resort to the account navigation.');

            $this->assertSame(
                0,
                preg_match_all('/<a\b[^>]*>\s*(?:←|&larr;|&leftarrow;)/u', $html),
                $pageLabel.' writes its own way back with an arrow link.'
            );

            $this->assertSame(
                0,
                preg_match_all('/<a\b[^>]*>\s*Back to\b/', $html),
                $pageLabel.' writes its own way back with a "Back to …" link.'
            );
        }
    }

    public function test_every_account_route_is_covered_by_the_shell_rules(): void
    {
        $covered = array_column($this->accountPages(), 'route');

        $unguarded = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteDefinition $route) => in_array('GET', $route->methods(), true))
            ->map(fn (RouteDefinition $route) => $route->getName())
            ->filter(fn (?string $name) => $name !== null && (
                Str::startsWith($name, ['customer.', 'profile.']) || $name === 'dashboard'
            ))
            ->reject(fn (string $name) => in_array($name, $covered, true) || array_key_exists($name, self::NOT_A_PAGE))
            ->values()
            ->all();

        $this->assertSame([], $unguarded, implode("\n", [
            'These account routes are not covered by the shell rules.',
            'Add the page to accountPages() with the fixture it needs (and mark it',
            '`flow` if it is part of a booking in progress), or list it in NOT_A_PAGE',
            'with the reason it is not a page. A page that renders without the account',
            'shell is a page that is not really part of the account.',
            '',
            ...$unguarded,
        ]));
    }

    /**
     * Every page a signed-in customer can open, with the fixture it needs.
     *
     * `flow` marks a page inside a booking in progress, where the wizard's own
     * step header replaces the standard account page header.
     *
     * @return list<array{route: string, params: array<string, mixed>, flow?: bool, note?: string}>
     */
    private function accountPages(): array
    {
        return [
            ['route' => 'customer.home', 'params' => []],
            ['route' => 'customer.reservations.index', 'params' => []],
            ['route' => 'customer.reservations.new', 'params' => ['type' => 'room']],
            [
                'route' => 'customer.reservations.show',
                'params' => ['reservation' => $this->reservation],
                'note' => 'the stay facet of a reservation',
            ],
            [
                'route' => 'customer.reservations.show',
                'params' => ['reservation' => $this->reservation, 'view' => 'arrival'],
                'note' => 'the arrival facet of a reservation',
            ],
            ['route' => 'customer.payments.index', 'params' => ['reservation' => $this->reservation]],
            ['route' => 'customer.inquiries.index', 'params' => []],
            ['route' => 'customer.inquiries.show', 'params' => ['inquiry' => $this->inquiry]],
            ['route' => 'customer.notifications.index', 'params' => []],
            ['route' => 'customer.facilities.show', 'params' => ['facility' => $this->room]],
            ['route' => 'profile.edit', 'params' => []],
            [
                'route' => 'customer.booking.create',
                'params' => ['facility' => $this->room],
                'flow' => true,
                'note' => 'the reservation wizard',
            ],
            [
                'route' => 'customer.booking.create',
                'params' => ['facility' => $this->cottage],
                'flow' => true,
                'note' => 'the cottage booking form',
            ],
            [
                'route' => 'customer.grouped-booking.workspace',
                'params' => [],
                'flow' => true,
                'note' => 'the unified booking workspace',
            ],
        ];
    }

    /**
     * The account area's own views, keyed by path relative to `resources/views`.
     * The layout and the shared components are deliberately out of scope: they
     * are the shell, not a page wearing it.
     *
     * @return array<string, string>
     */
    private function accountViewFiles(): array
    {
        $files = [];

        foreach (['customer', 'profile'] as $directory) {
            foreach (File::allFiles(resource_path('views/'.$directory)) as $file) {
                $relative = str_replace('\\', '/', $file->getRelativePathname());

                $files[$directory.'/'.$relative] = File::get($file->getPathname());
            }
        }

        return $files;
    }

    private function facility(string $typeSlug): Facility
    {
        $type = FacilityType::query()->where('slug', $typeSlug)->firstOrFail();

        $facility = Facility::factory()->create([
            'facility_type_id' => $type->id,
            'is_active' => true,
            'status' => FacilityStatus::Available->value,
            'capacity' => 2,
            'max_capacity' => 4,
        ]);

        Rate::factory()->create([
            'facility_id' => $facility->id,
            'type' => RateType::Base->value,
            'amount' => 5000,
            'unit' => 'per night',
            'is_active' => true,
        ]);

        return $facility;
    }
}
