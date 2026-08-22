<?php

namespace Tests\Feature\Admin;

use App\Enums\FacilityStatus;
use App\Enums\FacilityType;
use App\Enums\Role;
use App\Models\Amenity;
use App\Models\Facility;
use App\Models\FacilityType as FacilityTypeModel;
use App\Models\PackageComponent;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class AdminFacilityManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->customer = User::factory()->create();
        $this->customer->assignRole(Role::Customer->value);
    }

    // --- Authorization ---

    public function test_unauthenticated_user_cannot_access_facility_management(): void
    {
        $response = $this->get(route('admin.facilities.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_facility_management(): void
    {
        $response = $this->actingAs($this->customer)->get(route('admin.facilities.index'));
        $response->assertStatus(403);
    }

    // --- Facility Listing ---

    public function test_admin_can_view_facility_listing(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.facilities.index'));
        $response->assertStatus(200);
        $response->assertSee('Facility Management');
    }

    public function test_admin_can_filter_facilities_by_type(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.facilities.index', ['type' => 'room']));
        $response->assertStatus(200);
    }

    public function test_admin_can_filter_facilities_by_status(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.facilities.index', ['status' => 'available']));
        $response->assertStatus(200);
    }

    // --- Facility Creation ---

    public function test_admin_can_view_create_facility_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.facilities.create'));
        $response->assertStatus(200);
        $response->assertSee('Create Facility');
    }

    public function test_admin_can_create_facility(): void
    {
        $facilityType = FacilityTypeModel::first();

        $response = $this->actingAs($this->admin)->post(route('admin.facilities.store'), [
            'name' => 'Test New Room',
            'facility_type_id' => $facilityType->id,
            'description' => 'A test room',
            'base_capacity' => 4,
            'max_capacity' => 6,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('facilities', [
            'name' => 'Test New Room',
            'capacity' => 4,
            'max_capacity' => 6,
        ]);
    }

    public function test_invalid_facility_data_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.facilities.store'), [
            'name' => '',
            'facility_type_id' => 9999,
            'base_capacity' => 0,
            'max_capacity' => -1,
        ]);

        $response->assertSessionHasErrors(['name', 'facility_type_id', 'base_capacity', 'max_capacity']);
    }

    // --- Facility Update ---

    public function test_admin_can_update_facility(): void
    {
        $facility = Facility::first();

        $response = $this->actingAs($this->admin)->put(route('admin.facilities.update', $facility), [
            'name' => 'Updated Facility Name',
            'description' => 'Updated description',
            'base_capacity' => 10,
            'max_capacity' => 15,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $facility->refresh();
        $this->assertEquals('Updated Facility Name', $facility->name);
        $this->assertEquals(10, $facility->capacity);
        $this->assertEquals(15, $facility->max_capacity);
    }

    // --- Status Management ---

    public function test_admin_can_change_facility_status(): void
    {
        $facility = Facility::first();

        $response = $this->actingAs($this->admin)->patch(route('admin.facilities.status', $facility), [
            'status' => FacilityStatus::Maintenance->value,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $facility->refresh();
        $this->assertEquals(FacilityStatus::Maintenance, $facility->status);
    }

    // --- Amenity Management ---

    public function test_admin_can_manage_amenities(): void
    {
        $facility = Facility::first();
        $amenities = Amenity::where('is_active', true)->take(3)->pluck('id')->toArray();

        $response = $this->actingAs($this->admin)->post(route('admin.facilities.amenities', $facility), [
            'amenities' => $amenities,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertCount(count($amenities), $facility->fresh()->amenities()->get());
    }

    // --- Package Component Management ---

    public function test_admin_can_manage_package_components(): void
    {
        $packageType = FacilityTypeModel::where('slug', FacilityType::ResortPackage->value)->first();
        if (!$packageType) {
            $this->markTestSkipped('No resort package type seeded.');
        }

        $package = Facility::where('facility_type_id', $packageType->id)->first();
        if (!$package) {
            $this->markTestSkipped('No resort package facility seeded.');
        }

        $nonPackageFacilities = Facility::where('id', '!=', $package->id)->take(2)->pluck('id')->toArray();

        $response = $this->actingAs($this->admin)->post(route('admin.facilities.components', $package), [
            'components' => $nonPackageFacilities,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(
            count($nonPackageFacilities),
            PackageComponent::where('package_facility_id', $package->id)->count()
        );
    }

    public function test_duplicate_components_are_prevented(): void
    {
        $packageType = FacilityTypeModel::where('slug', FacilityType::ResortPackage->value)->first();
        if (!$packageType) {
            $this->markTestSkipped('No resort package type seeded.');
        }

        $package = Facility::where('facility_type_id', $packageType->id)->first();
        if (!$package) {
            $this->markTestSkipped('No resort package facility seeded.');
        }

        $component = Facility::where('id', '!=', $package->id)->first();

        // Send same component twice — should be deduplicated
        $response = $this->actingAs($this->admin)->post(route('admin.facilities.components', $package), [
            'components' => [$component->id, $component->id],
        ]);

        $response->assertRedirect();
        $this->assertEquals(
            1,
            PackageComponent::where('package_facility_id', $package->id)
                ->where('component_facility_id', $component->id)
                ->count()
        );
    }

    // --- Soft Delete / Archive ---

    public function test_archiving_does_not_destroy_historical_reservations(): void
    {
        $facility = Facility::first();

        // Create a reservation tied to this facility
        $reservation = Reservation::factory()->create([
            'facility_id' => $facility->id,
            'user_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.facilities.destroy', $facility));

        $response->assertRedirect(route('admin.facilities.index'));
        $response->assertSessionHas('success');

        // Facility is soft-deleted
        $this->assertSoftDeleted('facilities', ['id' => $facility->id]);

        // Reservation still exists
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
    }

    // --- Show Page ---

    public function test_admin_can_view_facility_show_page(): void
    {
        $facility = Facility::first();

        $response = $this->actingAs($this->admin)->get(route('admin.facilities.show', $facility));
        $response->assertStatus(200);
        $response->assertSee($facility->name);
        $response->assertSee('Capacity');
        $response->assertSee('Amenities');
    }

    // --- Capacity Display ---

    public function test_capacity_information_is_shown(): void
    {
        $facility = Facility::first();

        $response = $this->actingAs($this->admin)->get(route('admin.facilities.show', $facility));
        $response->assertStatus(200);
        $response->assertSee('Standard Capacity');
        $response->assertSee('Maximum Capacity');
    }
}
