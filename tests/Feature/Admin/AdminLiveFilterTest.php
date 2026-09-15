<?php

namespace Tests\Feature\Admin;

use App\Enums\FacilityStatus;
use App\Enums\InquiryStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Inquiry;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Support\LiveFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLiveFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->customer = User::factory()->create();
        $this->customer->assignRole(Role::Customer->value);

        $this->facility = Facility::firstOrFail();
    }

    public function test_listing_pages_expose_live_filters_without_apply_buttons(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index'))
            ->assertOk()
            ->assertSee('data-live-filter', false)
            ->assertSee('data-live-filter-reset', false)
            ->assertSee('Search reservations')
            ->assertDontSee('Find guest')
            ->assertDontSee('Apply filters');

        $this->actingAs($this->admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('data-live-filter', false)
            ->assertSee('Space type')
            ->assertDontSee('>Search</button>', false);

        $this->actingAs($this->admin)
            ->get(route('admin.facilities.index'))
            ->assertOk()
            ->assertSee('data-live-filter', false)
            ->assertDontSee('>Filter</button>', false);
    }

    public function test_reservation_live_filter_returns_partial_html_for_combined_filters(): void
    {
        $matching = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending->value,
            'reservation_source' => ReservationSource::WalkIn->value,
        ]);
        $wrongSource = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending->value,
            'reservation_source' => ReservationSource::Online->value,
        ]);
        $wrongStatus = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
            'reservation_source' => ReservationSource::WalkIn->value,
        ]);

        $response = $this->liveFilter('admin.reservations.index', [
            'status' => ReservationStatus::Pending->value,
            'source' => ReservationSource::WalkIn->value,
            'facility_id' => $this->facility->id,
            'facility_type' => $this->facility->facilityType->slug,
        ]);

        $response->assertOk();
        $response->assertSee($matching->reservation_code);
        $response->assertDontSee($wrongSource->reservation_code);
        $response->assertDontSee($wrongStatus->reservation_code);
        $response->assertDontSee('Reservation Management');
        $response->assertDontSee('Search reservations');
        $response->assertDontSee('Arriving today');
    }

    public function test_reservation_search_combines_with_status_and_facility_type(): void
    {
        $this->customer->forceFill(['email' => 'livefilter.guest@example.com'])->save();
        $otherType = FacilityType::query()
            ->where('id', '!=', $this->facility->facility_type_id)
            ->firstOrFail();
        $otherFacility = Facility::factory()->create([
            'facility_type_id' => $otherType->id,
            'is_active' => true,
        ]);

        $matching = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending->value,
        ]);
        $wrongStatus = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
        ]);
        $wrongType = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $otherFacility->id,
            'status' => ReservationStatus::Pending->value,
        ]);

        $response = $this->liveFilter('admin.reservations.index', [
            'search' => 'livefilter.guest@example.com',
            'status' => ReservationStatus::Pending->value,
            'facility_type' => $this->facility->facilityType->slug,
        ]);

        $response->assertOk();
        $response->assertSee($matching->reservation_code);
        $response->assertDontSee($wrongStatus->reservation_code);
        $response->assertDontSee($wrongType->reservation_code);
    }

    public function test_unique_guest_search_stays_on_the_list(): void
    {
        $reservation = Reservation::factory()->create([
            'reservation_code' => 'VM-20260910-LIVE01',
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['search' => 'VM-20260910-LIVE01']))
            ->assertOk()
            ->assertSee($reservation->reservation_code)
            ->assertSee('1 matching reservation');
    }

    public function test_live_filter_search_does_not_redirect_a_unique_match(): void
    {
        $reservation = Reservation::factory()->create([
            'reservation_code' => 'VM-20260910-LIVE02',
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
        ]);

        $this->liveFilter('admin.reservations.index', ['search' => 'VM-20260910-LIVE02'])
            ->assertOk()
            ->assertSee($reservation->reservation_code)
            ->assertSee('1 matching reservation');
    }

    public function test_payment_live_filter_combines_status_method_and_space(): void
    {
        $otherFacility = Facility::query()->where('id', '!=', $this->facility->id)->firstOrFail();

        $matchingReservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Approved->value,
        ]);
        $otherSpaceReservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $otherFacility->id,
            'status' => ReservationStatus::Approved->value,
        ]);

        Payment::factory()->create([
            'reservation_id' => $matchingReservation->id,
            'amount' => 1111.00,
            'method' => PaymentMethod::GCash->value,
            'status' => PaymentStatus::Pending->value,
            'payment_date' => now()->toDateString(),
        ]);
        Payment::factory()->create([
            'reservation_id' => $matchingReservation->id,
            'amount' => 2222.00,
            'method' => PaymentMethod::BankTransfer->value,
            'status' => PaymentStatus::Pending->value,
            'payment_date' => now()->toDateString(),
        ]);
        Payment::factory()->create([
            'reservation_id' => $otherSpaceReservation->id,
            'amount' => 3333.00,
            'method' => PaymentMethod::GCash->value,
            'status' => PaymentStatus::Pending->value,
            'payment_date' => now()->toDateString(),
        ]);

        $response = $this->liveFilter('admin.payments.index', [
            'status' => PaymentStatus::Pending->value,
            'method' => PaymentMethod::GCash->value,
            'facility_id' => $this->facility->id,
            'facility_type' => $this->facility->facilityType->slug,
        ]);

        $response->assertOk();
        $response->assertSee('1,111.00');
        $response->assertDontSee('2,222.00');
        $response->assertDontSee('3,333.00');
        $response->assertDontSee('Payment Management');
        $response->assertDontSee('Find a payment');
    }

    public function test_facility_empty_active_filter_does_not_hide_records(): void
    {
        $inactive = Facility::factory()->create([
            'name' => 'Quiet Annex Hall',
            'is_active' => false,
            'facility_type_id' => $this->facility->facility_type_id,
        ]);

        $this->liveFilter('admin.facilities.index', ['active' => ''])
            ->assertOk()
            ->assertSee($inactive->name)
            ->assertSee($this->facility->name)
            ->assertDontSee('Facility Management');
    }

    public function test_facility_search_combines_with_type_status_and_active(): void
    {
        $otherType = FacilityType::query()
            ->where('id', '!=', $this->facility->facility_type_id)
            ->firstOrFail();

        $matching = Facility::factory()->create([
            'name' => 'Cedar Livefilter Suite',
            'slug' => 'cedar-livefilter-suite',
            'facility_type_id' => $this->facility->facility_type_id,
            'status' => FacilityStatus::Available->value,
            'is_active' => true,
        ]);
        $wrongType = Facility::factory()->create([
            'name' => 'Cedar Livefilter Hall',
            'slug' => 'cedar-livefilter-hall',
            'facility_type_id' => $otherType->id,
            'status' => FacilityStatus::Available->value,
            'is_active' => true,
        ]);
        $wrongStatus = Facility::factory()->create([
            'name' => 'Cedar Livefilter Annex',
            'slug' => 'cedar-livefilter-annex',
            'facility_type_id' => $this->facility->facility_type_id,
            'status' => FacilityStatus::Maintenance->value,
            'is_active' => true,
        ]);
        $inactive = Facility::factory()->create([
            'name' => 'Cedar Livefilter Cottage',
            'slug' => 'cedar-livefilter-cottage',
            'facility_type_id' => $this->facility->facility_type_id,
            'status' => FacilityStatus::Available->value,
            'is_active' => false,
        ]);

        $response = $this->liveFilter('admin.facilities.index', [
            'search' => 'Cedar Livefilter',
            'type' => $this->facility->facilityType->slug,
            'status' => FacilityStatus::Available->value,
            'active' => '1',
        ]);

        $response->assertOk();
        $response->assertSee($matching->name);
        $response->assertDontSee($wrongType->name);
        $response->assertDontSee($wrongStatus->name);
        $response->assertDontSee($inactive->name);
    }

    public function test_customer_and_inquiry_live_filters_return_partials(): void
    {
        $this->liveFilter('admin.customers.index', ['search' => $this->customer->email])
            ->assertOk()
            ->assertSee($this->customer->name)
            ->assertDontSee('Customer Management');

        $inquiry = Inquiry::factory()->create([
            'user_id' => $this->customer->id,
            'subject' => 'Garden villa availability',
            'status' => InquiryStatus::Pending->value,
        ]);
        Inquiry::factory()->create([
            'user_id' => $this->customer->id,
            'subject' => 'Closed banquet question',
            'status' => InquiryStatus::Closed->value,
        ]);

        $this->liveFilter('admin.inquiries.index', [
            'status' => InquiryStatus::Pending->value,
            'search' => 'Garden villa',
        ])
            ->assertOk()
            ->assertSee($inquiry->subject)
            ->assertDontSee('Closed banquet question')
            ->assertDontSee('Inquiry Management');
    }

    public function test_rates_and_activity_log_live_filters_return_partials(): void
    {
        $this->liveFilter('admin.rates.index')
            ->assertOk()
            ->assertDontSee('Rate Management')
            ->assertDontSee('Create Rate');

        $this->liveFilter('admin.activity-log.index', ['staff_id' => $this->admin->id])
            ->assertOk()
            ->assertDontSee('cannot be edited or deleted')
            ->assertDontSee('Activity Log');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function liveFilter(string $routeName, array $params = [])
    {
        return $this->actingAs($this->admin)
            ->withHeaders([LiveFilter::HEADER => '1'])
            ->get(route($routeName, $params));
    }
}
