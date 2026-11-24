<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Customer\CustomerReservationService;
use App\Services\GuestInformationSheetNumber;
use App\Services\ReservationSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReservationScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-20 00:00:00'));
        $this->seed();
        $this->guest = User::factory()->create();
        $this->guest->assignRole(Role::Customer->value);
    }

    private function facility(string $slug): Facility
    {
        return Facility::where('slug', $slug)->firstOrFail();
    }

    private function quote(Facility $facility, string $start, string $end, array $extra = [])
    {
        return $this->actingAs($this->guest)->postJson(route('customer.booking.calculate', $facility), [
            'check_in' => '2026-11-10 '.$start, 'check_out' => '2026-11-10 '.$end, 'guestCount' => 2, ...$extra,
        ]);
    }

    public function test_available_overnight_room_can_be_requested_after_two_pm(): void
    {
        $this->travelTo(Carbon::parse('2026-11-13 16:00', 'Asia/Manila'));
        $room = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', 'room'))->firstOrFail();
        $start = Carbon::parse('2026-11-13 14:00');
        $end = Carbon::parse('2026-11-14 12:00');
        app(ReservationSchedule::class)->validate($room, $start, $end);
        $deadline = \App\Support\DepositDeadline::forRoomStay($start, $end, true);
        $this->assertTrue($deadline->isFuture());
        $this->assertTrue($deadline->lte(\App\Support\DepositDeadline::visitInstant($end)));
        $this->actingAs($this->guest)->postJson(route('customer.booking.calculate', $room), [
            'check_in' => '2026-11-13 14:00', 'check_out' => '2026-11-14 12:00', 'guestCount' => 2,
        ])->assertOk();
        // At 11 PM Manila it is already after 2 PM UTC as well: a generic
        // after:now request rule would wrongly reject this valid room stay.
        $this->travelTo(Carbon::parse('2026-11-13 23:00', 'Asia/Manila'));
        $this->actingAs($this->guest)->postJson(route('customer.booking.calculate', $room), [
            'check_in' => '2026-11-13 14:00', 'check_out' => '2026-11-14 12:00', 'guestCount' => 2,
        ])->assertOk();
    }

    public function test_late_room_booking_still_rejects_conflicts_cleaning_and_past_dates(): void
    {
        $this->travelTo(Carbon::parse('2026-11-13 16:00', 'Asia/Manila'));
        $room = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', 'room'))->firstOrFail();
        $payload = ['check_in' => '2026-11-13 14:00', 'check_out' => '2026-11-14 12:00', 'guestCount' => 2];
        $quote = fn ($data) => $this->actingAs($this->guest)->postJson(route('customer.booking.calculate', $room), $data);
        $quote([...$payload, 'check_in' => '2026-11-12 14:00'])->assertUnprocessable();
        $quote([...$payload, 'check_out' => '2026-11-13 17:00', 'check_in' => '2026-11-13 09:00'])->assertUnprocessable();
        $quote([...$payload, 'early_check_in_hours' => 1])->assertUnprocessable();
        $room->update(['cleaning_required_at' => now()]);
        $quote($payload)->assertUnprocessable();
        $room->update(['cleaning_required_at' => null]);
        Reservation::factory()->create([
            'facility_id' => $room->id, 'status' => ReservationStatus::Approved,
            'check_in' => $payload['check_in'], 'check_out' => $payload['check_out'],
        ]);
        $quote($payload)->assertUnprocessable();
    }

    public function test_late_room_exception_uses_manila_day_and_does_not_apply_to_halls(): void
    {
        $room = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', 'room'))->firstOrFail();
        $this->travelTo(Carbon::parse('2026-11-13 23:59', 'Asia/Manila'));
        app(ReservationSchedule::class)->validate($room, Carbon::parse('2026-11-13 14:00'), Carbon::parse('2026-11-14 12:00'));
        $this->travelTo(Carbon::parse('2026-11-14 00:01', 'Asia/Manila'));
        $this->actingAs($this->guest)->postJson(route('customer.booking.calculate', $room), [
            'check_in' => '2026-11-13 14:00', 'check_out' => '2026-11-14 12:00', 'guestCount' => 2,
        ])->assertUnprocessable();
        $this->travelTo(Carbon::parse('2026-11-13 16:00', 'Asia/Manila'));
        $this->expectException(ValidationException::class);
        app(ReservationSchedule::class)->validate($this->facility('diamond-hall'), Carbon::parse('2026-11-13 14:00'), Carbon::parse('2026-11-13 18:00'));
    }

    public function test_hall_extensions_have_their_own_quote_and_saved_snapshot(): void
    {
        foreach (['diamond-hall' => 500000, 'sapphire-hall' => 250000, 'emerald-hall' => 300000] as $slug => $cents) {
            $hall = $this->facility($slug);
            $this->quote($hall, '16:00', '22:00')->assertOk()
                ->assertJsonPath('data.hall_extension_charge', 2 * $cents)
                ->assertJsonPath('data.late_check_out_charge', 0);
            $reservation = app(CustomerReservationService::class)->createReservation(
                $this->guest, $hall, Carbon::parse('2026-11-10 16:00'), Carbon::parse('2026-11-10 22:00'), 2
            );
            $this->assertEquals(2 * $cents / 100, $reservation->hall_extension_charge);
            $this->assertEquals($reservation->base_price + $reservation->hall_extension_charge, $reservation->total_price);
            $this->assertSame(0, $reservation->late_check_out_hours);
        }
    }

    public function test_invalid_hall_windows_and_guest_late_hours_are_rejected(): void
    {
        $hall = $this->facility('diamond-hall');
        foreach ([['08:00', '12:00'], ['19:00', '23:00'], ['09:00', '12:00'], ['09:00', '13:30']] as [$start, $end]) {
            $this->quote($hall, $start, $end)->assertUnprocessable();
        }
        $this->quote($hall, '18:00', '22:00')->assertOk()->assertJsonPath('data.hall_extension_charge', 0);
        $this->quote($hall, '09:00', '13:00', ['late_check_out_hours' => 1])->assertUnprocessable();
        $this->quote($hall, '09:00', '13:00', ['early_check_in_hours' => 1])->assertUnprocessable();
    }

    public function test_opal_uses_only_its_two_slots(): void
    {
        $opal = $this->facility('opal-hall');
        $this->quote($opal, '09:00', '17:00')->assertOk()->assertJsonPath('data.hall_extension_charge', 0);
        $this->quote($opal, '17:00', '22:00')->assertOk();
        $this->quote($opal, '09:00', '13:00')->assertUnprocessable();
        $this->quote($opal, '17:00', '23:00')->assertUnprocessable();
    }

    public function test_room_fixed_times_and_early_occupancy_are_enforced(): void
    {
        $room = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', 'room'))->firstOrFail();
        Reservation::factory()->create([
            'facility_id' => $room->id, 'status' => ReservationStatus::Approved,
            // Legacy booking occupies the room until 1 PM: one hour early is
            // safe at the boundary, while a noon arrival still overlaps it.
            'check_in' => '2026-11-09 14:00', 'check_out' => '2026-11-10 12:00', 'late_check_out_hours' => 1,
        ]);
        $extra = ['check_out' => '2026-11-11 12:00'];
        $this->quote($room, '14:00', '12:00', $extra)->assertOk();
        $this->quote($room, '14:00', '12:00', [...$extra, 'early_check_in_hours' => 1])->assertOk();
        $this->quote($room, '14:00', '12:00', [...$extra, 'early_check_in_hours' => 2])->assertUnprocessable()->assertJsonPath('success', false);
        $this->quote($room, '09:00', '12:00', $extra)->assertUnprocessable();
        $this->quote($room, '14:00', '12:00', [...$extra, 'early_check_in_hours' => 3])->assertUnprocessable();
        $this->quote($room, '14:00', '12:00', [...$extra, 'late_check_out_hours' => 1])->assertUnprocessable();
    }

    public function test_package_schedule_is_preserved_and_sapphire_extensions_block_it(): void
    {
        $package = Facility::where('name', 'Sapphire Hall + Pool + Jacuzzi (Night Use)')->firstOrFail();
        $tier = $package->paxTiers()[0];
        $this->quote($package, '17:00', '22:00', ['pax_tier' => $tier])->assertOk();
        $this->quote($package, '14:00', '22:00', ['pax_tier' => $tier])->assertUnprocessable();
        app(CustomerReservationService::class)->createReservation($this->guest, $this->facility('sapphire-hall'), Carbon::parse('2026-11-10 12:00'), Carbon::parse('2026-11-10 18:00'), 2);
        $this->quote($package, '17:00', '22:00', ['pax_tier' => $tier])->assertUnprocessable();
    }

    public function test_rescheduling_reprices_extensions_and_rejects_invalid_times(): void
    {
        $hall = $this->facility('diamond-hall');
        $service = app(CustomerReservationService::class);
        $reservation = $service->createReservation($this->guest, $hall, Carbon::parse('2026-11-10 09:00'), Carbon::parse('2026-11-10 13:00'), 2);
        $updated = $service->rescheduleReservation($this->guest, $reservation, $hall, Carbon::parse('2026-11-11 14:00'), Carbon::parse('2026-11-11 20:00'), 2);
        $this->assertEquals(0, $updated->hall_extension_charge, 'A proposed reschedule must not alter the saved quote.');
        $this->assertSame('proposed', $updated->booking_workflow['change']['status']);
        $practices = app(\App\Services\BookingPracticeService::class);
        $practices->acceptChange($updated, $this->guest, $updated->booking_workflow['change']['quote_cents']);
        $practices->confirmChange($updated, User::role(Role::Administrator->value)->firstOrFail());
        $this->assertEquals(10000, $updated->fresh()->hall_extension_charge);
        $this->expectException(ValidationException::class);
        $service->rescheduleReservation($this->guest, $updated, $hall, Carbon::parse('2026-11-12 19:00'), Carbon::parse('2026-11-12 23:00'), 2);
    }

    public function test_new_booking_cannot_bypass_schedule_validation(): void
    {
        $this->expectException(ValidationException::class);
        app(CustomerReservationService::class)->createReservation($this->guest, $this->facility('opal-hall'), Carbon::parse('2026-11-10 09:00'), Carbon::parse('2026-11-10 13:00'), 2);
    }

    public function test_issued_sheet_numbers_start_at_one_and_reprints_keep_them(): void
    {
        $issuer = app(GuestInformationSheetNumber::class);
        $one = Reservation::factory()->create(['status' => ReservationStatus::Approved]);
        $two = Reservation::factory()->create(['status' => ReservationStatus::Approved]);
        $read = fn ($reservation) => $reservation->guest_sheet_control_number;
        $this->assertSame(1, $issuer->issue($two, $read));
        $this->assertSame(2, $issuer->issue($one, $read));
        $this->assertSame(1, $issuer->issue($two, $read));
        $this->assertEquals(3, DB::table('document_sequences')->value('next_number'));
    }

    public function test_public_sign_in_handoff_preserves_early_arrival_and_package_tier(): void
    {
        $room = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', 'room'))->firstOrFail();
        $response = $this->postJson(route('reservation.intent'), [
            'facility_id' => $room->id, 'arrival' => '2026-11-10 14:00', 'departure' => '2026-11-11 12:00',
            'guests' => 2, 'early_check_in_hours' => 2,
        ])->assertOk();
        parse_str(parse_url($response->json('redirect'), PHP_URL_QUERY), $query);
        $this->assertSame('2', $query['early_check_in_hours']);
        $this->assertSame('2026-11-10 14:00', $query['arrival']);

        $package = Facility::where('name', 'Sapphire Hall + Pool + Jacuzzi (Night Use)')->firstOrFail();
        $tier = $package->paxTiers()[0];
        $response = $this->postJson(route('reservation.intent'), [
            'facility_id' => $package->id, 'arrival' => '2026-11-10 17:00', 'departure' => '2026-11-10 22:00',
            'guests' => 2, 'pax_tier' => $tier,
        ])->assertOk();
        parse_str(parse_url($response->json('redirect'), PHP_URL_QUERY), $query);
        $this->assertSame((string) $tier, $query['pax_tier']);
    }

    public function test_hall_draft_retains_extension_hours_and_notes(): void
    {
        $hall = $this->facility('diamond-hall');
        $this->quote($hall, '16:00', '22:00', ['step' => 2, 'special_requests' => 'Please confirm the extension.'])->assertOk();
        $response = $this->get(route('customer.booking.create', $hall))->assertOk();
        preg_match('/<script type="application\/json" id="booking-wizard-config">(.*?)<\/script>/s', $response->getContent(), $matches);
        $config = json_decode($matches[1], true);
        $this->assertSame('2026-11-10 22:00', $config['prefill']['check_out']);
        $this->assertSame('Please confirm the extension.', $config['guestPrefill']['special_requests']);
        $this->assertTrue($config['resumed']);
    }

    public function test_reschedule_notes_do_not_extend_room_occupancy(): void
    {
        $room = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', 'room'))->firstOrFail();
        $reservation = Reservation::factory()->create([
            'user_id' => $this->guest->id, 'facility_id' => $room->id, 'status' => ReservationStatus::Approved,
            'check_in' => '2026-11-10 14:00', 'check_out' => '2026-11-11 12:00', 'guest_count' => 1,
        ]);
        $this->actingAs($this->guest)->patch(route('customer.reservations.reschedule', $reservation), [
            'facility_id' => $room->id, 'check_in' => '2026-11-12 14:00', 'check_out' => '2026-11-13 12:00',
            'guestCount' => 1, 'special_requests' => 'May I leave at 1 PM? Please advise the fee.',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('May I leave at 1 PM? Please advise the fee.', $reservation->fresh()->special_requests);
        $this->assertSame('12:00', $reservation->fresh()->occupancyEnd()->format('H:i'));
    }

    public function test_package_reschedule_preview_keeps_the_stored_guest_tier(): void
    {
        $package = Facility::where('name', 'Sapphire Hall + Pool + Jacuzzi (Night Use)')->firstOrFail();
        $tier = $package->paxTiers()[0];
        $reservation = app(CustomerReservationService::class)->createReservation($this->guest, $package, Carbon::parse('2026-11-10 17:00'), Carbon::parse('2026-11-10 22:00'), 2, ['pax_tier' => $tier]);
        $this->actingAs($this->guest)->postJson(route('customer.reservations.calculate-reschedule', $reservation), [
            'facility_id' => $package->id, 'check_in' => '2026-11-11 17:00', 'check_out' => '2026-11-11 22:00', 'guestCount' => 2,
        ])->assertOk()->assertJsonPath('data.total_amount', (int) round($reservation->total_price * 100));
    }

    public function test_failed_render_rolls_back_number_allocation(): void
    {
        $reservation = Reservation::factory()->create(['status' => ReservationStatus::Approved]);
        try {
            app(GuestInformationSheetNumber::class)->issue($reservation, fn () => throw new \RuntimeException('render failed'));
            $this->fail('Expected render failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('render failed', $e->getMessage());
        }
        $this->assertNull($reservation->fresh()->guest_sheet_control_number);
        $this->assertEquals(1, DB::table('document_sequences')->value('next_number'));
    }

    public function test_schedule_controls_and_notices_render_for_each_facility(): void
    {
        foreach (['diamond-hall' => 'hall', 'opal-hall' => 'opal'] as $slug => $mode) {
            $this->actingAs($this->guest)->get(route('customer.booking.create', $this->facility($slug)))->assertOk()
                ->assertSee('data-reservation-schedule="'.$mode.'"', false)
                ->assertSee('30 minutes')->assertDontSee('Extra hours after check out');
        }
    }
}
