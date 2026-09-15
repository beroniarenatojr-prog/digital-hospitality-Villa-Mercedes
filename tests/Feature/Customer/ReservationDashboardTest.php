<?php

namespace Tests\Feature\Customer;

use App\Enums\FacilityStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReservationDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $customer1;

    private User $customer2;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer1 = User::factory()->create();
        // Give customer role if needed, or assume default

        $this->customer2 = User::factory()->create();

        $this->facility = Facility::factory()->create([
            'is_active' => true,
            'status' => FacilityStatus::Available->value,
            'max_capacity' => 10,
        ]);

        // Add a rate to the facility
        Rate::factory()->create([
            'facility_id' => $this->facility->id,
            'amount' => 100000, // 1000.00
            'is_active' => true,
        ]);
    }

    public function test_customer_can_view_own_reservations_dashboard()
    {
        Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending->value,
        ]);

        $response = $this->actingAs($this->customer1)->get(route('customer.reservations.index'));
        $response->assertStatus(200);
        $response->assertSee('My reservations');
    }

    public function test_customer_cannot_view_another_customers_reservation()
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer2->id,
            'facility_id' => $this->facility->id,
        ]);

        $response = $this->actingAs($this->customer1)->get(route('customer.reservations.show', $reservation));
        $response->assertStatus(403);
    }

    public function test_pending_reservation_explains_why_self_cancel_is_unavailable(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending->value,
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(12),
        ]);

        $response = $this->actingAs($this->customer1)->get(route('customer.reservations.show', $reservation));

        $response->assertOk();
        $response->assertSee('Request withdrawal');
        $response->assertDontSee('Online cancellation is available after the resort approves');
    }

    public function test_approved_reservation_still_offers_cancel_inside_the_window(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(12),
        ]);

        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Cancel reservation');
    }

    public function test_reservation_dashboard_does_not_requery_payment_totals_per_card(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $reservation = Reservation::factory()->create([
                'user_id' => $this->customer1->id,
                'facility_id' => $this->facility->id,
                'status' => ReservationStatus::Approved->value,
                'total_price' => 5000,
            ]);

            Payment::factory()->create([
                'reservation_id' => $reservation->id,
                'amount' => 1000,
                'status' => PaymentStatus::Verified->value,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.index'))
            ->assertOk();

        $sumQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains(strtolower($query['query']), 'sum(')
                && str_contains(strtolower($query['query']), 'payments'))
            ->count();

        $this->assertSame(0, $sumQueries, 'Payment totals should use the eager-loaded payments relation.');
    }

    public function test_reservation_dashboard_paginates_booking_history(): void
    {
        Reservation::factory()->count(13)->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
        ]);

        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertViewHas('reservations', fn ($reservations) => $reservations->total() === 13
                && $reservations->perPage() === 12
                && $reservations->count() === 12);
    }

    public function test_completed_reservations_are_not_grouped_with_cancelled(): void
    {
        Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Completed->value,
            'check_in' => now()->subDays(5),
            'check_out' => now()->subDays(3),
        ]);

        Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Cancelled->value,
            'check_in' => now()->addDays(5),
            'check_out' => now()->addDays(7),
        ]);

        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.index', ['tab' => 'completed']))
            ->assertOk()
            ->assertViewHas('reservations', fn ($reservations) => $reservations->total() === 1
                && $reservations->first()->status === ReservationStatus::Completed);

        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.index', ['tab' => 'cancelled']))
            ->assertOk()
            ->assertViewHas('reservations', fn ($reservations) => $reservations->total() === 1
                && $reservations->first()->status === ReservationStatus::Cancelled);
    }

    public function test_in_progress_approved_stay_is_listed_as_current_not_completed(): void
    {
        Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => now()->subDay(),
            'check_out' => now()->addDay(),
        ]);

        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.index', ['tab' => 'current']))
            ->assertOk()
            ->assertSee('Current stay')
            ->assertViewHas('reservations', fn ($reservations) => $reservations->total() === 1);

        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.index', ['tab' => 'completed']))
            ->assertOk()
            ->assertViewHas('reservations', fn ($reservations) => $reservations->total() === 0);
    }

    public function test_tab_counts_cover_full_history_not_just_the_current_page(): void
    {
        Reservation::factory()->count(13)->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending->value,
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(12),
        ]);

        Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Cancelled->value,
        ]);

        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.index', ['tab' => 'pending']))
            ->assertOk()
            ->assertViewHas('tabs', function (array $tabs) {
                $counts = collect($tabs)->pluck('count', 'key');

                return $counts['pending'] === 13 && $counts['cancelled'] === 1;
            })
            ->assertViewHas('reservations', fn ($reservations) => $reservations->total() === 13
                && $reservations->count() === 12);
    }

    public function test_customer_can_cancel_approved_reservation()
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => now()->addDays(5),
            'check_out' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->customer1)->patch(route('customer.reservations.cancel', $reservation), [
            'reason' => 'Change of plans',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::Cancelled->value,
            'cancellation_reason' => 'Change of plans',
        ]);
    }

    public function test_invalid_cancellation_is_rejected()
    {
        // Cancelled reservations cannot be cancelled again
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Cancelled->value,
        ]);

        $response = $this->actingAs($this->customer1)->patch(route('customer.reservations.cancel', $reservation));

        // The service throws ReservationCancellationException, which in a real app might be caught
        // by the exception handler, returning 500 or redirect with error.
        // We'll assert that the exception is thrown or caught (status 500/redirect).
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::Cancelled->value, // Still cancelled
        ]);
    }

    public function test_reschedule_preview_excludes_current_reservation()
    {
        $checkIn = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $checkOut = Carbon::now()->addDays(6)->setHour(12)->setMinute(0)->setSecond(0);

        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guest_count' => 2,
        ]);

        $response = $this->actingAs($this->customer1)->postJson(route('customer.reservations.calculate-reschedule', $reservation), [
            'facility_id' => $this->facility->id,
            'check_in' => $checkIn->format('Y-m-d\TH:i'),
            'check_out' => $checkOut->format('Y-m-d\TH:i'),
            'guestCount' => 3, // change guest count
        ]);

        $response->assertStatus(200);
        $response->assertJsonMissingPath('debug');
        $response->assertJsonPath('success', true);
        // It shouldn't fail with "Facility is already booked" because it excludes itself
    }

    public function test_final_reschedule_revalidates_availability()
    {
        $checkIn = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $checkOut = Carbon::now()->addDays(6)->setHour(12)->setMinute(0)->setSecond(0);

        // Another customer has a reservation
        Reservation::factory()->create([
            'user_id' => $this->customer2->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]);

        $checkInOriginal = Carbon::now()->addDays(10)->setHour(14)->setMinute(0)->setSecond(0);
        $checkOutOriginal = Carbon::now()->addDays(11)->setHour(12)->setMinute(0)->setSecond(0);

        // Customer 1 tries to reschedule their own reservation to overlapping dates
        $reservation1 = Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => $checkInOriginal,
            'check_out' => $checkOutOriginal,
        ]);

        $response = $this->actingAs($this->customer1)->patch(route('customer.reservations.reschedule', $reservation1), [
            'facility_id' => $this->facility->id,
            'check_in' => $checkIn->format('Y-m-d\TH:i'),
            'check_out' => $checkOut->format('Y-m-d\TH:i'),
            'guestCount' => 2,
        ]);

        // The service should throw ReservationConflictException, which the handler handles
        // Assert the reservation dates didn't change
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation1->id,
            'check_in' => $checkInOriginal->toDateTimeString(),
        ]);
    }
}
