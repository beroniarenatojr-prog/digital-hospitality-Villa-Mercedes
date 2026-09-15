<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\ResortFee;
use App\Models\ResortPool;
use App\Models\ResortRule;
use App\Models\SwimmingSession;
use App\Models\User;
use Database\Seeders\CottageSeeder;
use Database\Seeders\FacilityTypeSeeder;
use Database\Seeders\FaqCategorySeeder;
use Database\Seeders\FaqSeeder;
use Database\Seeders\ResortServicesSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ResortServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, FacilityTypeSeeder::class, CottageSeeder::class, ResortServicesSeeder::class, FaqCategorySeeder::class, FaqSeeder::class]);
    }

    public function test_resort_page_publishes_verified_pool_cottage_menu_and_fee_information(): void
    {
        $this->get(route('resort.show'))
            ->assertOk()
            ->assertSee('Adult Pool')
            ->assertSee('Kiddie Pool')
            ->assertSee('9:00 AM')
            ->assertSee('Children are not allowed to swim during this session.')
            ->assertSee('Cottage reservation: ₱500 per session')
            ->assertSee('Ensaladang Talong')
            ->assertSee('0935 207 8289')
            ->assertSee('Catering, lights &amp; sounds', false);

        $this->assertDatabaseCount('facilities', 12);
        $this->assertDatabaseHas('resort_fees', ['name' => 'Catering, lights & sounds', 'amount' => 2000]);
    }

    public function test_resort_and_cottage_seeders_are_idempotent(): void
    {
        $this->seed([CottageSeeder::class, ResortServicesSeeder::class]);

        $this->assertDatabaseCount('facilities', 12);
        $this->assertDatabaseCount('swimming_sessions', 2);
        $this->assertDatabaseCount('resort_pools', 3);
        $this->assertDatabaseCount('menu_categories', 3);
        $this->assertDatabaseCount('menu_items', 17);
    }

    public function test_cottage_availability_is_per_cottage_and_session(): void
    {
        $session = SwimmingSession::query()->where('slug', 'day')->firstOrFail();
        $first = Facility::query()->where('slug', 'cottage-01')->firstOrFail();
        $date = now()->addDays(5)->toDateString();
        [$checkIn, $checkOut] = $session->periodFor($date);

        Reservation::factory()->create([
            'facility_id' => $first->id,
            'swimming_session_id' => $session->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'status' => ReservationStatus::Pending,
        ]);

        $data = $this->getJson(route('resort.cottages.availability', ['date' => $date, 'session' => 'day']))
            ->assertOk()
            ->json('data');

        $this->assertFalse(collect($data)->firstWhere('name', 'Cottage 01')['available']);
        $this->assertTrue(collect($data)->firstWhere('name', 'Cottage 02')['available']);
    }

    public function test_cottage_quote_uses_fixed_session_times_and_excludes_pool_entrance(): void
    {
        $cottage = Facility::query()->where('slug', 'cottage-01')->firstOrFail();
        $session = SwimmingSession::query()->where('slug', 'day')->firstOrFail();
        $date = now()->addDays(5)->toDateString();

        $response = $this->postJson(route('facilities.availability.calculate', $cottage), [
            'visit_date' => $date,
            'swimming_session_id' => $session->id,
            'guestCount' => 40,
            'check_in' => now()->addYear()->toDateTimeString(),
            'check_out' => now()->addYear()->addDay()->toDateTimeString(),
        ])->assertOk()->assertJsonPath('data.total_amount', 50000);

        $this->assertStringContainsString('09:00:00', $response->json('data.check_in'));
        $this->assertStringContainsString('17:00:00', $response->json('data.check_out'));
        $response->assertJsonPath('data.extra_person_charge', 0);
    }

    public function test_authenticated_cottage_reservation_persists_session_and_unlimited_party_size(): void
    {
        Notification::fake();
        Mail::fake();
        $user = User::factory()->create();
        $cottage = Facility::query()->where('slug', 'cottage-02')->firstOrFail();
        $session = SwimmingSession::query()->where('slug', 'day')->firstOrFail();

        $this->actingAs($user)->post(route('customer.reservations.store'), [
            'facility_id' => $cottage->id,
            'visit_date' => now()->addDays(6)->toDateString(),
            'swimming_session_id' => $session->id,
            'guestCount' => 40,
            'guest_name' => 'Cottage Guest',
            'guest_phone' => '09171234567',
            'guest_email' => 'cottage@example.com',
        ])->assertRedirect();

        $this->assertDatabaseHas('reservations', [
            'facility_id' => $cottage->id,
            'swimming_session_id' => $session->id,
            'guest_count' => 40,
            'base_price' => 500,
            'total_price' => 500,
        ]);

        $reservation = Reservation::query()->where('user_id', $user->id)->sole();
        $this->get(route('customer.reservations.show', $reservation))
            ->assertOk()
            ->assertSee($cottage->name)
            ->assertSee($session->name)
            ->assertSee('Pool entrance is paid separately at the entrance.');
    }

    public function test_cottage_reservation_succeeds_with_contact_details_only(): void
    {
        Notification::fake();
        Mail::fake();
        $user = User::factory()->create();
        $cottage = Facility::query()->where('slug', 'cottage-02')->firstOrFail();
        $session = SwimmingSession::query()->where('slug', 'day')->firstOrFail();

        $this->actingAs($user)->post(route('customer.reservations.store'), [
            'facility_id' => $cottage->id,
            'visit_date' => now()->addDays(7)->toDateString(),
            'swimming_session_id' => $session->id,
            'guestCount' => 8,
            'guest_name' => 'Day Cottage Guest',
            'guest_phone' => '09171234567',
            'guest_email' => 'day.cottage@example.com',
        ])->assertRedirect();

        $this->assertDatabaseHas('reservations', [
            'facility_id' => $cottage->id,
            'guest_name' => 'Day Cottage Guest',
            'guest_email' => 'day.cottage@example.com',
            'guest_valid_id' => null,
            'guest_signature' => null,
        ]);
    }

    public function test_cottage_reschedule_redirects_with_feedback_without_changing_the_booking(): void
    {
        $user = User::factory()->create();
        $cottage = Facility::query()->where('slug', 'cottage-01')->firstOrFail();
        $session = SwimmingSession::query()->where('slug', 'day')->firstOrFail();
        [$checkIn, $checkOut] = $session->periodFor(now()->addDays(6)->toDateString());
        $reservation = Reservation::factory()->create([
            'user_id' => $user->id,
            'facility_id' => $cottage->id,
            'swimming_session_id' => $session->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'status' => ReservationStatus::Pending,
            'total_price' => 500,
        ]);

        $this->actingAs($user)->patch(route('customer.reservations.reschedule', $reservation), [
            'facility_id' => $cottage->id,
            'check_in' => $checkIn->copy()->addDay()->toDateTimeString(),
            'check_out' => $checkOut->copy()->addDay()->toDateTimeString(),
            'guestCount' => 10,
        ])->assertRedirect(route('customer.reservations.show', $reservation))
            ->assertSessionHas('error', 'Cottage reservations use fixed swimming sessions. Please contact the resort if you need to change the date, session, or cottage.');

        $this->get(route('customer.reservations.show', $reservation))->assertOk();
        $reservation->refresh();
        $this->assertTrue($reservation->check_in->equalTo($checkIn));
        $this->assertTrue($reservation->check_out->equalTo($checkOut));
        $this->assertEquals(500, $reservation->total_price);
    }

    public function test_administrator_can_edit_resort_content_and_public_visibility(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);
        $pool = ResortPool::query()->firstOrFail();
        $rule = ResortRule::query()->firstOrFail();
        $item = MenuItem::query()->firstOrFail();

        $pools = ResortPool::all()->mapWithKeys(fn (ResortPool $row) => [$row->id => [
            'name' => $row->is($pool) ? 'Updated Adult Pool' : $row->name,
            'description' => $row->description,
            'audience_note' => $row->audience_note,
            'sort_order' => $row->sort_order,
            'is_active' => (int) $row->is_active,
            'is_package_only' => (int) $row->is_package_only,
        ]])->all();
        $sessions = SwimmingSession::all()->mapWithKeys(fn (SwimmingSession $row) => [$row->id => [
            'name' => $row->name,
            'starts_at' => substr($row->starts_at, 0, 5),
            'ends_at' => substr($row->ends_at, 0, 5),
            'adult_entrance_fee' => $row->adult_entrance_fee,
            'child_entrance_fee' => $row->child_entrance_fee,
            'child_qualifier' => $row->child_qualifier,
            'cottage_fee' => $row->cottage_fee,
            'children_allowed' => (int) $row->children_allowed,
            'is_active' => (int) $row->is_active,
            'sort_order' => $row->sort_order,
        ]])->all();
        $rules = ResortRule::all()->mapWithKeys(fn (ResortRule $row) => [$row->id => [
            'text' => $row->text,
            'is_active' => (int) $row->is_active,
            'is_public' => $row->is($rule) ? 0 : (int) $row->is_public,
            'sort_order' => $row->sort_order,
        ]])->all();
        $fees = ResortFee::all()->mapWithKeys(fn (ResortFee $row) => [$row->id => [
            'name' => $row->name, 'amount' => $row->amount, 'unit' => $row->unit,
            'description' => $row->description, 'is_active' => (int) $row->is_active, 'sort_order' => $row->sort_order,
        ]])->all();
        $categories = MenuCategory::all()->mapWithKeys(fn (MenuCategory $row) => [$row->id => [
            'name' => $row->name, 'subtitle' => $row->subtitle,
            'is_active' => (int) $row->is_active, 'sort_order' => $row->sort_order,
        ]])->all();
        $items = MenuItem::all()->mapWithKeys(fn (MenuItem $row) => [$row->id => [
            'menu_category_id' => $row->menu_category_id, 'name' => $row->name, 'note' => $row->note,
            'price' => $row->is($item) ? 199 : $row->price,
            'is_available' => (int) $row->is_available, 'sort_order' => $row->sort_order,
        ]])->all();

        $this->actingAs($admin)->get(route('admin.resort-services.edit'))->assertOk();
        $this->actingAs($admin)->put(route('admin.resort-services.update'), [
            'settings' => [
                'cafe_hours' => 'Daily, 7:00 AM–10:00 PM',
                'cafe_viber' => '0935 207 8289',
                'cafe_service_charge' => 3,
                'cafe_outside_food_notice' => 'Outside food is not allowed.',
            ],
            ...compact('pools', 'sessions', 'rules', 'fees', 'categories', 'items'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('resort_pools', ['id' => $pool->id, 'name' => 'Updated Adult Pool']);
        $this->assertDatabaseHas('resort_rules', ['id' => $rule->id, 'is_public' => false]);
        $this->assertDatabaseHas('menu_items', ['id' => $item->id, 'price' => 199]);
        $this->get(route('resort.show'))->assertDontSee($rule->text);
    }
}
