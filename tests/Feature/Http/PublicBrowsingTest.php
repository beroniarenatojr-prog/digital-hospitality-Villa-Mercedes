<?php

namespace Tests\Feature\Http;

use App\Enums\FacilityStatus;
use App\Enums\FacilityType as FacilityTypeEnum;
use App\Enums\RateType;
use App\Models\Amenity;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Rate;
use Database\Seeders\FacilityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBrowsingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FacilityTypeSeeder::class);
    }

    private function createFacility(string $typeSlug, bool $isActive = true, string $status = 'available'): Facility
    {
        $type = FacilityType::where('slug', $typeSlug)->first();
        
        return Facility::factory()->create([
            'facility_type_id' => $type->id,
            'is_active' => $isActive,
            'status' => $status,
            'sort_order' => 1
        ]);
    }

    private function createBaseRate(Facility $facility): void
    {
        Rate::factory()->create([
            'facility_id' => $facility->id,
            'type' => RateType::Base,
            'is_active' => true,
            'amount' => 1000.00
        ]);
    }

    public function test_home_page_responds_successfully()
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertViewIs('pages.home');
    }

    public function test_rooms_listing_displays_active_rooms_only()
    {
        $activeRoom = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $this->createBaseRate($activeRoom);
        
        $inactiveRoom = $this->createFacility(FacilityTypeEnum::Room->value, false);

        $response = $this->get(route('rooms'));

        $response->assertStatus(200);
        $response->assertSee($activeRoom->name);
        $response->assertDontSee($inactiveRoom->name);
    }

    public function test_halls_listing_displays_active_halls_only()
    {
        $activeHall = $this->createFacility(FacilityTypeEnum::FunctionHall->value, true);
        $this->createBaseRate($activeHall);

        $response = $this->get(route('halls'));

        $response->assertStatus(200);
        $response->assertSee($activeHall->name);
    }

    public function test_packages_listing_displays_active_packages_only()
    {
        $activePackage = $this->createFacility(FacilityTypeEnum::ResortPackage->value, true);
        $this->createBaseRate($activePackage);

        $response = $this->get(route('packages'));

        $response->assertStatus(200);
        $response->assertSee($activePackage->name);
    }

    public function test_facility_detail_page_renders_correctly()
    {
        $room = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $this->createBaseRate($room);
        
        $amenity = Amenity::factory()->create(['name' => 'Free Wi-Fi']);
        $room->amenities()->attach($amenity->id);

        $response = $this->get(route('facilities.show', $room));

        $response->assertStatus(200);
        $response->assertSee($room->name);
        $response->assertSee($room->description);
        $response->assertSee('Free Wi-Fi');
        $response->assertSee('1,000.00'); // Check price formatting
    }

    public function test_inactive_facility_detail_returns_404()
    {
        $room = $this->createFacility(FacilityTypeEnum::Room->value, false);

        $response = $this->get(route('facilities.show', $room));

        $response->assertStatus(404);
    }

    public function test_package_detail_displays_included_resources()
    {
        $package = $this->createFacility(FacilityTypeEnum::ResortPackage->value, true);
        $this->createBaseRate($package);
        
        $hallComponent = $this->createFacility(FacilityTypeEnum::FunctionHall->value, true);
        
        \App\Models\PackageComponent::create([
            'package_facility_id' => $package->id,
            'component_facility_id' => $hallComponent->id,
        ]);

        $response = $this->get(route('facilities.show', $package));

        $response->assertStatus(200);
        $response->assertSee('Included Resources');
        $response->assertSee($hallComponent->name);
    }
}

