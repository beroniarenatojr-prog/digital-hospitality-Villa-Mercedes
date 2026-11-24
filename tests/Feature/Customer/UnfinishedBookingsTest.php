<?php

namespace Tests\Feature\Customer;

use App\Enums\FacilityStatus;
use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Enums\RateType;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Rate;
use App\Models\User;
use App\Services\BookingResumeLink;
use App\Support\BookingDraft;
use Database\Seeders\FacilityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The booking a guest abandoned, on the page where they look for their bookings.
 *
 * A draft used to be reachable only by opening that space's wizard again, which
 * means remembering which space it was — so the guest who walked away from the
 * confirm step had no way of finding the work they had already done. "My
 * reservations" is the page they check, so the unfinished booking is listed
 * there, said plainly as *not* a reservation: nothing submitted, nothing held,
 * no deposit owed.
 *
 * It is also the one list that reads both places a draft can live, and this file
 * is mostly about what each of them is worth: the browser's own copy is the
 * fresher one, the emailed link is the one that survives a closed laptop, and a
 * booking that can no longer be finished is not offered at all.
 */
class UnfinishedBookingsTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Facility $room;

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

        $this->room = $this->facility('room');
    }

    public function test_a_booking_the_guest_walked_away_from_is_listed_as_not_a_reservation(): void
    {
        $this->actingAs($this->customer);
        $this->priceAStep(2)->assertOk();

        $response = $this->get(route('customer.reservations.index'));

        $response->assertOk()
            ->assertSee('Unfinished booking')
            ->assertSee('Not submitted')
            ->assertSee($this->room->name)
            ->assertSee('Guest details to finish')
            ->assertSee('nothing is held and no deposit is due')
            ->assertSee('Continue this booking')
            ->assertSee('Discard');

        // It is not dressed up as one of the bookings in the tabs.
        $response->assertDontSee('remaining of');
    }

    public function test_the_guest_can_open_it_again_from_the_list(): void
    {
        $this->actingAs($this->customer);
        $this->priceAStep(2)->assertOk();

        $response = $this->get(route('customer.reservations.index'));
        $response->assertSee('href="'.route('customer.booking.create', $this->room).'"', false);

        // The offer is real: the link opens the wizard on the booking they left,
        // not on a blank step 1.
        $config = $this->wizardConfig($this->followingRedirects()->get(route('customer.booking.create', $this->room)));

        $this->assertTrue($config['resumed']);
        $this->assertSame(2, $config['startStep']);
        $this->assertSame($this->arrival(), $config['prefill']['check_in']);
    }

    public function test_a_booking_whose_browser_is_gone_is_still_listed_behind_its_emailed_link(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->priceAStep(BookingResumeLink::CONFIRM_STEP)->assertOk();

        // The session that held it is gone — another device, a closed laptop. The
        // link emailed to the guest is the only copy left.
        BookingDraft::forgetSession($this->room->id);
        $this->assertNull($this->draft(), 'The draft survived the browser it belonged to.');

        $response = $this->get(route('customer.reservations.index'));

        $response->assertOk()
            ->assertSee('Unfinished booking')
            ->assertSee('Ready to confirm')
            ->assertSee('Finish this booking');

        $token = app(BookingResumeLink::class)->armedFor($this->customer->id)[$this->room->id]['token'] ?? null;
        $this->assertIsString($token, 'Nothing was armed to come back to.');
        $response->assertSee('href="'.route('customer.booking.resume', ['token' => $token]).'"', false);

        // Following it is what puts the booking back into this browser.
        $config = $this->wizardConfig($this->followingRedirects()
            ->get(route('customer.booking.resume', ['token' => $token])));

        $this->assertTrue($config['resumed']);
        $this->assertSame(BookingResumeLink::CONFIRM_STEP, $config['startStep']);
    }

    public function test_the_fresher_copy_in_this_browser_is_the_one_offered(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->priceAStep(BookingResumeLink::CONFIRM_STEP)->assertOk();

        // The guest stays on the page and moves back to the guest sheet: the link
        // still describes the confirm-step booking, but what they are working on is
        // in front of them.
        $this->priceAStep(2, ['special_requests' => 'Changed my mind about the dates.'])->assertOk();

        $this->get(route('customer.reservations.index'))
            ->assertOk()
            // The wizard can read this browser's copy directly, so the card sends
            // them there rather than through the email link.
            ->assertSee('href="'.route('customer.booking.create', $this->room).'"', false);
    }

    public function test_companion_names_are_counted_because_that_is_the_work_worth_saving(): void
    {
        $this->actingAs($this->customer);
        $this->priceAStep(BookingResumeLink::CONFIRM_STEP, [
            'other_guest_names' => [
                ['name' => 'Ana Dela Cruz', 'valid_id' => GuestIdType::PhilSys->value],
                ['name' => 'Ben Uy', 'valid_id' => GuestIdType::PhilSys->value],
                ['name' => '', 'valid_id' => null],
            ],
        ])->assertOk();

        $this->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertSee('Companions saved')
            ->assertSee('2');
    }

    public function test_discarding_it_from_the_list_clears_both_copies(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->priceAStep(BookingResumeLink::CONFIRM_STEP)->assertOk();

        $this->delete(route('customer.booking.draft.destroy', $this->room))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull($this->draft());
        $this->assertSame([], app(BookingResumeLink::class)->armedFor($this->customer->id));

        // ...and the card is gone with it, rather than offering a link that would
        // land the guest on an expired-link apology. (The card's own words, not the
        // heading: the flash message from the discard says "Unfinished booking"
        // too, so the heading is still on the page this request.)
        $this->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertDontSee('Not submitted')
            ->assertDontSee('Continue this booking');
    }

    public function test_a_booking_whose_dates_have_passed_is_not_offered(): void
    {
        $this->actingAs($this->customer);

        $this->priceAStep(2, [
            'check_in' => now()->addDays(2)->setTime(14, 0)->format('Y-m-d H:i'),
            'check_out' => now()->addDays(3)->setTime(12, 0)->format('Y-m-d H:i'),
        ])->assertOk();

        $this->travelTo(now()->addDays(7));

        // Those dates are history, so there is nothing here to finish — and the
        // wizard would refuse them anyway.
        $this->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertDontSee('Unfinished booking');
    }

    public function test_a_space_that_is_not_bookable_online_is_not_offered_but_its_draft_survives(): void
    {
        $this->actingAs($this->customer);
        $this->priceAStep(2)->assertOk();

        // The resort takes the space offline: not something this list can honestly
        // offer a way back to, and not the guest's fault either.
        $this->room->update(['is_active' => false]);

        $this->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertDontSee('Unfinished booking');

        // It is only hidden. A space goes offline and comes back, and the guest's
        // typing should outlive that.
        $this->assertNotNull($this->draft());
    }

    public function test_a_cottage_draft_is_not_offered_by_the_wizard_list(): void
    {
        $this->actingAs($this->customer);

        $cottage = $this->facility('cottage');

        // Cottages are booked into a swimming session, which the wizard never asks
        // for — a cottage guest finishes on the resort page instead, so this list
        // has no way back to offer them.
        BookingDraft::remember($cottage, [
            'check_in' => $this->arrival(),
            'check_out' => $this->departure(),
            'guestCount' => 2,
        ], 2);

        $this->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertDontSee('Unfinished booking');
    }

    public function test_another_account_never_sees_it(): void
    {
        $this->actingAs($this->customer);
        $this->priceAStep(2)->assertOk();

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertDontSee('Unfinished booking');
    }

    public function test_a_guest_with_nothing_unfinished_sees_no_such_section(): void
    {
        $this->actingAs($this->customer)
            ->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertDontSee('Unfinished booking')
            ->assertDontSee('Not submitted');
    }

    /**
     * One priced step, exactly as the wizard's `refreshQuote` sends it.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function priceAStep(int $step, array $overrides = []): TestResponse
    {
        return $this->postJson(route('customer.booking.calculate', $this->room), array_merge([
            'check_in' => $this->arrival(),
            'check_out' => $this->departure(),
            'guestCount' => 2,
            'step' => $step,
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

    /** The draft this session is holding for the room, or null. */
    private function draft(): ?array
    {
        $draft = session(BookingDraft::key($this->room));

        return is_array($draft) ? $draft : null;
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

    private function facility(string $typeSlug): Facility
    {
        $type = FacilityType::query()->where('slug', $typeSlug)->firstOrFail();

        $facility = Facility::factory()->create([
            'facility_type_id' => $type->id,
            'is_active' => true,
            'is_listed' => true,
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
