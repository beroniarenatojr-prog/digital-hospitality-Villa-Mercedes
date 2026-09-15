<?php

namespace Tests\Feature\Admin;

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
        $response->assertSee('Reporting & Analytics', false);
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
        $response->assertSee('Total Reservations');
        $response->assertSee('Confirmed'); // Status breakdown
        $response->assertSee('walk in'); // Source breakdown
        $response->assertSee('Export Excel (CSV)');
        $response->assertSee('Export PDF');
    }

    public function test_booking_trends_monthly_grouping(): void
    {
        Reservation::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('admin.reports.index', ['grouping' => 'month']));
        $response->assertStatus(200);
        $response->assertSee('Reservation Trends');
        $response->assertSee('Month');
    }

    public function test_booking_trends_weekly_and_yearly_grouping(): void
    {
        Reservation::factory()->create();

        $week = $this->actingAs($this->admin)->get(route('admin.reports.index', ['grouping' => 'week']));
        $week->assertStatus(200);
        $week->assertSee('Reservation Trends');
        $week->assertSee('Week');

        $year = $this->actingAs($this->admin)->get(route('admin.reports.index', ['grouping' => 'year']));
        $year->assertStatus(200);
        $year->assertSee('Reservation Trends');
        $year->assertSee('Year');
    }

    public function test_reports_csv_export_downloads(): void
    {
        Reservation::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('admin.reports.export.csv', ['grouping' => 'month']));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('Total Reservations', $response->streamedContent());
        $this->assertStringContainsString('Reservation Trends', $response->streamedContent());
    }

    public function test_reports_pdf_export_view(): void
    {
        Reservation::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('admin.reports.export.pdf'));

        $response->assertOk();
        $response->assertSee('Reservations report');
        $response->assertSee('Print / Save as PDF');
        $response->assertSee('Total Reservations');
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
        $response->assertSee('No Data Available');
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
        $response->assertSee('Most Reserved Services');
        $response->assertSee('Deluxe Room A');

        $response->assertSee('Package Popularity');
        $response->assertSee('Summer Promo');
        $response->assertSee('(100%)'); // Since it's the only package booked
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
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));

        $response->assertStatus(200);
        // Reservation Value should be 5,000
        $response->assertSee('5,000.00');
        // Verified Payment Revenue should be 2,500
        $response->assertSee('2,500.00');
        $response->assertSee('Outstanding Balance');

        // Payment status counts
        $response->assertSee('Payment Status Breakdown');
        $response->assertSee('Verified');
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
            ->get(route('admin.reports.index'))
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
        $response->assertSee('vs previous period');
        $response->assertSee('Quick range');
        $response->assertSee('Last 7 days');
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
}
