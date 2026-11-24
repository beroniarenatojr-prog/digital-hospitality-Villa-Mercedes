<?php

namespace Tests\Feature\Admin;

use App\Enums\FacilityStatus;
use App\Enums\FacilityType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\FacilityType as FacilityTypeModel;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Support\ResortTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReservationCatalog(); // Ensures facility types, roles, etc. are loaded

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->customer = User::factory()->create();
        $this->customer->assignRole(Role::Customer->value);
    }

    public function test_unauthenticated_blocked_from_reports(): void
    {
        $this->get(route('admin.reports.index'))->assertRedirect(route('admin.login'));
    }

    public function test_customer_blocked_from_reports(): void
    {
        $this->actingAs($this->customer)->get(route('admin.reports.index'))->assertRedirect(route('admin.login'));
    }

    public function test_administrator_allowed_to_view_reports(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));
        $response->assertStatus(200);
        // assertSeeText reads the text a reader gets, so the ampersand may appear
        // as the escaped entity the browser renders rather than as a raw "&".
        $response->assertSeeText('Reports and insights');
    }

    public function test_booking_reports_summary_and_breakdowns(): void
    {
        $roomType = FacilityTypeModel::where('slug', FacilityType::Room->value)->first();
        $room = Facility::factory()->create(['facility_type_id' => $roomType->id]);

        Reservation::factory()->create([
            'status' => ReservationStatus::Approved->value,
            'reservation_source' => ReservationSource::WalkIn->value,
            'facility_id' => $room->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('Service reservations');
        $response->assertSee('Confirmed'); // Status breakdown
        $response->assertSee('Walk-in'); // Source breakdown
        $response->assertDontSee('Export Excel (CSV)');
        $response->assertSee('Print / Save PDF');
    }

    public function test_booking_trends_monthly_grouping(): void
    {
        Reservation::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('admin.reports.index', ['grouping' => 'month']));
        $response->assertStatus(200);
        $response->assertSee('Reservations created over time');
        $response->assertSee('Month');
    }

    public function test_booking_trends_weekly_and_yearly_grouping(): void
    {
        Reservation::factory()->create();

        $week = $this->actingAs($this->admin)->get(route('admin.reports.index', ['grouping' => 'week']));
        $week->assertStatus(200);
        $week->assertSee('Reservations created over time');
        $week->assertSee('Week');

        $year = $this->actingAs($this->admin)->get(route('admin.reports.index', ['grouping' => 'year']));
        $year->assertStatus(200);
        $year->assertSee('Reservations created over time');
        $year->assertSee('Year');
    }

    public function test_reports_csv_export_downloads(): void
    {
        Reservation::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('admin.reports.export.csv', ['grouping' => 'month']));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('Service reservations', $response->streamedContent());
        $this->assertStringContainsString('Reservations created over time', $response->streamedContent());
    }

    public function test_reports_pdf_export_view(): void
    {
        Reservation::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('admin.reports.export.pdf'));

        $response->assertOk();
        $response->assertSee('Bookings');
        $response->assertSee('Print / Save PDF');
        $response->assertSee('Service reservations');
    }

    public function test_customer_blocked_from_report_exports(): void
    {
        $this->actingAs($this->customer)->get(route('admin.reports.export.csv'))->assertRedirect(route('admin.login'));
        $this->actingAs($this->customer)->get(route('admin.reports.export.pdf'))->assertRedirect(route('admin.login'));
    }

    public function test_date_range_applied_correctly(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index', [
            'start_date' => now()->addDays(10)->format('Y-m-d'),
            'end_date' => now()->addDays(20)->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        // Assert empty state is shown since we're querying the future
        $response->assertSeeText('No activity recorded for this chart');
    }

    public function test_popularity_reports_are_displayed(): void
    {
        $roomType = FacilityTypeModel::where('slug', FacilityType::Room->value)->first();
        $room = Facility::factory()->create(['facility_type_id' => $roomType->id, 'name' => 'Deluxe Room A']);

        $packageType = FacilityTypeModel::where('slug', FacilityType::ResortPackage->value)->first();
        $package = Facility::factory()->create(['facility_type_id' => $packageType->id, 'name' => 'Summer Promo']);

        Reservation::factory()->create(['facility_id' => $room->id]);
        Reservation::factory()->create(['facility_id' => $package->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));
        $response->assertStatus(200);
        $response->assertSee('Confirmed reservations by service type');
        $response->assertSee('Deluxe Room A');

        $response->assertSee('Reservation records');
        $response->assertSee('Summer Promo');

    }

    public function test_revenue_and_payment_reports(): void
    {
        $roomType = FacilityTypeModel::where('slug', FacilityType::Room->value)->first();
        $room = Facility::factory()->create(['facility_type_id' => $roomType->id]);

        $reservation = Reservation::factory()->create([
            'facility_id' => $room->id,
            'total_price' => 5000,
            'status' => ReservationStatus::Approved->value,
        ]);

        Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => 2500,
            'status' => PaymentStatus::Verified->value,
            'payment_date' => ResortTime::today(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));

        $response->assertStatus(200);
        // Reservation Value should be 5,000
        $response->assertSee('5,000.00');
        // Verified Payment Revenue should be 2,500
        $this->get(route('admin.reports.index', ['view' => 'revenue']))->assertOk()->assertSee('2,500.00')->assertSee('Verified');
        $this->get(route('admin.reports.index', ['view' => 'balances']))->assertOk()->assertSee('Still unpaid')->assertSee('2,500.00');

        // Payment status counts

    }

    public function test_outstanding_balance_uses_verified_payments_for_selected_reservations(): void
    {
        $roomType = FacilityTypeModel::where('slug', FacilityType::Room->value)->first();
        $room = Facility::factory()->create(['facility_type_id' => $roomType->id]);

        $partiallyPaid = Reservation::factory()->create([
            'facility_id' => $room->id,
            'total_price' => 5000,
            'status' => ReservationStatus::Approved->value,
        ]);
        $overpaid = Reservation::factory()->create([
            'facility_id' => $room->id,
            'total_price' => 1000,
            'status' => ReservationStatus::Approved->value,
        ]);

        Payment::factory()->create([
            'reservation_id' => $partiallyPaid->id,
            'amount' => 2000,
            'status' => PaymentStatus::Verified->value,
        ]);
        Payment::factory()->create([
            'reservation_id' => $partiallyPaid->id,
            'amount' => 1000,
            'status' => PaymentStatus::Pending->value,
        ]);
        Payment::factory()->create([
            'reservation_id' => $overpaid->id,
            'amount' => 1500,
            'status' => PaymentStatus::Verified->value,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.index', ['view' => 'balances']))
            ->assertOk()
            ->assertSee('3,000.00');
    }

    public function test_explicit_date_range_shows_previous_period_comparison_and_quick_ranges(): void
    {
        Reservation::factory()->create(['created_at' => now()->subDays(2)]);
        Reservation::factory()->create(['created_at' => now()->subDays(9)]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.index', [
            'start_date' => now()->subDays(6)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Compared with');
        $response->assertSee('What this period shows');
        $response->assertSee('Last month');
        $response->assertSee('This month');
    }

    public function test_reversed_report_date_range_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.reports.index'))
            ->get(route('admin.reports.index', [
                'start_date' => '2026-09-10',
                'end_date' => '2026-09-01',
            ]))
            ->assertRedirect(route('admin.reports.index'))
            ->assertSessionHasErrors('end_date');
    }

    public function test_sensitive_customer_data_is_not_exposed(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));
        // Verify it doesn't leak emails or passwords on the global report page
        $response->assertDontSee($this->customer->email);
        $response->assertDontSee($this->customer->password);
    }

    public function test_role_focused_report_views_render_and_preserve_filters(): void
    {
        $revenue = $this->actingAs($this->admin)->get(route('admin.reports.index', [
            'view' => 'revenue',
            'facility_type' => FacilityType::Room->value,
            'source' => ReservationSource::Online->value,
            'grouping' => 'week',
        ]));

        $revenue->assertOk()
            ->assertSee('Verified payments received')
            ->assertSee('Refunds returned')
            ->assertDontSee('Advanced revenue analytics')
            ->assertDontSee('name="facility_type"', false)
            ->assertSee('source=online', false);

        // Operations no longer lives inside the analytics page. An old bookmark is
        // sent to the dedicated screen rather than shown a second copy of it.
        $this->actingAs($this->admin)
            ->get(route('admin.reports.index', ['view' => 'operations']))
            ->assertRedirect(route('admin.reports.operational'));

        $operations = $this->actingAs($this->admin)->get(route('admin.reports.operational'));
        $operations->assertOk()
            ->assertSee('Guest check-in registry')
            ->assertSee('Front desk reports');
    }

    public function test_operations_screen_renders_registry_occupancy_and_action_lists(): void
    {
        $roomType = FacilityTypeModel::where('slug', FacilityType::Room->value)->firstOrFail();
        $room = Facility::factory()->create(['facility_type_id' => $roomType->id, 'is_active' => true]);

        $checkedIn = Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Approved->value,
            'guest_count' => 6,
            'checked_in_at' => now()->subHour(),
            'checked_out_at' => null,
        ]);

        $registry = $this->actingAs($this->admin)->get(route('admin.reports.operational'));
        $registry->assertOk()
            ->assertSee('Guest check-in registry')
            ->assertSee($checkedIn->reservation_code)
            // A party of six must read as six guests, not one booking row.
            ->assertSeeText('6 guests across 1 parties');

        $occupancy = $this->actingAs($this->admin)->get(route('admin.reports.operational', ['ops' => 'occupancy']));
        $occupancy->assertRedirect(route('admin.checked-in.index'));

        $actions = $this->actingAs($this->admin)->get(route('admin.reports.operational', ['ops' => 'action-lists']));
        $actions->assertOk()
            ->assertSee('Guests awaiting check in')
            ->assertSee('Facilities under maintenance');
    }

    public function test_checked_in_workspace_only_lists_stays_that_have_not_checked_out(): void
    {
        $current = Reservation::factory()->create([
            'status' => ReservationStatus::Approved->value,
            'checked_in_at' => now()->subDay(),
            'checked_out_at' => null,
            'check_out' => now()->subHour(),
        ]);
        $departed = Reservation::factory()->create([
            'checked_in_at' => now()->subDays(2),
            'checked_out_at' => now()->subDay(),
        ]);
        $upcoming = Reservation::factory()->create(['checked_in_at' => null]);

        $response = $this->actingAs($this->admin)->get(route('admin.checked-in.index'));
        $response
            ->assertOk()
            ->assertSee('Checked-in')
            ->assertSee($current->reservation_code)
            ->assertSee('Remaining balance')
            ->assertSee('Overdue checkout')
            ->assertSee(route('admin.reservations.show', $current), false);

        $ids = array_column($response->viewData('currentOccupancy')['occupied_list'], 'reservation_id');
        $this->assertContains($current->id, $ids);
        $this->assertNotContains($departed->id, $ids);
        $this->assertNotContains($upcoming->id, $ids);
    }

    public function test_operational_action_links_open_the_reservation_instead_of_getting_patch_only_routes(): void
    {
        $roomType = FacilityTypeModel::where('slug', FacilityType::Room->value)->firstOrFail();
        $room = Facility::factory()->create(['facility_type_id' => $roomType->id, 'is_active' => true]);

        $walkIn = Reservation::factory()->create([
            'facility_id' => $room->id,
            'reservation_source' => ReservationSource::WalkIn->value,
            'status' => ReservationStatus::Approved->value,
            'created_at' => now(),
        ]);
        $missedArrival = Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => now()->subDays(2),
            'check_out' => now()->subDay(),
            'checked_in_at' => null,
        ]);
        $overdueDeparture = Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => now()->subDays(2),
            'check_out' => now()->subDay(),
            'checked_in_at' => now()->subDays(2),
            'checked_out_at' => null,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.operational', [
            'ops' => 'action-lists',
        ]));

        $response->assertOk()
            ->assertSee(route('admin.reservations.show', $walkIn), false)
            ->assertSee(route('admin.reservations.show', $missedArrival), false)
            ->assertSee(route('admin.reservations.show', $overdueDeparture), false)
            ->assertDontSee(route('admin.reservations.show', $walkIn->reservation_code), false)
            ->assertDontSee(route('admin.reservations.show', $missedArrival->reservation_code), false)
            ->assertDontSee(route('admin.reservations.check-in', $walkIn->reservation_code), false)
            ->assertDontSee(route('admin.reservations.cancel', $missedArrival->reservation_code), false)
            ->assertDontSee(route('admin.reservations.check-out', $overdueDeparture->reservation_code), false);

        $this->actingAs($this->admin)->get(route('admin.reservations.show', $walkIn))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.reservations.show', $missedArrival))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.reservations.show', $overdueDeparture))->assertOk();
    }

    public function test_operations_screen_ignores_an_unknown_sub_view(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.operational', ['ops' => 'invented']))
            ->assertSessionHasErrors('ops');
    }

    public function test_operations_report_prints_the_registry_without_the_revenue_charts(): void
    {
        $printed = $this->actingAs($this->admin)->get(route('admin.reports.export.pdf', ['view' => 'operations']));

        $printed->assertOk()
            ->assertSee('Guest check-in registry')
            ->assertDontSee('Revenue by service department');
    }

    public function test_operations_export_can_print_one_section_for_a_selected_month(): void
    {
        $printed = $this->actingAs($this->admin)->get(route('admin.reports.export.pdf', [
            'view' => 'operations',
            'section' => 'occupancy',
            'period' => 'month',
            'month' => '2026-09',
        ]));

        $printed->assertOk()
            ->assertSee('data:image/png;base64,', false)
            ->assertSee(config('resort.location'))
            ->assertSee(config('resort.phone'))
            ->assertSee(config('resort.email'))
            ->assertSee('Current occupancy')
            ->assertDontSee('Guest check-in registry')
            ->assertDontSee("Today's action lists")
            ->assertDontSee('Available right now');

        $this->assertSame('2026-09-01', $printed->viewData('startDate')->format('Y-m-d'));
        $this->assertSame('2026-09-30', $printed->viewData('endDate')->format('Y-m-d'));
    }

    public function test_operations_export_can_print_all_sections_for_a_selected_year(): void
    {
        $printed = $this->actingAs($this->admin)->get(route('admin.reports.export.pdf', [
            'view' => 'operations',
            'section' => 'all',
            'period' => 'year',
            'year' => 2026,
        ]));

        $printed->assertOk()
            ->assertSee('Guest check-in registry')
            ->assertDontSee('<h2>Current occupancy</h2>', false)
            ->assertSee("Today's action lists", false)
            ->assertDontSee('Available right now');

        $this->assertSame('2026-01-01', $printed->viewData('startDate')->format('Y-m-d'));
        $this->assertSame('2026-12-31', $printed->viewData('endDate')->format('Y-m-d'));
    }

    public function test_operations_screen_offers_flexible_pdf_export_options(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.operational'))
            ->assertOk()
            ->assertSee('Print report / Save PDF')
            ->assertSee('section=registry', false)
            ->assertDontSee('All sections')
            ->assertDontSee('Available now');
    }

    public function test_seasonality_reads_a_fixed_twelve_month_window_and_forward_demand(): void
    {
        $roomType = FacilityTypeModel::where('slug', FacilityType::Room->value)->firstOrFail();
        $room = Facility::factory()->create(['facility_type_id' => $roomType->id]);

        // One unambiguous peak month five months back.
        $busyMonth = now()->startOfMonth()->subMonths(5);
        foreach (range(1, 4) as $ignored) {
            Reservation::factory()->create([
                'facility_id' => $room->id,
                'status' => ReservationStatus::Completed->value,
                'check_in' => $busyMonth->copy()->addDays(3),
                'check_out' => $busyMonth->copy()->addDays(5),
                'guest_count' => 10,
            ]);
        }

        // A cancelled stay never happens, so it must not create a season.
        Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Cancelled->value,
            'check_in' => $busyMonth->copy()->addDays(3),
            'check_out' => $busyMonth->copy()->addDays(5),
            'guest_count' => 50,
        ]);

        // Confirmed demand ahead of today belongs in "on the books".
        $futureMonth = now()->copy()->addMonths(2)->startOfMonth();
        Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Approved->value,
            'check_in' => $futureMonth->copy()->addDays(2),
            'check_out' => $futureMonth->copy()->addDays(4),
            'guest_count' => 7,
        ]);

        $seasonality = app(\App\Services\Admin\ReportingService::class)->getSeasonalityReport(new \App\Support\Admin\ReportFilters(null, null));

        // Twelve whole months ending with the current one, regardless of filters.
        $this->assertCount(12, $seasonality['series']);
        $this->assertSame(now()->format('Y-m'), $seasonality['series'][11]['key']);
        $this->assertTrue($seasonality['series'][11]['is_current']);

        $this->assertSame($busyMonth->format('Y-m'), $seasonality['peak']['key']);
        $this->assertSame(40, $seasonality['peak']['guests']);

        // Internal consistency: the headline totals are the sums of the series.
        $this->assertSame(collect($seasonality['series'])->sum('guests'), $seasonality['confirmed_guests']);
        $this->assertSame(collect($seasonality['series'])->sum('parties'), $seasonality['confirmed_parties']);
        $this->assertSame(
            $seasonality['weekend']['parties'] + $seasonality['weekday']['parties'],
            $seasonality['confirmed_parties'],
        );

        $onBooksMonths = array_column($seasonality['on_books'], 'key');
        $this->assertContains($futureMonth->format('Y-m'), $onBooksMonths);
        foreach ($onBooksMonths as $key) {
            $this->assertGreaterThan(now()->format('Y-m'), $key, 'Only stays ahead of today belong on the books.');
        }
    }

    public function test_deposit_health_flags_the_half_and_overdue_bookings(): void
    {
        $roomType = FacilityTypeModel::where('slug', FacilityType::Room->value)->firstOrFail();
        $room = Facility::factory()->create(['facility_type_id' => $roomType->id]);

        $today = ResortTime::today()->toDateString();

        // Approval is what raises the deposit, so a confirmed booking inside its
        // window is the on-time case.
        $onTime = Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Approved->value,
            'total_price' => 1000,
            'deposit_required' => null,
            'deposit_due_at' => now()->addDays(2),
            'approved_at' => now(),
            'created_at' => now(),
        ]);

        $overdue = Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Approved->value,
            'total_price' => 1000,
            'deposit_required' => null,
            'deposit_due_at' => now()->subDay(),
            'approved_at' => now(),
            'created_at' => now(),
        ]);

        $settled = Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Approved->value,
            'total_price' => 1000,
            'deposit_required' => null,
            'deposit_due_at' => now()->addDays(2),
            'approved_at' => now(),
            'created_at' => now(),
        ]);

        // A request still waiting for the resort owes nothing yet, so it is not
        // this report's business — it belongs to the review queue.
        Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Pending->value,
            'total_price' => 1000,
            'deposit_required' => 500,
            'deposit_due_at' => now()->addDay(),
            'created_at' => now(),
        ]);

        Payment::factory()->create([
            'reservation_id' => $settled->id,
            'amount' => 500,
            'status' => PaymentStatus::Verified->value,
            'payment_date' => now(),
        ]);

        // A receipt uploaded but not yet verified must read as awaiting verification,
        // not as collected and not as missing entirely.
        Payment::factory()->create([
            'reservation_id' => $overdue->id,
            'amount' => 500,
            'status' => PaymentStatus::Pending->value,
            'payment_date' => now(),
        ]);

        $health = app(\App\Services\Admin\ReportingService::class)->getDepositHealth(new \App\Support\Admin\ReportFilters(ResortTime::today(), ResortTime::today()));

        $this->assertSame(3, $health['bookings']);
        $this->assertSame(1, $health['met']);
        $this->assertSame(2, $health['short']);
        $this->assertSame(1, $health['overdue']);
        $this->assertSame(1, $health['unverified']);

        // Half of 1000 for each of the three live bookings.
        $this->assertSame(1500.0, $health['deposit_expected']);
        $this->assertSame(500.0, $health['deposit_collected']);
        $this->assertSame(500.0, $health['awaiting_verification']);
        $this->assertSame(1000.0, $health['outstanding_total']);
        $this->assertSame(2, $health['outstanding_count']);

        // Overdue first, because that is the order a front desk works the list.
        $this->assertSame($overdue->reservation_code, $health['outstanding'][0]['reservation_code']);
        $this->assertTrue($health['outstanding'][0]['is_overdue']);
        $this->assertSame($onTime->reservation_code, $health['outstanding'][1]['reservation_code']);
        $this->assertFalse($health['outstanding'][1]['is_overdue']);
    }

    public function test_decision_report_prints_findings_without_unrelated_operational_panels(): void
    {
        $printed = $this->actingAs($this->admin)->get(route('admin.reports.export.pdf', [
            'view' => 'executive',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $printed->assertOk()
            ->assertSeeText('What this period shows')
            ->assertDontSeeText('50% down payment health');
    }

    public function test_operations_availability_report_prints_the_sellable_list(): void
    {
        $printed = $this->actingAs($this->admin)->get(route('admin.reports.export.pdf', [
            'view' => 'operations',
            'ops' => 'availability',
            'pax' => 20,
        ]));

        $printed->assertRedirect(route('admin.decision-board'));
    }

    public function test_walk_in_availability_offers_only_open_spaces_that_fit(): void
    {
        $hallType = FacilityTypeModel::where('slug', FacilityType::FunctionHall->value)->firstOrFail();
        $cottageType = FacilityTypeModel::where('slug', FacilityType::Cottage->value)->firstOrFail();

        $hall = Facility::factory()->create([
            'facility_type_id' => $hallType->id,
            'name' => 'Grand Function Hall',
            'capacity' => 20,
            'max_capacity' => 60,
            'status' => FacilityStatus::Available->value,
            'is_active' => true,
            'is_listed' => true,
        ]);

        $cottage = Facility::factory()->create([
            'facility_type_id' => $cottageType->id,
            'name' => 'Sunset Cottage',
            'capacity' => 4,
            'max_capacity' => 6,
            'status' => FacilityStatus::Available->value,
            'is_active' => true,
            'is_listed' => true,
        ]);

        // A second hall that fits the party but is already held must stay off the list.
        $busyHall = Facility::factory()->create([
            'facility_type_id' => $hallType->id,
            'name' => 'Occupied Hall',
            'capacity' => 20,
            'max_capacity' => 60,
            'status' => FacilityStatus::Available->value,
            'is_active' => true,
            'is_listed' => true,
        ]);

        Reservation::factory()->create([
            'facility_id' => $busyHall->id,
            'status' => ReservationStatus::Approved->value,
            'guest_count' => 30,
            'check_in' => ResortTime::databaseNow()->subHours(2),
            'check_out' => ResortTime::databaseNow()->addHours(6),
            'checked_in_at' => now()->subHours(2),
            'checked_out_at' => null,
        ]);

        $availability = app(\App\Services\Admin\ReportingService::class)->getWalkInAvailability(20);

        $this->assertSame(20, $availability['minimum_pax']);

        // The seeded catalog also holds sellable spaces, so the check is which of
        // our three spaces made the shortlist, not how many total.
        $recommendedNames = array_column($availability['recommended'], 'name');
        $this->assertContains($hall->name, $recommendedNames);
        $this->assertNotContains($busyHall->name, $recommendedNames);
        $this->assertNotContains($cottage->name, $recommendedNames, 'A space too small for the party must not be recommended.');
        $this->assertSame(count($availability['recommended']), $availability['sellable_count']);

        // Every space counted as open must also be one of the rows the list shows.
        $this->assertSame(
            collect($availability['rows'])->where('open_now', true)->count(),
            $availability['open_count'],
        );

        $cottageRow = collect($availability['rows'])->firstWhere('name', $cottage->name);
        $this->assertTrue($cottageRow['open_now'], 'A free cottage should read as open even when it is too small.');
        $this->assertFalse($cottageRow['fits']);

        $busyRow = collect($availability['rows'])->firstWhere('name', $busyHall->name);
        $this->assertFalse($busyRow['open_now']);
        $this->assertNotNull($busyRow['occupied_until']);
    }

    public function test_report_filters_sit_on_one_row_without_a_subtitle(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));

        $response->assertOk()
            ->assertSee('Date from')
            ->assertSee('Date until')
            ->assertSee('Chart interval')
            ->assertDontSee('Apply filters')
            ->assertDontSee('Booking figures count the reservation creation date')
            ->assertDontSee('How to read these figures');
    }

    public function test_report_view_tabs_are_labels_only(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));

        $response->assertOk()
            ->assertSee('Bookings')
            ->assertSee('Completed services')
            ->assertSee('Front desk reports')
            ->assertDontSee('Cash, bookings, season and deposits')
            ->assertDontSee('Booking value, delivered services and cash')
            ->assertDontSee('Guest registry, occupancy and today');

        // The same segmented switcher as the Operations screen: one bordered
        // surface, one filled segment for where you are, three centred labels.
        preg_match('#<nav aria-label="Report views"[\s\S]*?</nav>#', $response->getContent(), $nav);
        $markup = $nav[0] ?? '';

        $this->assertSame(4, substr_count($markup, 'px-4 py-3 text-center'));
        $this->assertSame(1, substr_count($markup, 'bg-primary text-white'), 'Only the open view is filled');
        $this->assertStringContainsString('rounded-xl border border-border bg-surface p-1.5', $markup);

        // The bar is the one way to Operations on this screen: the header button
        // that used to point there as well read as a competing destination. The
        // sidebar still links to it, so the check is the header only.
        preg_match('#<h1[^>]*>Reports and insights</h1>[\s\S]*?<nav aria-label="Report views"#', $response->getContent(), $header);
        $this->assertNotSame('', $header[0] ?? '', 'The page header must render above the view switcher.');
        $this->assertStringNotContainsString(route('admin.reports.operational'), $header[0]);
        $this->assertStringContainsString('Print / Save PDF', $header[0]);
    }

    public function test_both_report_screens_build_their_filter_bar_from_one_component(): void
    {
        $reports = $this->filterBarShape(
            $this->actingAs($this->admin)->get(route('admin.reports.index'))->getContent()
        );
        $operations = $this->filterBarShape(
            $this->actingAs($this->admin)->get(route('admin.reports.operational', ['ops' => 'registry']))->getContent()
        );

        // The row and every field cell come out of <x-admin.filter-bar> and
        // <x-admin.filter-field>, so the two screens cannot drift into a second
        // shape. Changing the breakpoint means changing this expectation too.
        $this->assertSame(['flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end xl:flex-nowrap', 5], $reports);
        $this->assertSame([$reports[0], 4], $operations);
    }

    public function test_filter_bar_renders_the_reset_the_screen_asks_for(): void
    {
        $filtered = $this->actingAs($this->admin)->get(route('admin.reports.operational', [
            'ops' => 'registry',
            'facility_type' => FacilityType::Room->value,
            'pax' => 2,
        ]));

        $filtered->assertOk()->assertSee('Clear filters');
    }

    public function test_registry_filters_describe_actual_check_in_dates(): void
    {
        $this->actingAs($this->admin)->get(route('admin.reports.operational'))
            ->assertOk()->assertSee('Checked in from')->assertSee('Checked in until')
            ->assertDontSee('Available now')->assertDontSee('name="pax"', false);
        $this->actingAs($this->admin)->get(route('admin.reports.operational', ['ops' => 'availability']))
            ->assertRedirect(route('admin.decision-board'));
        $this->actingAs($this->admin)->get(route('admin.reports.operational', ['ops' => 'action-lists', 'start_date' => '2020-01-01']))
            ->assertOk()->assertDontSee('name="start_date"', false)->assertSee('Live shift report');
    }

    /**
     * The filter bar's shape, not its wording: the form's own classes and how
     * many field cells share the row.
     *
     * @return array{0: string|null, 1: int}
     */
    private function filterBarShape(string $html): array
    {
        preg_match_all('#<form\b[\s\S]*?</form>#', $html, $forms);

        $form = collect($forms[0])->first(fn (string $markup) => preg_match('#action="[^"]*/admin/reports(?:/operational)?"#', $markup));

        $this->assertNotNull($form, 'The report screens must render their filter bar form.');

        preg_match('#class="([^"]*)"#', $form, $formClass);
        preg_match_all('#<div class="min-w-0 flex-1">#', $form, $cells);

        return [$formClass[1] ?? null, count($cells[0])];
    }

    public function test_walk_in_availability_drops_the_party_size_on_other_views(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.operational', [
            'ops' => 'registry',
            'pax' => 20,
        ]));

        $response->assertOk();

        // A stale party size must not follow the operator onto another view.
        $response->assertViewMissing('walkInAvailability');
    }

    public function test_chart_exports_reconcile_with_filtered_screen_data(): void
    {
        $roomType = FacilityTypeModel::where('slug', FacilityType::Room->value)->firstOrFail();
        $room = Facility::factory()->create(['facility_type_id' => $roomType->id]);
        $reservation = Reservation::factory()->create(['facility_id' => $room->id, 'status' => 'completed', 'total_price' => 6543, 'created_at' => '2026-09-05 12:00:00', 'check_out' => '2026-09-09 12:00:00']);
        Payment::factory()->create(['reservation_id' => $reservation->id, 'amount' => 3210, 'status' => 'verified', 'payment_date' => '2026-09-10']);
        $query = ['view' => 'revenue', 'facility_type' => 'room', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30'];
        $screen = $this->actingAs($this->admin)->get(route('admin.reports.index', $query));
        $screen->assertOk()->assertSee('Payments')->assertSee('View exact values')->assertSee('Payment records');
        $report = $screen->viewData('report');
        $this->assertEquals(3210, collect($report['charts'][0]['rows'])->sum('value'));
        $this->assertEquals(3210, collect($report['charts'][1]['rows'])->sum('value'));
        $csv = $this->get(route('admin.reports.export.csv', $query))->streamedContent();
        $this->assertStringContainsString('3210', $csv);
        $print = $this->get(route('admin.reports.export.pdf', $query))->assertOk()->assertSee('3,210.00');
        $this->assertSame($report, $print->viewData('report'));
    }

    public function test_unsupported_report_dimensions_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.reports.index'))
            ->get(route('admin.reports.index', ['view' => 'owner', 'channel' => 'invented-ota']))
            ->assertRedirect(route('admin.reports.index'))
            ->assertSessionHasErrors(['view', 'channel']);
    }

    public function test_report_exports_share_payment_date_semantics_and_metric_definitions(): void
    {
        $roomType = FacilityTypeModel::where('slug', FacilityType::Room->value)->firstOrFail();
        $room = Facility::factory()->create(['facility_type_id' => $roomType->id]);
        $reservation = Reservation::factory()->create([
            'facility_id' => $room->id,
            'status' => ReservationStatus::Approved->value,
        ]);
        Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'status' => PaymentStatus::Verified->value,
            'amount' => 4321,
            'payment_date' => '2026-09-10',
            'created_at' => '2026-09-15 09:00:00',
        ]);

        $csv = $this->actingAs($this->admin)->get(route('admin.reports.export.csv', [
            'view' => 'revenue',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
        ]));
        $content = $csv->streamedContent();

        $this->assertStringContainsString('4321', $content);
        $this->assertStringContainsString('Payment date for receipts', $content);
        $this->assertStringContainsString('What this period shows', $content);

        $this->actingAs($this->admin)->get(route('admin.reports.export.pdf', [
            'view' => 'revenue',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
        ]))->assertOk()->assertSee('4,321.00');
    }
}
