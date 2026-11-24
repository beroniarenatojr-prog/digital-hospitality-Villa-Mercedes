<?php

namespace Tests\Feature\Customer;

use App\Enums\FacilityStatus;
use App\Enums\RateType;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\GalleryImage;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\SwimmingSession;
use App\Models\User;
use Database\Seeders\FacilityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The picker offers what a guest can actually book.
 *
 * A guest who picks dates and then reads a card believes the card is free. That
 * belief is the entire value of the page, so the filter is not a convenience
 * laid over the catalog: the spaces it leaves out are exactly the ones the
 * resort's own availability rules refuse, because it asks those rules rather
 * than restating them (see `AvailabilitySearchService::openAmong`).
 *
 * The dates the guest brings also travel: the space page forwards them to the
 * wizard, which prefills them, so a filtered list and the booking it leads to
 * can never describe different stays.
 */
class PickerAvailabilityFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 08:00:00'));

        $this->seed(FacilityTypeSeeder::class);

        $this->customer = User::factory()->create();
    }

    public function test_a_space_that_is_already_booked_is_not_offered_for_those_dates(): void
    {
        $free = $this->space('room', 'Free Room');
        $booked = $this->space('room', 'Booked Room');

        $this->booking($booked, '2026-10-12 14:00', '2026-10-13 12:00');

        $this->picker(['type' => 'room', 'check_in' => '2026-10-12', 'check_out' => '2026-10-13', 'guests' => 2])
            ->assertOk()
            ->assertSee('Spaces free for your dates')
            ->assertSee('Free Room')
            ->assertDontSee('Booked Room');
    }

    public function test_the_published_catalog_is_unchanged_when_no_dates_are_given(): void
    {
        $this->space('room', 'Free Room');
        $booked = $this->space('room', 'Booked Room');

        $this->booking($booked, '2026-10-12 14:00', '2026-10-13 12:00');

        $this->picker(['type' => 'room'])
            ->assertOk()
            ->assertSee('Check what is free first')
            ->assertSee('Free Room')
            ->assertSee('Booked Room');
    }

    public function test_a_space_under_maintenance_is_not_offered_for_dates(): void
    {
        $this->space('room', 'Open Room');
        $this->space('room', 'Closed Room', status: FacilityStatus::Maintenance);

        $this->picker(['type' => 'room', 'check_in' => '2026-10-12', 'check_out' => '2026-10-13'])
            ->assertOk()
            ->assertSee('Open Room')
            ->assertDontSee('Closed Room');
    }

    public function test_party_size_leaves_out_spaces_that_cannot_take_it(): void
    {
        $this->space('room', 'Couple Room', capacity: 2, maxCapacity: 2);
        $this->space('room', 'Barkada Room', capacity: 4, maxCapacity: 6);

        $this->picker(['type' => 'room', 'guests' => 3])
            ->assertOk()
            ->assertSee('Spaces for your party')
            ->assertSee('Barkada Room')
            ->assertDontSee('Couple Room');

        // The same party on the same catalog, now with dates: still filtered, and
        // still by the rule the guest can reason about.
        $this->picker(['type' => 'room', 'guests' => 3, 'check_in' => '2026-10-12', 'check_out' => '2026-10-13'])
            ->assertOk()
            ->assertSee('Barkada Room')
            ->assertDontSee('Couple Room');
    }

    public function test_a_cottage_booked_for_one_swim_is_still_offered_for_another(): void
    {
        $this->swimSession('Morning swim', 'morning-swim', '08:00', '12:00');
        $this->swimSession('Afternoon swim', 'afternoon-swim', '13:00', '17:00');
        $cottage = $this->space('cottage', 'Barkada Cottage');

        $visit = now()->addDays(20)->toDateString();
        $this->booking($cottage, $visit.' 08:00', $visit.' 12:00');

        // No session chosen: the afternoon swim is still free, so the cottage is.
        $this->picker(['type' => 'cottage', 'date' => $visit])
            ->assertOk()
            ->assertSee('Barkada Cottage');

        // The booked session is exactly what the filter is for.
        $this->picker(['type' => 'cottage', 'date' => $visit, 'session' => 'morning-swim'])
            ->assertOk()
            ->assertSee('Nothing is free for those dates')
            ->assertDontSee('Barkada Cottage');

        $this->picker(['type' => 'cottage', 'date' => $visit, 'session' => 'afternoon-swim'])
            ->assertOk()
            ->assertSee('Barkada Cottage');
    }

    public function test_each_service_says_how_much_it_has_free_and_where_else_to_look(): void
    {
        $this->space('room', 'Barkada Room');

        // Nothing is free under cottages, so the guest is told which service is.
        $this->picker(['type' => 'cottage', 'date' => now()->addDays(20)->toDateString(), 'guests' => 2])
            ->assertOk()
            ->assertSee('Nothing is free for those dates')
            ->assertSee('Rooms & suites · 1 free');
    }

    public function test_free_spaces_lead_with_the_ones_that_fit_the_party_then_the_cheapest_rate(): void
    {
        // All three can take four guests; the difference is what that costs. The
        // cosy room includes two, so the other two are charged for.
        $cosy = $this->space('room', 'Cosy Room', capacity: 2, maxCapacity: 4);
        $garden = $this->space('room', 'Garden Room', capacity: 4, maxCapacity: 4);
        $barkada = $this->space('room', 'Barkada Room', capacity: 6, maxCapacity: 6);

        // The cheapest card belongs to the room that would charge for half the
        // party, so it does not lead: the rate on a card is a promise about what
        // the guest pays, and an extra-person charge breaks it.
        $this->rate($cosy, 1000);
        $this->rate($cosy, 500, RateType::ExtraPerson);
        $this->rate($garden, 2000);
        $this->rate($barkada, 3000);

        $this->picker(['type' => 'room', 'guests' => 4])
            ->assertOk()
            ->assertSeeInOrder(['Garden Room', 'Barkada Room', 'Cosy Room'])
            ->assertSee('Extra person');
    }

    public function test_the_card_shows_the_rate_the_order_was_made_of(): void
    {
        $room = $this->space('room', 'Garden Room', capacity: 4, maxCapacity: 4);
        $this->rate($room, 2500);

        $this->picker(['type' => 'room', 'guests' => 4])
            ->assertOk()
            // The figure is asserted the way every other test asserts one: the
            // symbol sits in its own span, so the digits are what is in the HTML.
            ->assertSee('From')
            ->assertSee('2,500.00');
    }

    public function test_a_photo_is_what_breaks_a_tie_at_the_same_fit_and_rate(): void
    {
        $plain = $this->space('room', 'Alpha Room', capacity: 4, maxCapacity: 4);
        $photo = $this->space('room', 'Zebra Room', capacity: 4, maxCapacity: 4);

        $this->rate($plain, 2000);
        $this->rate($photo, 2000);
        GalleryImage::factory()->create(['facility_id' => $photo->id, 'is_active' => true]);

        // Alphabetically Zebra comes last. A card a guest can look at comes first.
        $this->picker(['type' => 'room', 'guests' => 4])
            ->assertOk()
            ->assertSeeInOrder(['Zebra Room', 'Alpha Room']);
    }

    public function test_a_space_with_no_published_rate_ranks_after_one_that_has_a_rate(): void
    {
        $unpriced = $this->space('room', 'Alpha Room', capacity: 4, maxCapacity: 4);
        $priced = $this->space('room', 'Zebra Room', capacity: 4, maxCapacity: 4);

        $this->rate($priced, 5000);

        // An ask-us rate cannot be compared with a published one, so it does not
        // get to pretend it is the cheapest.
        $this->picker(['type' => 'room', 'guests' => 4])
            ->assertOk()
            ->assertSeeInOrder(['Zebra Room', 'Alpha Room'])
            ->assertSee('Ask us for the rate');
    }

    public function test_with_dates_but_no_party_the_cheapest_rate_leads_and_the_page_says_so(): void
    {
        $dear = $this->space('room', 'Alpha Room', capacity: 4, maxCapacity: 4);
        $cheap = $this->space('room', 'Zebra Room', capacity: 4, maxCapacity: 4);

        $this->rate($dear, 4000);
        $this->rate($cheap, 1500);

        $this->picker(['type' => 'room', 'check_in' => '2026-10-12', 'check_out' => '2026-10-13'])
            ->assertOk()
            ->assertSeeInOrder(['Zebra Room', 'Alpha Room']);
    }

    public function test_a_booked_window_suggests_the_nearest_dates_that_are_free(): void
    {
        $room = $this->space('room', 'Barkada Room');
        $this->booking($room, '2026-10-12 14:00', '2026-10-13 12:00');

        $this->picker(['type' => 'room', 'check_in' => '2026-10-12', 'check_out' => '2026-10-13', 'guests' => 2])
            ->assertOk()
            ->assertSee('Nothing is free for those dates')
            ->assertSee('Nearby dates with space')
            // Dated the way every other label on this page is.
            ->assertSee('Tue, 13 Oct 2026 – Wed, 14 Oct 2026')
            // Nearest first, either side: one day later before one day earlier.
            ->assertSeeInOrder(['1 day later', '1 day earlier'])
            // Each suggestion is the same question asked of different days, so the
            // tab and the party travel with it.
            ->assertSee(route('customer.reservations.new', [
                'type' => 'room',
                'check_in' => '2026-10-13',
                'check_out' => '2026-10-14',
                'guests' => 2,
            ]));
    }

    public function test_a_suggestion_is_counted_for_the_party_and_the_service_asked_about(): void
    {
        $this->space('room', 'Couple Room', capacity: 2, maxCapacity: 2);
        $big = $this->space('room', 'Barkada Room', capacity: 4, maxCapacity: 6);
        $this->booking($big, '2026-10-12 14:00', '2026-10-13 12:00');

        // A party of 3: the couple room never fits, so the only space the picker
        // can offer is the barkada room, on the days either side of the stay.
        $this->picker(['type' => 'room', 'check_in' => '2026-10-12', 'check_out' => '2026-10-13', 'guests' => 3])
            ->assertOk()
            ->assertSee('1 free')
            ->assertDontSee('2 free')
            ->assertSee(route('customer.reservations.new', [
                'type' => 'room',
                'check_in' => '2026-10-13',
                'check_out' => '2026-10-14',
                'guests' => 3,
            ]));
    }

    public function test_a_cottage_suggestion_keeps_the_swimming_session(): void
    {
        $this->swimSession('Morning swim', 'morning-swim', '08:00', '12:00');
        $cottage = $this->space('cottage', 'Barkada Cottage');

        $visit = '2026-10-12';
        $this->booking($cottage, $visit.' 08:00', $visit.' 12:00');

        // The cottage is taken for the morning swim on the visit date, and free the
        // day after — which is only a useful suggestion if it keeps the session.
        $this->picker(['type' => 'cottage', 'date' => $visit, 'session' => 'morning-swim', 'guests' => 2])
            ->assertOk()
            ->assertSee('Visit Tue, 13 Oct')
            ->assertSee(route('customer.reservations.new', [
                'type' => 'cottage',
                'check_in' => '2026-10-13',
                'check_out' => '2026-10-13',
                'session' => 'morning-swim',
                'guests' => 2,
            ]));
    }

    public function test_the_days_either_side_are_checked_before_the_picker_says_nothing_will_do(): void
    {
        $room = $this->space('room', 'Barkada Room');

        // Booked for the window asked about and for every day the picker is
        // willing to suggest, so the honest answer is "these dates and the days
        // around them".
        foreach ([
            ['2026-10-09', '2026-10-10'],
            ['2026-10-10', '2026-10-11'],
            ['2026-10-11', '2026-10-12'],
            ['2026-10-12', '2026-10-13'],
            ['2026-10-13', '2026-10-14'],
            ['2026-10-14', '2026-10-15'],
            ['2026-10-15', '2026-10-16'],
        ] as [$checkIn, $checkOut]) {
            $this->booking($room, $checkIn.' 14:00', $checkOut.' 12:00');
        }

        $this->picker(['type' => 'room', 'check_in' => '2026-10-12', 'check_out' => '2026-10-13', 'guests' => 2])
            ->assertOk()
            ->assertSee('Nothing is free for those dates')
            ->assertSee('3 days either side are taken too')
            ->assertDontSee('Nearby dates with space');
    }

    public function test_a_service_with_nothing_published_is_not_told_its_neighbours_are_booked(): void
    {
        // No cottages exist at all. "The days either side are taken" would be a
        // plain lie here — there is nothing to take.
        $this->picker(['type' => 'cottage', 'date' => '2026-10-12', 'guests' => 2])
            ->assertOk()
            ->assertSee('Nothing is free for those dates')
            ->assertDontSee('days either side are taken too')
            ->assertDontSee('Nearby dates with space');
    }

    public function test_a_list_with_something_in_it_suggests_nothing(): void
    {
        $this->space('room', 'Free Room');

        // Nothing was refused, so there is nothing to make up for — and no nearby
        // search was paid for.
        $this->picker(['type' => 'room', 'check_in' => '2026-10-12', 'check_out' => '2026-10-13', 'guests' => 2])
            ->assertOk()
            ->assertSee('Free Room')
            ->assertDontSee('Nearby dates with space');
    }

    public function test_carried_dates_reach_the_space_page_with_the_filter(): void
    {
        $room = $this->space('room', 'Free Room');

        // Escaped like the page writes it, which is also the assertion that it is
        // an `href` a browser will follow.
        $this->picker(['type' => 'room', 'check_in' => '2026-10-12', 'check_out' => '2026-10-13', 'guests' => 2])
            ->assertOk()
            ->assertSee(route('customer.facilities.show', [
                'facility' => $room,
                'check_in' => '2026-10-12',
                'check_out' => '2026-10-13',
                'guests' => 2,
            ]));
    }

    public function test_switching_tabs_translates_the_filter_instead_of_dropping_it(): void
    {
        $this->space('room', 'Barkada Room');

        // A cottage visit date is one day; the rooms tab asks it as a day-use stay.
        // The keys are listed in the order the links read them, so this is the
        // exact address the tab hands over.
        $this->picker(['type' => 'cottage', 'date' => '2026-10-12', 'guests' => 2])
            ->assertOk()
            ->assertSee(route('customer.reservations.new', [
                'check_in' => '2026-10-12',
                'guests' => 2,
                'type' => 'room',
            ]));
    }

    public function test_a_filter_the_picker_cannot_read_is_refused_rather_than_guessed(): void
    {
        $this->space('room', 'Barkada Room');

        $this->picker(['type' => 'room', 'check_in' => 'not-a-date'])->assertStatus(302);

        // A session nobody has would otherwise filter every cottage away, which
        // reads as "the resort is full" instead of "that link is wrong".
        $this->picker(['type' => 'cottage', 'date' => now()->addDays(20)->toDateString(), 'session' => 'no-such-swim'])
            ->assertStatus(302);
    }

    public function test_the_picker_and_the_availability_search_agree_on_what_is_free(): void
    {
        $free = $this->space('room', 'Free Room');
        $booked = $this->space('room', 'Booked Room');

        $this->booking($booked, '2026-10-12 14:00', '2026-10-13 12:00');

        $search = $this->get(route('availability.search', [
            'service_type' => 'room',
            'check_in' => '2026-10-12',
            'check_out' => '2026-10-13',
            'guests' => 2,
        ]))->assertOk();

        $searched = collect($search->json('results'))->pluck('id');

        $this->assertContains($free->id, $searched->all());
        $this->assertNotContains($booked->id, $searched->all());

        $this->picker(['type' => 'room', 'check_in' => '2026-10-12', 'check_out' => '2026-10-13', 'guests' => 2])
            ->assertOk()
            ->assertSee('Free Room')
            ->assertDontSee('Booked Room');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function picker(array $query): TestResponse
    {
        return $this->actingAs($this->customer)->get(route('customer.reservations.new', $query));
    }

    /**
     * A published, bookable space of one service.
     *
     * The factory's random status is the one thing these tests must not inherit:
     * "can this be booked for these dates" is the question under test, so the
     * space starts bookable and each test withdraws it deliberately.
     */
    private function space(
        string $typeSlug,
        string $name,
        int $capacity = 2,
        ?int $maxCapacity = 4,
        FacilityStatus $status = FacilityStatus::Available,
    ): Facility {
        return Facility::factory()->create([
            'facility_type_id' => $this->type($typeSlug)->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
            'is_listed' => true,
            'status' => $status->value,
            'capacity' => $capacity,
            'max_capacity' => $maxCapacity,
        ])->load('facilityType');
    }

    /**
     * A booking that takes the space off the market for that window. `Approved` is
     * the status the guest portal calls "Confirmed" — one of the two the availability
     * rules treat as occupying the space.
     */
    private function booking(Facility $facility, string $checkIn, string $checkOut): Reservation
    {
        return Reservation::factory()->create([
            'facility_id' => $facility->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'status' => ReservationStatus::Approved->value,
        ]);
    }

    /**
     * A published rate, in pesos. `ExtraPerson` is what a party larger than the
     * space includes is charged, and what keeps a cheap card from leading.
     */
    private function rate(Facility $facility, float $amount, RateType $type = RateType::Base): Rate
    {
        return Rate::factory()->create([
            'facility_id' => $facility->id,
            'type' => $type->value,
            'amount' => $amount,
            'unit' => 'per night',
            'is_active' => true,
        ]);
    }

    private function swimSession(string $name, string $slug, string $startsAt, string $endsAt): SwimmingSession
    {
        return SwimmingSession::query()->create([
            'name' => $name,
            'slug' => $slug,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'adult_entrance_fee' => 150,
            'cottage_fee' => 500,
            'is_active' => true,
        ]);
    }

    private function type(string $slug): FacilityType
    {
        return FacilityType::query()->where('slug', $slug)->firstOrFail();
    }
}
