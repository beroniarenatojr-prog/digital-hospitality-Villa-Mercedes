<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\User;
use App\Support\ResortTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReservationCatalog();
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);
        $this->actingAs($admin);
    }

    private function report(string $view, array $extra = [])
    {
        return $this->get(route('admin.reports.index', array_merge([
            'view' => $view, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'grouping' => 'day',
        ], $extra)))->assertOk();
    }

    public function test_findings_compare_equal_periods_with_matching_source_and_small_sample_context(): void
    {
        Reservation::factory()->create(['created_at' => '2026-08-10', 'status' => 'approved', 'reservation_source' => 'online']);
        Reservation::factory()->count(3)->create(['created_at' => '2026-08-10', 'status' => 'approved', 'reservation_source' => 'walk_in']);
        Reservation::factory()->create(['created_at' => '2026-09-10', 'status' => 'approved', 'reservation_source' => 'online']);
        Reservation::factory()->create(['created_at' => '2026-09-10', 'status' => 'cancelled', 'reservation_source' => 'online']);
        $response = $this->report('executive', ['source' => 'online']);
        $report = $response->viewData('report');
        $this->assertSame('Aug 2, 2026', $report['comparison']['from']);
        $this->assertSame('Aug 31, 2026', $report['comparison']['until']);
        $this->assertEquals(1, $report['comparison']['previous']);
        $this->assertEquals(100, $report['comparison']['change_percent']);
        $response->assertSee('up 100.0%')->assertSee('1 of 2')->assertSee('small sample');
    }

    public function test_automatic_interval_zero_baseline_and_manual_override(): void
    {
        $auto = $this->report('executive', ['grouping' => 'auto'])->viewData('report');
        $this->assertSame('Day', $auto['charts'][0]['grouping']);
        $this->assertNull($auto['comparison']['change_percent']);
        $this->assertStringContainsString('percentage growth cannot be calculated', $auto['findings'][0]);
        $year = $this->report('executive', ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'grouping' => 'auto'])->viewData('report');
        $this->assertSame('Month', $year['charts'][0]['grouping']);
        $manual = $this->report('executive', ['grouping' => 'month'])->viewData('report');
        $this->assertSame('Month', $manual['charts'][0]['grouping']);
        $this->assertNull($this->report('balances')->viewData('report')['comparison']);
    }

    public function test_group_receipts_and_line_receipts_are_counted_once_and_source_filters_preserve_them(): void
    {
        $group = ReservationGroup::factory()->create(['status' => 'approved', 'source' => 'online', 'total_amount' => 1000]);
        $line = Reservation::factory()->create(['reservation_group_id' => $group->id, 'status' => 'approved', 'total_price' => 1000]);
        Payment::factory()->create(['reservation_id' => $line->id, 'reservation_group_id' => $group->id, 'amount' => 200, 'status' => 'verified', 'payment_date' => '2026-09-05']);
        Payment::factory()->create(['reservation_id' => null, 'reservation_group_id' => $group->id, 'amount' => 300, 'status' => 'verified', 'payment_date' => '2026-09-06']);
        Payment::factory()->create(['reservation_id' => null, 'reservation_group_id' => $group->id, 'amount' => 100, 'status' => 'pending', 'payment_date' => '2026-09-06']);
        Payment::factory()->create(['reservation_id' => $line->id, 'amount' => 50, 'status' => 'verified', 'payment_date' => '2026-08-31']);
        $response = $this->report('revenue', ['source' => 'online', 'facility_type' => 'room']);
        $data = $response->viewData('report');
        $this->assertEquals(500, $data['metrics'][0]['value']);
        $this->assertEquals(100, $data['metrics'][3]['value']);
        $this->assertEquals(500, array_sum(array_column($data['charts'][0]['rows'], 'value')));
        $this->assertCount(30, $data['charts'][0]['rows']);
        $response->assertDontSee('name="facility_type"', false);
        $this->assertNull($response->viewData('filters')->facilityType);
        $this->assertEquals(0, $this->report('revenue', ['source' => 'walk_in'])->viewData('report')['metrics'][0]['value']);
    }

    public function test_balances_are_current_grouped_and_exclude_pending_or_refunded_bookings(): void
    {
        $group = ReservationGroup::factory()->create(['status' => 'approved', 'total_amount' => 1000]);
        $line = Reservation::factory()->create(['reservation_group_id' => $group->id, 'status' => 'approved', 'total_price' => 1000]);
        Payment::factory()->create(['reservation_id' => $line->id, 'reservation_group_id' => $group->id, 'amount' => 200, 'status' => 'verified']);
        Payment::factory()->create(['reservation_id' => null, 'reservation_group_id' => $group->id, 'amount' => 300, 'status' => 'verified']);
        Payment::factory()->create(['reservation_id' => $line->id, 'amount' => 500, 'status' => 'pending']);
        Reservation::factory()->create(['status' => 'pending', 'total_price' => 9000]);
        $departed = Reservation::factory()->create(['status' => 'completed', 'total_price' => 100, 'created_at' => '2020-01-01']);
        $refund = Reservation::factory()->create(['status' => 'approved', 'total_price' => 8000]);
        $refund->forceFill(['booking_workflow' => ['refund' => ['status' => 'approved']]])->save();
        $response = $this->report('balances');
        $data = $response->viewData('report');
        $this->assertEquals(600, $data['metrics'][0]['value']);
        $this->assertEquals(100, $data['metrics'][1]['value']);
        $this->assertCount(2, $data['tables'][0]['rows']);
        $this->assertSame($departed->reservation_code, $data['tables'][0]['rows'][0]['cells'][0]);
        $response->assertDontSee('name="start_date"', false)->assertDontSee('name="facility_type"', false);
    }

    public function test_refunds_use_actual_return_date_and_group_refund_is_not_counted_twice(): void
    {
        $group = ReservationGroup::factory()->create(['status' => 'cancelled', 'refund_status' => 'processed', 'refund_amount' => 400, 'refunded_at' => '2026-09-05']);
        $group->forceFill(['booking_workflow' => ['refund' => ['status' => 'returned', 'amount_cents' => 40000, 'returned_at' => '2026-09-05T10:00:00+08:00']]])->save();
        $single = Reservation::factory()->create(['status' => 'cancelled']);
        $single->forceFill(['booking_workflow' => ['refund' => ['status' => 'returned', 'amount_cents' => 10000, 'returned_at' => '2026-09-06T10:00:00+08:00']]])->save();
        $approved = Reservation::factory()->create(['status' => 'cancelled']);
        $approved->forceFill(['booking_workflow' => ['refund' => ['status' => 'approved', 'amount_cents' => 90000]]])->save();
        $data = $this->report('revenue')->viewData('report');
        $this->assertEquals(500, $data['metrics'][1]['value']);
        $this->assertEquals(-500, $data['metrics'][2]['value']);
        $this->assertCount(2, $data['tables'][1]['rows']);
    }

    public function test_completed_services_use_departure_not_creation_or_payment_date(): void
    {
        Reservation::factory()->create(['status' => 'completed', 'total_price' => 1234, 'created_at' => '2026-08-01', 'check_out' => '2026-09-05']);
        Reservation::factory()->create(['status' => 'approved', 'total_price' => 9000, 'created_at' => '2026-09-01', 'check_out' => '2026-09-05', 'checked_out_at' => null]);
        $data = $this->report('services')->viewData('report');
        $this->assertEquals(1, $data['metrics'][0]['value']);
        $this->assertEquals(1234, $data['metrics'][1]['value']);
        $this->assertEquals(1234, array_sum(array_column($data['charts'][0]['rows'], 'value')));
        $this->assertEquals(1234, array_sum(array_column($data['charts'][1]['rows'], 'value')));
    }

    public function test_bookings_default_to_month_to_date_and_share_their_values_with_exports(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-17 12:00:00', ResortTime::timezone()));
        Reservation::factory()->create(['status' => 'approved', 'total_price' => 1234, 'created_at' => '2026-09-05']);
        Reservation::factory()->create(['status' => 'cancelled', 'total_price' => 9999, 'created_at' => '2026-09-05']);
        $response = $this->get(route('admin.reports.index'))->assertOk();
        $this->assertSame('2026-09-01', $response->viewData('startDate')->toDateString());
        $this->assertSame('2026-09-17', $response->viewData('endDate')->toDateString());
        $data = $response->viewData('report');
        $this->assertEquals(2, $data['metrics'][0]['value']);
        $this->assertEquals(1234, $data['metrics'][2]['value']);
        $this->assertEquals(1, $data['metrics'][3]['value']);
        $print = $this->get(route('admin.reports.export.pdf'))->assertOk();
        $this->assertSame($data, $print->viewData('report'));
        $csv = $this->get(route('admin.reports.export.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('1234', $csv);
        $this->assertStringContainsString('Confirmed booking value', $csv);
        $this->travelBack();
    }

    public function test_all_reports_render_empty_states_and_enforce_access_and_date_validation(): void
    {
        foreach (['executive', 'services', 'revenue', 'balances'] as $view) {
            $this->report($view)->assertSee('Reports and insights')->assertDontSee('How to read the Overview');
            $this->get(route('admin.reports.export.pdf', ['view' => $view]))->assertOk();
        }
        $this->get(route('admin.reports.index', ['start_date' => '2026-09-10', 'end_date' => '2026-09-01']))->assertSessionHasErrors('end_date');
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);
        $this->actingAs($customer)->get(route('admin.reports.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.reports.export.csv'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.reports.export.pdf'))->assertRedirect(route('admin.login'));
    }
}
