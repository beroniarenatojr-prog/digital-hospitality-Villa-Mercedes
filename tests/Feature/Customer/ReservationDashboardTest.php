<?php

namespace Tests\Feature\Customer;

use App\Enums\FacilityStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationDestination;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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

    public function test_grouped_booking_has_one_card_count_and_combined_balance(): void
    {
        $group = \App\Models\ReservationGroup::factory()->create(['user_id' => $this->customer1->id, 'total_amount' => 12900]);
        $lines = Reservation::factory()->count(14)->create(['user_id' => $this->customer1->id, 'facility_id' => $this->facility->id, 'reservation_group_id' => $group->id, 'status' => ReservationStatus::Pending]);
        Payment::withoutEvents(function () use ($group, $lines) {
            Payment::factory()->create(['reservation_id' => $lines->first()->id, 'reservation_group_id' => $group->id, 'amount' => 1000, 'status' => PaymentStatus::Verified]);
            Payment::factory()->create(['reservation_id' => null, 'reservation_group_id' => $group->id, 'amount' => 6450, 'status' => PaymentStatus::Pending]);
        });
        $response = $this->actingAs($this->customer1)->get(route('customer.reservations.index', ['tab' => 'pending']));
        $response->assertOk()
            ->assertSee('12,900.00')
            ->assertSeeInOrder(['Receipt under review', '6,450.00', 'Balance if verified', '5,450.00'])
            ->assertSee('Receipt awaiting verification')
            ->assertSee('View payment status')
            ->assertSee('View booking');
        $this->assertDoesNotMatchRegularExpression('/>\s*Manage\s*</', $response->getContent());
        $response->assertSee(route('customer.reservation-groups.payments', $group), false);
        $this->assertSame(1, $response->viewData('reservations')->total());
        $this->assertSame(1, collect($response->viewData('tabs'))->firstWhere('key', 'pending')['count']);
        $this->assertSame(1, substr_count($response->getContent(), $group->group_code));
        $this->actingAs($this->customer1)->get(route('customer.reservation-groups.show', $group))->assertOk()->assertSee($lines->first()->reservation_code)->assertSee($lines->last()->reservation_code);
        $this->actingAs($this->customer2)->get(route('customer.reservation-groups.show', $group))->assertForbidden();
    }

    public function test_group_payment_page_combines_direct_and_service_payments_and_protects_access(): void
    {
        $group = \App\Models\ReservationGroup::factory()->create(['user_id' => $this->customer1->id, 'total_amount' => 12900]);
        $lines = Reservation::factory()->count(2)->create(['user_id' => $this->customer1->id, 'facility_id' => $this->facility->id, 'reservation_group_id' => $group->id, 'status' => ReservationStatus::Pending]);
        $payments = Payment::withoutEvents(function () use ($group, $lines) {
            return collect([
                Payment::factory()->create(['reservation_id' => null, 'reservation_group_id' => $group->id, 'amount' => 6450, 'status' => PaymentStatus::Pending, 'proof_path' => 'payment_proofs/group-proof.pdf', 'reference_number' => 'GROUP-TRANSFER']),
                Payment::factory()->create(['reservation_id' => $lines->first()->id, 'reservation_group_id' => $group->id, 'amount' => 1000, 'status' => PaymentStatus::Verified, 'reference_number' => 'FIRST-SERVICE']),
                Payment::factory()->create(['reservation_id' => $lines->last()->id, 'amount' => 2000, 'status' => PaymentStatus::Rejected, 'rejection_reason' => 'Reference does not match', 'reference_number' => 'SECOND-SERVICE']),
            ]);
        });
        $response = $this->actingAs($this->customer1)->get(route('customer.reservation-groups.payments', $group));
        $response->assertOk()->assertSee('Deposit &amp; payment history', false)->assertSee('11,900.00')
            ->assertSee('GROUP-TRANSFER')->assertSee('FIRST-SERVICE')->assertSee('SECOND-SERVICE')
            ->assertSee('Reference does not match')->assertSee(route('customer.payments.view', $payments->first()), false)
            ->assertDontSee('Payments for this service')->assertDontSee('Your booking');
        $this->assertCount(3, $response->viewData('payments'));
        $this->actingAs($this->customer2)->get(route('customer.reservation-groups.payments', $group))->assertForbidden();
        $this->actingAs($this->customer2)->get(route('customer.payments.view', $payments->first()))->assertForbidden();
        $this->actingAs($this->customer2)->get(route('customer.payments.receipt', $payments[1]))->assertForbidden();
    }

    public function test_mixed_group_stays_together_in_active_tab(): void
    {
        $group = \App\Models\ReservationGroup::factory()->create(['user_id' => $this->customer1->id]);
        Reservation::factory()->create(['user_id' => $this->customer1->id, 'facility_id' => $this->facility->id, 'reservation_group_id' => $group->id, 'status' => ReservationStatus::Completed]);
        Reservation::factory()->create(['user_id' => $this->customer1->id, 'facility_id' => $this->facility->id, 'reservation_group_id' => $group->id, 'status' => ReservationStatus::Approved, 'check_in' => now()->addDays(2), 'check_out' => now()->addDays(3)]);
        $response = $this->actingAs($this->customer1)->get(route('customer.reservations.index', ['tab' => 'upcoming']));
        $response->assertOk()->assertSee($group->group_code);
        $this->assertSame(1, $response->viewData('reservations')->total());
        $this->assertSame(0, collect($response->viewData('tabs'))->firstWhere('key', 'completed')['count']);
        $this->actingAs($this->customer1)->get(route('customer.reservations.index', ['tab' => 'completed']))->assertDontSee($group->group_code);
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

    public function test_pending_reservation_offers_withdrawal_not_cancellation(): void
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
        $response->assertDontSee('Cancel reservation');
        $response->assertDontSee('Online cancellation is available after the resort approves');
    }

    public function test_approved_reservation_offers_rescheduling_and_no_cancellation(): void
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
            ->assertSee('Reschedule reservation')
            ->assertDontSee('Cancel reservation');
    }

    public function test_a_paid_booking_gets_the_phone_instruction_instead_of_an_action(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(12),
            'deposit_required' => true,
        ]);

        Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => 1000,
            'status' => PaymentStatus::Verified->value,
        ]);

        // Rescheduling is refused once a deposit is paid, and the resort does not
        // accept cancellations, so the panel names the resort rather than
        // offering an action that cannot succeed.
        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Contact staff to change this booking so they can reconcile your deposit first')
            ->assertSee(config('resort.phone'))
            ->assertDontSee('Reschedule reservation')
            ->assertDontSee('Request withdrawal')
            ->assertDontSee('Cancel reservation');
    }

    public function test_a_reservation_record_answers_one_question_at_a_time(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(12),
            'deposit_required' => 1000,
        ]);

        // The pass is a confirmed-and-paid document: this booking is one the
        // guest may show at the desk, so its deposit is verified the way staff
        // verifying a proof would leave it.
        Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => $reservation->requiredDepositAmount(),
            'status' => PaymentStatus::Verified->value,
        ]);

        $stayUrl = route('customer.reservations.show', $reservation);
        $arrivalUrl = route('customer.reservations.show', [
            'reservation' => $reservation,
            'view' => 'arrival',
        ]);

        // The record opens on the stay: what was booked, and how to change it.
        $this->actingAs($this->customer1)
            ->get($stayUrl)
            ->assertOk()
            ->assertSee('Your stay')
            ->assertSee('Guest information sheet')
            ->assertSee('Change this reservation')
            // Reading order is the hierarchy: what was booked, what to check,
            // then how to change it.
            ->assertSeeInOrder(['Your stay', 'Guest information sheet', 'Change this reservation'])
            // The money answers to one address: the record states where the
            // booking stands instead of repeating the deposit panel and the
            // pricing card it used to carry beside it.
            ->assertSee('remaining of')
            ->assertDontSee('Required deposit (50%)')
            ->assertDontSee('Base amount')
            // ...and the desk code is not buried in the stay.
            ->assertDontSee('Show this at the front desk')
            // Both facets and the money page are reachable from the tabs.
            ->assertSee($arrivalUrl)
            ->assertSee(route('customer.payments.index', $reservation));

        // Arrival instructions, and only those.
        $this->actingAs($this->customer1)
            ->get($arrivalUrl)
            ->assertOk()
            ->assertSee('Show this at the front desk')
            ->assertSee('Open check in pass')
            ->assertSee('What to bring')
            ->assertDontSee('Guest information sheet')
            ->assertDontSee('Change this reservation');

        // An unknown facet is not an error: the record falls back to its own view.
        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.show', [
                'reservation' => $reservation,
                'view' => 'not-a-facet',
            ]))
            ->assertOk()
            ->assertSee('Guest information sheet');
    }

    public function test_a_pending_card_opens_the_money_page_it_is_waiting_on(): void
    {
        // A booking nothing can be paid on yet: what the resort is deciding, and
        // what the stay will cost, are both answered on the money page.
        $reservation = $this->pendingReservation(['deposit_required' => null]);

        $response = $this->actingAs($this->customer1)
            ->get(route('customer.reservations.index', ['tab' => 'pending']));

        $response->assertOk()
            ->assertSee('Deposit &amp; payments', false)
            ->assertSee('href="'.route('customer.payments.index', $reservation).'"', false)
            // The stay is not what is in question, so the card does not offer it.
            ->assertDontSee('href="'.route('customer.reservations.show', $reservation).'"', false);
    }

    public function test_a_pending_card_waits_for_review_and_keeps_one_path_to_the_money_page(): void
    {
        // The deposit workflow, on: the request is still with staff, so nothing is
        // payable yet — and the card says which state it is in rather than asking
        // for money the page behind it would refuse.
        config(['payments.deposit_workflow' => true]);

        $reservation = $this->pendingReservation(['deposit_required' => 2500]);

        $response = $this->actingAs($this->customer1)
            ->get(route('customer.reservations.index', ['tab' => 'pending']))
            ->assertOk()
            ->assertSee('Review pending')
            ->assertSee('Awaiting confirmation')
            ->assertDontSee('Submit deposit');

        $paymentsUrl = route('customer.payments.index', $reservation);

        // One way in, not two: a status line plus a single link, and the link is
        // the card's own destination rather than a second copy of it.
        $this->assertSame(
            1,
            substr_count((string) $response->getContent(), 'href="'.$paymentsUrl.'"'),
            'The card offered two ways to the same page.'
        );
        $response->assertSee('Deposit &amp; payments', false);
    }

    public function test_a_confirmed_card_opens_the_arrival_facet(): void
    {
        $reservation = $this->approvedReservation(now()->addDays(10), now()->addDays(12));

        $arrivalUrl = route('customer.reservations.show', [
            'reservation' => $reservation,
            'view' => 'arrival',
        ]);

        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertSee('Upcoming stay')
            ->assertSee('Check in details')
            ->assertSee('href="'.$arrivalUrl.'"', false)
            // Not the record's own view: a confirmed booking is about the day.
            ->assertDontSee('href="'.route('customer.reservations.show', $reservation).'"', false);
    }

    public function test_a_submitted_receipt_is_visible_without_counting_it_as_verified_payment(): void
    {
        $reservation = $this->approvedReservation(now()->addDays(10), now()->addDays(12));
        $reservation->update(['total_price' => 2500]);

        Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => 1250,
            'status' => PaymentStatus::Pending->value,
        ]);

        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.index', ['tab' => 'upcoming']))
            ->assertOk()
            ->assertSeeInOrder([
                'Verified paid',
                '0.00',
                'Under review',
                '1,250.00',
                'Balance if verified',
                '1,250.00',
            ])
            ->assertSee('Receipt awaiting verification');
    }

    public function test_a_stay_that_is_over_opens_the_record_rather_than_arrivals(): void
    {
        // Confirmed, and already finished — a guest is looking back at it, not
        // packing for it.
        $reservation = $this->approvedReservation(now()->subDays(5), now()->subDays(3));

        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.index', ['tab' => 'completed']))
            ->assertOk()
            ->assertSee('Stay finished')
            ->assertSee('View details')
            ->assertSee('href="'.route('customer.reservations.show', [$reservation, 'tab' => 'completed']).'"', false)
            ->assertDontSee('Check in details');
    }

    public function test_the_no_stay_tab_stays_out_of_sight_until_it_has_history(): void
    {
        // Nothing has ended without a stay, so a tab naming the ways one can is
        // not worth a guest's attention.
        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertDontSee('Closed bookings');

        Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Cancelled->value,
            'check_in' => now()->addDays(5),
            'check_out' => now()->addDays(7),
        ]);

        // Once there is a booking in it, the record is reachable exactly as before.
        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertSee('Closed bookings');
    }

    public function test_one_destination_per_status(): void
    {
        $upcoming = [now()->addDays(10), now()->addDays(12)];
        $past = [now()->subDays(5), now()->subDays(3)];

        $cases = [
            'pending' => [ReservationStatus::Pending, $upcoming, ReservationDestination::Payments],
            'confirmed and ahead' => [ReservationStatus::Approved, $upcoming, ReservationDestination::Arrival],
            'in progress' => [ReservationStatus::Approved, [now()->subDay(), now()->addDay()], ReservationDestination::Arrival],
            'confirmed and over' => [ReservationStatus::Approved, $past, ReservationDestination::Stay],
            'completed' => [ReservationStatus::Completed, $past, ReservationDestination::Stay],
            'cancelled' => [ReservationStatus::Cancelled, $upcoming, ReservationDestination::Stay],
            'rejected' => [ReservationStatus::Rejected, $upcoming, ReservationDestination::Stay],
            'expired' => [ReservationStatus::Expired, $upcoming, ReservationDestination::Stay],
            'rescheduled' => [ReservationStatus::Rescheduled, $upcoming, ReservationDestination::Stay],
        ];

        foreach ($cases as $name => [$status, $period, $expected]) {
            $reservation = Reservation::factory()->create([
                'user_id' => $this->customer1->id,
                'facility_id' => $this->facility->id,
                'status' => $status->value,
                'check_in' => $period[0],
                'check_out' => $period[1],
            ]);

            $this->assertSame($expected, ReservationDestination::for($reservation), "A {$name} booking lands in the wrong place.");
        }
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

    public function test_reservation_tabs_show_the_customer_lifecycle_in_order(): void
    {
        config(['payments.deposit_workflow' => true]);

        Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => now()->addDays(5),
            'check_out' => now()->addDays(6),
        ]);

        $this->actingAs($this->customer1)
            ->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertSeeInOrder(['Review pending', 'Confirmed', 'Current stay', 'Completed'])
            ->assertDontSee('Reservation flow:')
            ->assertDontSee('All stays')
            ->assertViewHas('activeTab', 'upcoming')
            ->assertViewHas('reservations', fn ($reservations) => $reservations->total() === 1
                && $reservations->first()->status === ReservationStatus::Approved);
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

    public function test_customer_cannot_cancel_an_approved_reservation()
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => now()->addDays(5),
            'check_out' => now()->addDays(7),
        ]);

        // Withdrawal is the only endpoint a guest has, and it refuses a booking
        // the resort has already approved.
        $this->actingAs($this->customer1)
            ->patch(route('customer.reservations.withdraw', $reservation), ['reason' => 'Change of plans'])
            ->assertRedirect(route('customer.reservations.show', $reservation))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::Approved->value,
            'cancellation_reason' => null,
        ]);
    }

    public function test_no_customer_route_can_cancel_a_reservation()
    {
        $this->assertFalse(
            collect(Route::getRoutes())->contains(
                fn ($route) => ($route->getName() ?? '') === 'customer.reservations.cancel'
            )
        );

        // The old URL is gone rather than merely hidden, so a bookmark or an
        // old link cannot cancel a booking.
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => now()->addDays(5),
            'check_out' => now()->addDays(7),
        ]);

        $this->actingAs($this->customer1)
            ->patch('/customer/reservations/'.$reservation->id.'/cancel')
            ->assertNotFound();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::Approved->value,
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

    /**
     * A booking the resort has not answered for yet.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function pendingReservation(array $overrides = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending->value,
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(12),
            'total_price' => 5000,
            'deposit_required' => null,
        ], $overrides));
    }

    /**
     * A confirmed booking with the given stay.
     */
    private function approvedReservation(Carbon $checkIn, Carbon $checkOut): Reservation
    {
        return Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'total_price' => 5000,
            'deposit_required' => null,
        ]);
    }
}
