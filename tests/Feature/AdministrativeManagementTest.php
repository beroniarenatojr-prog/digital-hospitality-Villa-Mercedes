<?php

namespace Tests\Feature;

use App\Enums\FacilityStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RateType;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Exceptions\Admin\UnauthorizedAdminActionException;
use App\Exceptions\Payment\UnauthorizedPaymentActionException;
use App\Models\Amenity;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Rate;
use App\Models\User;
use App\Services\Admin\CustomerManagementService;
use App\Services\Admin\FacilityManagementService;
use App\Services\Admin\RateManagementService;
use App\Services\AvailabilityService;
use App\Services\PaymentService;
use App\Services\PricingService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdministrativeManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    
    private FacilityManagementService $facilityService;
    private RateManagementService $rateService;
    private CustomerManagementService $customerService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->artisan('db:seed');

        $this->facilityService = app(FacilityManagementService::class);
        $this->rateService = app(RateManagementService::class);
        $this->customerService = app(CustomerManagementService::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->customer = User::factory()->create();
        $this->customer->assignRole(Role::Customer->value);
    }

    public function test_customer_cannot_manage_facilities()
    {
        $this->expectException(UnauthorizedAdminActionException::class);
        $this->facilityService->createFacility($this->customer, []);
    }

    public function test_customer_cannot_manage_rates()
    {
        $this->expectException(UnauthorizedAdminActionException::class);
        $this->rateService->createRate($this->customer, []);
    }

    public function test_customer_cannot_retrieve_customer_info()
    {
        $this->expectException(UnauthorizedAdminActionException::class);
        $this->customerService->getCustomer($this->customer, $this->customer->id);
    }

    public function test_admin_can_create_facility()
    {
        $type = FacilityType::factory()->create();
        
        $facility = $this->facilityService->createFacility($this->admin, [
            'facility_type_id' => $type->id,
            'name' => 'Admin Created Room',
            'capacity' => 4,
            'status' => FacilityStatus::Available,
        ]);

        $this->assertDatabaseHas('facilities', [
            'id' => $facility->id,
            'name' => 'Admin Created Room',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $this->assertTrue(Activity::where('event', 'facility_created')->exists());
    }

    public function test_admin_can_update_facility()
    {
        $facility = Facility::factory()->create(['name' => 'Old Name']);

        $updated = $this->facilityService->updateFacility($this->admin, $facility, [
            'name' => 'New Name',
        ]);

        $this->assertEquals('New Name', $updated->name);
        $this->assertTrue(Activity::where('event', 'facility_updated')->exists());
    }

    public function test_admin_can_change_facility_status_and_affects_availability()
    {
        $facility = Facility::factory()->create(['status' => FacilityStatus::Available]);
        
        $this->facilityService->changeStatus($this->admin, $facility, FacilityStatus::Maintenance);

        $this->assertEquals(FacilityStatus::Maintenance, $facility->refresh()->status);
        $this->assertTrue(Activity::where('event', 'facility_status_changed')->exists());

        $availability = app(AvailabilityService::class)->isAvailable(
            $facility,
            Carbon::tomorrow(),
            Carbon::tomorrow()->addDays(2)
        );

        $this->assertFalse($availability->isAvailable);
    }

    public function test_admin_can_synchronize_amenities_without_duplicates()
    {
        $facility = Facility::factory()->create();
        $amenity1 = Amenity::factory()->create();
        $amenity2 = Amenity::factory()->create();

        $this->facilityService->assignAmenities($this->admin, $facility, [$amenity1->id, $amenity2->id]);
        $this->assertCount(2, $facility->refresh()->amenities);

        $this->facilityService->assignAmenities($this->admin, $facility, [$amenity1->id]);
        $this->assertCount(1, $facility->refresh()->amenities);
        $this->assertTrue(Activity::where('event', 'amenities_assigned')->exists());
    }

    public function test_invalid_amenity_id_is_rejected()
    {
        $facility = Facility::factory()->create();
        
        $this->expectException(InvalidArgumentException::class);
        $this->facilityService->assignAmenities($this->admin, $facility, [999]);
    }

    public function test_facility_soft_deletion_protects_historical_records()
    {
        $facility = Facility::factory()->create();
        
        $this->facilityService->deleteFacility($this->admin, $facility);
        
        $this->assertSoftDeleted($facility);
        $this->assertTrue(Activity::where('event', 'facility_deleted')->exists());
    }

    public function test_admin_can_create_base_rate()
    {
        $facility = Facility::factory()->create();
        
        $rate = $this->rateService->createRate($this->admin, [
            'facility_id' => $facility->id,
            'name' => 'Base Admin Rate',
            'type' => RateType::Base,
            'amount' => 500.50,
            'unit' => 'per_night',
        ]);

        $this->assertEquals(500.50, $rate->amount);
        $this->assertEquals(RateType::Base, $rate->type);
        $this->assertTrue(Activity::where('event', 'rate_created')->exists());
    }

    public function test_admin_can_create_seasonal_rate()
    {
        $facility = Facility::factory()->create();
        
        $rate = $this->rateService->createRate($this->admin, [
            'facility_id' => $facility->id,
            'name' => 'Summer Rate',
            'type' => RateType::Seasonal,
            'amount' => 600.00,
            'effective_from' => '2026-06-01',
            'effective_until' => '2026-08-31',
        ]);

        $this->assertEquals('2026-06-01', $rate->effective_from->format('Y-m-d'));
        $this->assertEquals('2026-08-31', $rate->effective_until->format('Y-m-d'));
    }

    public function test_invalid_rate_amounts_are_rejected()
    {
        $facility = Facility::factory()->create();
        
        $this->expectException(InvalidArgumentException::class);
        $this->rateService->createRate($this->admin, [
            'facility_id' => $facility->id,
            'name' => 'Invalid',
            'amount' => -10,
        ]);
    }

    public function test_invalid_seasonal_dates_are_rejected()
    {
        $facility = Facility::factory()->create();
        
        $this->expectException(InvalidArgumentException::class);
        $this->rateService->createRate($this->admin, [
            'facility_id' => $facility->id,
            'name' => 'Invalid Summer',
            'type' => RateType::Seasonal,
            'amount' => 100,
            'effective_from' => '2026-08-31',
            'effective_until' => '2026-06-01', // Before from
        ]);
    }

    public function test_admin_can_update_rate()
    {
        $rate = Rate::factory()->create(['amount' => 100, 'name' => 'Old Rate']);
        
        $updated = $this->rateService->updateRate($this->admin, $rate, [
            'amount' => 200,
        ]);

        $this->assertEquals(200, $updated->amount);
        $this->assertTrue(Activity::where('event', 'rate_updated')->exists());
    }

    public function test_admin_can_activate_and_deactivate_rates()
    {
        $rate = Rate::factory()->create(['is_active' => true]);
        
        $this->rateService->deactivateRate($this->admin, $rate);
        $this->assertFalse($rate->refresh()->is_active);
        $this->assertTrue(Activity::where('event', 'rate_deactivated')->exists());

        $this->rateService->activateRate($this->admin, $rate);
        $this->assertTrue($rate->refresh()->is_active);
        $this->assertTrue(Activity::where('event', 'rate_activated')->exists());
    }

    public function test_admin_can_securely_retrieve_customer_without_exposing_sensitive_fields()
    {
        $retrieved = $this->customerService->getCustomer($this->admin, $this->customer->id);
        
        $array = $retrieved->toArray();
        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertEquals($this->customer->id, $array['id']);
    }

    public function test_admin_can_securely_retrieve_customer_with_reservations()
    {
        $retrieved = $this->customerService->getCustomerWithReservations($this->admin, $this->customer->id);
        
        $this->assertTrue($retrieved->relationLoaded('reservations'));
        $array = $retrieved->toArray();
        $this->assertArrayNotHasKey('password', $array);
    }

    public function test_administrative_reservation_actions_delegate_to_reservation_service()
    {
        $facility = Facility::factory()->create(['status' => FacilityStatus::Available]);
        Rate::factory()->create(['facility_id' => $facility->id, 'type' => RateType::Base, 'is_active' => true]);
        
        $reservationService = app(ReservationService::class);
        
        // Use customer to make it pending
        $reservation = $reservationService->createReservation(
            $facility,
            $this->customer,
            Carbon::tomorrow()->setHour(14)->setMinute(0),
            Carbon::tomorrow()->addDays(2)->setHour(12)->setMinute(0),
            ReservationSource::Online,
            2
        );

        $this->assertEquals(ReservationStatus::Pending, $reservation->status);

        // Current policy reserves the booking only after the initial payment is verified.
        $payments = app(PaymentService::class);
        $deposit = $payments->createPayment($reservation, $this->customer, $reservation->requiredDepositAmount(), PaymentMethod::GCash, 'SYNTHETIC-ADMIN-APPROVAL');
        $payments->verifyPayment($deposit, $this->admin);
        $reservationService->changeStatus($reservation, ReservationStatus::Approved, $this->admin);
        $this->assertEquals(ReservationStatus::Approved, $reservation->refresh()->status);
    }

    public function test_administrative_payment_actions_delegate_to_payment_service()
    {
        $facility = Facility::factory()->create(['status' => FacilityStatus::Available]);
        Rate::factory()->create(['facility_id' => $facility->id, 'type' => RateType::Base, 'is_active' => true]);

        $reservationService = app(ReservationService::class);
        $paymentService = app(PaymentService::class);

        $reservation = $reservationService->createReservation(
            $facility,
            $this->customer,
            Carbon::tomorrow()->setHour(14)->setMinute(0),
            Carbon::tomorrow()->addDays(2)->setHour(12)->setMinute(0),
            ReservationSource::Online,
            2
        );

        $payment = $paymentService->createPayment(
            $reservation,
            $this->customer,
            $reservation->requiredDepositAmount(),
            PaymentMethod::GCash,
            '123456789'
        );

        // Customer cannot verify
        try {
            $paymentService->verifyPayment($payment, $this->customer);
        } catch (\Exception $e) {
            $this->assertInstanceOf(UnauthorizedPaymentActionException::class, $e);
        }

        // Admin can verify
        $paymentService->verifyPayment($payment, $this->admin);
        $this->assertEquals(PaymentStatus::Verified, $payment->refresh()->status);
        $reservationService->changeStatus($reservation, ReservationStatus::Approved, $this->admin);
        $this->assertEquals(ReservationStatus::Approved, $reservation->fresh()->status);
    }
}
