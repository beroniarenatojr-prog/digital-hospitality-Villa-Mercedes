<?php

namespace Tests\Feature\Admin;

use App\Enums\RateType;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRateManagementTest extends TestCase
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

    // --- Access Control ---

    public function test_unauthenticated_user_cannot_access_rate_management(): void
    {
        $response = $this->get(route('admin.rates.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_rate_management(): void
    {
        $response = $this->actingAs($this->customer)->get(route('admin.rates.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_access_rate_listing(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.rates.index'));
        $response->assertStatus(200);
        $response->assertSee('Rate Management');
    }

    // --- Listing & Filters ---

    public function test_rate_listing_displays_facility_and_type(): void
    {
        $rate = Rate::with('facility')->first();
        $this->assertNotNull($rate, 'At least one rate should be seeded.');

        $response = $this->actingAs($this->admin)->get(route('admin.rates.index'));
        $response->assertStatus(200);
        $response->assertSee($rate->facility->name);
        $response->assertSee($rate->type->label());
    }

    public function test_rate_listing_filters_by_type(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.rates.index', ['type' => 'base']));
        $response->assertStatus(200);
    }

    public function test_rate_listing_filters_by_active_state(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.rates.index', ['active' => '1']));
        $response->assertStatus(200);
    }

    // --- Creation ---

    public function test_admin_can_view_create_rate_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.rates.create'));
        $response->assertStatus(200);
        $response->assertSee('Create Rate');
    }

    public function test_admin_can_create_base_rate(): void
    {
        $facility = Facility::first();

        $response = $this->actingAs($this->admin)->post(route('admin.rates.store', $facility), [
            'name' => 'Test Base Rate',
            'type' => RateType::Base->value,
            'amount' => 2500.00,
            'unit' => 'per_night',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('rates', [
            'facility_id' => $facility->id,
            'name' => 'Test Base Rate',
            'type' => RateType::Base->value,
            'amount' => '2500.00',
        ]);
    }

    public function test_admin_can_create_seasonal_rate(): void
    {
        $facility = Facility::first();

        $response = $this->actingAs($this->admin)->post(route('admin.rates.store', $facility), [
            'name' => 'Summer Promo',
            'type' => RateType::Seasonal->value,
            'amount' => 3000.00,
            'unit' => 'per_night',
            'effective_from' => '2027-06-01',
            'effective_until' => '2027-08-31',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('rates', [
            'facility_id' => $facility->id,
            'name' => 'Summer Promo',
            'type' => RateType::Seasonal->value,
        ]);
    }

    public function test_admin_can_create_additional_charge(): void
    {
        $facility = Facility::first();

        $response = $this->actingAs($this->admin)->post(route('admin.rates.store', $facility), [
            'name' => 'Early Check-in',
            'type' => RateType::AdditionalCharge->value,
            'amount' => 500.00,
            'unit' => 'per_hour',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('rates', [
            'name' => 'Early Check-in',
            'type' => RateType::AdditionalCharge->value,
        ]);
    }

    public function test_admin_can_create_extra_person_rate(): void
    {
        $facility = Facility::first();

        $response = $this->actingAs($this->admin)->post(route('admin.rates.store', $facility), [
            'name' => 'Extra Person Charge',
            'type' => RateType::ExtraPerson->value,
            'amount' => 300.00,
            'unit' => 'per_person',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('rates', [
            'name' => 'Extra Person Charge',
            'type' => RateType::ExtraPerson->value,
        ]);
    }

    public function test_invalid_amount_is_rejected(): void
    {
        $facility = Facility::first();

        $response = $this->actingAs($this->admin)->post(route('admin.rates.store', $facility), [
            'name' => 'Bad Rate',
            'type' => RateType::Base->value,
            'amount' => -100,
            'unit' => 'per_night',
        ]);

        $response->assertSessionHasErrors(['amount']);
    }

    public function test_invalid_dates_are_rejected(): void
    {
        $facility = Facility::first();

        $response = $this->actingAs($this->admin)->post(route('admin.rates.store', $facility), [
            'name' => 'Bad Seasonal',
            'type' => RateType::Seasonal->value,
            'amount' => 1000.00,
            'unit' => 'per_night',
            'effective_from' => '2027-12-31',
            'effective_until' => '2027-01-01', // before effective_from
        ]);

        $response->assertSessionHasErrors(['effective_until']);
    }

    public function test_invalid_rate_type_is_rejected(): void
    {
        $facility = Facility::first();

        $response = $this->actingAs($this->admin)->post(route('admin.rates.store', $facility), [
            'name' => 'Bad Type',
            'type' => 'nonexistent_type',
            'amount' => 1000.00,
            'unit' => 'per_night',
        ]);

        $response->assertSessionHasErrors(['type']);
    }

    // --- Editing ---

    public function test_admin_can_edit_rate(): void
    {
        $rate = Rate::first();

        $response = $this->actingAs($this->admin)->put(route('admin.rates.update', $rate), [
            'name' => 'Updated Rate Name',
            'type' => $rate->type->value,
            'amount' => 5000.00,
            'unit' => $rate->unit,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $rate->refresh();
        $this->assertEquals('Updated Rate Name', $rate->name);
        $this->assertEquals('5000.00', $rate->amount);
    }

    public function test_editing_rate_does_not_change_historical_reservation_prices(): void
    {
        $facility = Facility::first();
        $rate = Rate::where('facility_id', $facility->id)->first();

        // Create a reservation with a known price
        $reservation = Reservation::factory()->create([
            'facility_id' => $facility->id,
            'user_id' => $this->customer->id,
            'total_price' => 5000.00,
        ]);

        $originalPrice = $reservation->total_price;

        // Edit the rate
        $this->actingAs($this->admin)->put(route('admin.rates.update', $rate), [
            'name' => $rate->name,
            'type' => $rate->type->value,
            'amount' => 99999.00,
            'unit' => $rate->unit,
        ]);

        // Reservation price must remain unchanged
        $reservation->refresh();
        $this->assertEquals($originalPrice, $reservation->total_price);
    }

    // --- Activation / Deactivation ---

    public function test_rate_can_be_activated(): void
    {
        $rate = Rate::first();
        $rate->update(['is_active' => false]);

        $response = $this->actingAs($this->admin)->patch(route('admin.rates.activate', $rate));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $rate->refresh();
        $this->assertTrue($rate->is_active);
    }

    public function test_rate_can_be_deactivated(): void
    {
        $rate = Rate::first();
        $rate->update(['is_active' => true]);

        $response = $this->actingAs($this->admin)->patch(route('admin.rates.deactivate', $rate));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $rate->refresh();
        $this->assertFalse($rate->is_active);
    }

    public function test_deactivation_does_not_delete_rate(): void
    {
        $rate = Rate::first();

        $this->actingAs($this->admin)->patch(route('admin.rates.deactivate', $rate));

        // Rate must still exist in the database
        $this->assertDatabaseHas('rates', ['id' => $rate->id]);
    }

    // --- Show Page ---

    public function test_admin_can_view_rate_detail(): void
    {
        $rate = Rate::first();

        $response = $this->actingAs($this->admin)->get(route('admin.rates.show', $rate));
        $response->assertStatus(200);
        $response->assertSee($rate->name);
        $response->assertSee($rate->type->label());
    }

    // --- Security ---

    public function test_customer_cannot_create_rate(): void
    {
        $facility = Facility::first();

        $response = $this->actingAs($this->customer)->post(route('admin.rates.store', $facility), [
            'name' => 'Hacked Rate',
            'type' => RateType::Base->value,
            'amount' => 1.00,
            'unit' => 'per_night',
        ]);

        $response->assertStatus(403);
    }

    public function test_customer_cannot_modify_rate(): void
    {
        $rate = Rate::first();

        $response = $this->actingAs($this->customer)->put(route('admin.rates.update', $rate), [
            'name' => 'Hacked',
            'type' => $rate->type->value,
            'amount' => 1.00,
            'unit' => 'per_night',
        ]);

        $response->assertStatus(403);
    }
}
