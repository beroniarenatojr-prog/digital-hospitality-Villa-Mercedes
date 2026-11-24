<?php

namespace Tests\Feature\Customer;

use App\Enums\FacilityStatus;
use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Enums\RateType;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\User;
use App\Support\BookingDraft;
use Database\Seeders\FacilityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Leaving the wizard without losing the work.
 *
 * The wizard is three steps of typing, and the guest who walks away from it mid
 * flow used to come back to a blank step 1: the dates they had already decided
 * on, the party size, the extra hours and every companion name lived in the
 * browser only. What is kept instead is a draft in the session of the account
 * that made it — offered back when they return, re-priced rather than trusted,
 * and removable in one click.
 */
class BookingDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Facility $room;

    private Facility $otherRoom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FacilityTypeSeeder::class);

        $this->customer = User::factory()->create([
            'name' => 'Maria Santos',
            'gender' => GuestGender::Female,
            'address' => 'Barangay Namnama, City of Ilagan, Isabela',
            'nationality' => 'Filipino',
            'valid_id_type' => GuestIdType::PhilSys,
            'phone' => '09171234567',
            'email' => 'maria.santos@example.com',
        ]);

        $this->room = $this->facility();
        $this->otherRoom = $this->facility();
    }

    public function test_a_guest_who_leaves_on_the_guest_sheet_comes_back_to_their_dates(): void
    {
        $this->actingAs($this->customer);

        $this->priceAStep()->assertOk();

        $response = $this->get(route('customer.booking.create', $this->room));
        $config = $this->wizardConfig($response);

        $this->assertTrue($config['resumed'], 'The wizard did not offer the unfinished booking back.');
        $this->assertSame(2, $config['startStep'], 'The guest was not returned to the step they left.');
        $this->assertSame($this->arrival(), $config['prefill']['check_in']);
        $this->assertSame($this->departure(), $config['prefill']['check_out']);
        $this->assertSame(2, $config['prefill']['guest_count']);

        // The page says what it did, and offers the one click that undoes it.
        $response->assertSee('Picking up where you left off');
        $response->assertSee('Start over');
    }

    public function test_companions_and_preferences_survive_leaving_on_the_confirm_step(): void
    {
        $this->actingAs($this->customer);

        $this->priceAStep([
            'step' => 3,
            'other_guest_names' => [
                ['name' => 'Ana Dela Cruz', 'valid_id' => GuestIdType::PhilSys->value],
                // A row the guest opened and never filled in: dropped, not stored.
                ['name' => '', 'valid_id' => null],
            ],
            'special_requests' => 'Ground floor please, arriving with a toddler.',
        ])->assertOk();

        $config = $this->wizardConfig($this->get(route('customer.booking.create', $this->room)));

        $this->assertSame(3, $config['startStep']);
        $this->assertSame(
            [['name' => 'Ana Dela Cruz', 'valid_id' => GuestIdType::PhilSys->value]],
            $config['guestPrefill']['other_guest_names']
        );
        $this->assertSame('Ground floor please, arriving with a toddler.', $config['guestPrefill']['special_requests']);
    }

    public function test_the_extra_hours_a_guest_asked_for_are_remembered(): void
    {
        $this->actingAs($this->customer);

        $this->priceAStep(['early_check_in_hours' => 2, 'late_check_out_hours' => 0])->assertOk();

        $config = $this->wizardConfig($this->get(route('customer.booking.create', $this->room)));

        $this->assertSame(2, $config['prefill']['early_check_in_hours']);
        $this->assertSame(0, $config['prefill']['late_check_out_hours']);
    }

    public function test_a_link_that_carries_its_own_dates_starts_a_fresh_booking(): void
    {
        $this->actingAs($this->customer);

        $this->priceAStep(['step' => 3])->assertOk();

        $newArrival = now()->addDays(20)->toDateString();

        $response = $this->get(route('customer.booking.create', [
            'facility' => $this->room,
            'check_in' => $newArrival,
            'check_out' => now()->addDays(21)->toDateString(),
            'availability_checked' => 1,
        ]));
        $config = $this->wizardConfig($response);

        // The link is the newest thing the guest said, so it wins.
        $this->assertFalse($config['resumed']);
        $this->assertSame(1, $config['startStep']);
        $this->assertStringStartsWith($newArrival, (string) $config['prefill']['check_in']);
        $response->assertDontSee('Picking up where you left off');

        // ...and the draft is left alone rather than thrown away: it may still be
        // what the guest wants if this link turns out to be a look around, so
        // asking for the wizard again without dates still offers it back.
        $this->assertNotNull($this->draft());
        $this->assertTrue($this->wizardConfig($this->get(route('customer.booking.create', $this->room)))['resumed']);
    }

    public function test_a_draft_belongs_to_one_space(): void
    {
        $this->actingAs($this->customer);

        $this->priceAStep()->assertOk();

        $config = $this->wizardConfig($this->get(route('customer.booking.create', $this->otherRoom)));

        $this->assertFalse($config['resumed']);
        $this->assertNull($config['prefill']['check_in']);
    }

    public function test_a_draft_whose_arrival_has_passed_is_dropped(): void
    {
        $this->actingAs($this->customer);

        $this->priceAStep([
            'check_in' => now()->addDays(2)->setTime(14, 0)->format('Y-m-d H:i'),
            'check_out' => now()->addDays(3)->setTime(12, 0)->format('Y-m-d H:i'),
        ])->assertOk();

        // The guest comes back a week later: those dates are now history, and the
        // wizard must not open on a selection the server would refuse.
        $this->travelTo(now()->addDays(7));

        $config = $this->wizardConfig($this->get(route('customer.booking.create', $this->room)));

        $this->assertFalse($config['resumed']);
        $this->assertNull($this->draft(), 'A draft that can never be resumed should not be kept.');
    }

    public function test_an_unavailable_selection_still_keeps_the_work(): void
    {
        // Someone else already holds the room for those dates.
        Reservation::factory()->create([
            'facility_id' => $this->room->id,
            'check_in' => now()->addDays(10)->setTime(14, 0),
            'check_out' => now()->addDays(12)->setTime(12, 0),
            'status' => ReservationStatus::Approved->value,
        ]);

        $this->actingAs($this->customer);

        $this->priceAStep()->assertStatus(422);

        $config = $this->wizardConfig($this->get(route('customer.booking.create', $this->room)));

        // Coming back to their dates and the reason they were refused beats coming
        // back to an empty form.
        $this->assertTrue($config['resumed']);
        $this->assertSame($this->arrival(), $config['prefill']['check_in']);
    }

    public function test_confirming_a_reservation_clears_the_draft(): void
    {
        $this->actingAs($this->customer);

        $this->priceAStep(['step' => 3])->assertOk();
        $this->assertNotNull($this->draft());

        $this->post(route('customer.reservations.store'), [
            'facility_id' => $this->room->id,
            'check_in' => $this->arrival(),
            'check_out' => $this->departure(),
            'guestCount' => 2,
        ] + $this->guestInformationPayload())->assertRedirect();

        $this->assertNull($this->draft(), 'A finished booking must not fill the next booking in.');

        $this->get(route('customer.booking.create', $this->room))
            ->assertDontSee('Picking up where you left off');
    }

    public function test_starting_over_clears_the_draft(): void
    {
        $this->actingAs($this->customer);

        $this->priceAStep()->assertOk();

        $this->get(route('customer.booking.create', ['facility' => $this->room, 'fresh' => 1]))
            ->assertRedirect(route('customer.booking.create', $this->room));

        $this->assertNull($this->draft());

        $this->get(route('customer.booking.create', $this->room))
            ->assertDontSee('Picking up where you left off');
    }

    public function test_a_visitor_who_is_not_signed_in_leaves_no_draft(): void
    {
        // The public availability bar prices a selection for a browsing guest.
        // There is no account to come back to, so no party names are kept.
        $this->postJson(route('facilities.availability.calculate', $this->room), [
            'check_in' => $this->arrival(),
            'check_out' => $this->departure(),
            'guestCount' => 2,
        ])->assertOk();

        $this->assertNull($this->draft());
    }

    /**
     * One priced step, exactly as the wizard's own `refreshQuote` sends it.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function priceAStep(array $overrides = []): TestResponse
    {
        return $this->postJson(route('customer.booking.calculate', $this->room), array_merge([
            'check_in' => $this->arrival(),
            'check_out' => $this->departure(),
            'guestCount' => 2,
            'step' => 2,
        ], $overrides));
    }

    private function arrival(): string
    {
        return now()->addDays(10)->setTime(14, 0)->format('Y-m-d H:i');
    }

    private function departure(): string
    {
        return now()->addDays(12)->setTime(12, 0)->format('Y-m-d H:i');
    }

    /**
     * The wizard's configuration, as the page hands it to the date pickers.
     *
     * @return array<string, mixed>
     */
    private function wizardConfig(TestResponse $response): array
    {
        $matched = preg_match(
            '/id="booking-wizard-config">(.*?)<\/script>/s',
            (string) $response->getContent(),
            $matches
        );

        $this->assertSame(1, $matched, 'The booking wizard did not render its configuration.');

        return json_decode(trim($matches[1]), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * The draft this session is holding for the room, read the way the app reads
     * it. `BookingDraft::key` names the slot by account and space, so the same
     * helper answers "is there one?" for a signed-in guest and for a visitor.
     *
     * @return array<string, mixed>|null
     */
    private function draft(): ?array
    {
        $draft = session(BookingDraft::key($this->room));

        return is_array($draft) ? $draft : null;
    }

    private function facility(): Facility
    {
        $type = FacilityType::query()->where('slug', 'room')->firstOrFail();

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
        Rate::factory()->create([
            'facility_id' => $facility->id,
            'name' => \App\Services\PricingService::EARLY_CHECK_IN_RATE_NAME,
            'type' => RateType::AdditionalCharge->value,
            'amount' => 200,
            'unit' => 'per hour',
            'is_active' => true,
        ]);

        return $facility;
    }
}
