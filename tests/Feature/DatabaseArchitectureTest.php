<?php

namespace Tests\Feature;

use App\Enums\FacilityType as FacilityTypeEnum;
use App\Enums\PaymentStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Inquiry;
use App\Models\Payment;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_can_be_seeded_cleanly(): void
    {
        $this->artisan('db:seed')->assertSuccessful();

        // Verify seeded data
        $this->assertDatabaseHas('facility_types', [
            'slug' => FacilityTypeEnum::Room->value,
        ]);

        $this->assertDatabaseHas('facilities', [
            'slug' => Str::slug('Deluxe (Room 26)'),
        ]);

        $this->assertDatabaseHas('rates', [
            'name' => 'Base Rate',
            'amount' => 2500, // Deluxe rate
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'customer@villamercedes.test',
        ]);

        $this->assertDatabaseHas('resort_pools', [
            'slug' => 'adult-pool',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('swimming_sessions', [
            'slug' => 'day',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('resort_fees', [
            'name' => 'Catering, lights & sounds',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('menu_categories', [
            'slug' => 'viands',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('resort_settings', [
            'key' => 'cafe_hours',
        ]);

        $admin = User::query()->where('email', 'admin@villamercedes.test')->firstOrFail();
        $this->assertTrue($admin->hasRole(Role::Administrator->value));
        $this->assertTrue(Hash::check('Password123!', $admin->password));

        $secondAdmin = User::query()->where('email', 'admin2@villamercedes.test')->firstOrFail();
        $this->assertTrue($secondAdmin->hasRole(Role::Administrator->value));
        $this->assertTrue(Hash::check('Password123!', $secondAdmin->password));

        $this->post(route('admin.login.store'), [
            'email' => 'admin@villamercedes.test',
            'password' => 'Password123!',
        ])->assertRedirect(route('admin.dashboard', absolute: false));

        $this->post(route('admin.logout'))->assertRedirect();

        $this->post(route('admin.login.store'), [
            'email' => 'admin2@villamercedes.test',
            'password' => 'Password123!',
        ])->assertRedirect(route('admin.dashboard', absolute: false));

        $this->assertSame(0, Reservation::query()
            ->where('reservation_code', 'like', 'VM-HIST-%')
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Approved])
            ->where('check_out', '<=', now())
            ->count());
    }

    public function test_reservation_can_be_created_with_relationships(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $facility = Facility::query()->first();

        $reservation = Reservation::factory()->create([
            'user_id' => $user->id,
            'facility_id' => $facility->id,
            'status' => ReservationStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'user_id' => $user->id,
            'facility_id' => $facility->id,
        ]);

        $this->assertEquals($user->id, $reservation->user->id);
        $this->assertEquals($facility->id, $reservation->facility->id);
    }

    public function test_reservation_source_can_represent_online_and_walk_in(): void
    {
        $onlineReservation = Reservation::factory()->create([
            'reservation_source' => ReservationSource::Online->value,
        ]);

        $walkInReservation = Reservation::factory()->create([
            'reservation_source' => ReservationSource::WalkIn->value,
        ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $onlineReservation->id,
            'reservation_source' => 'online',
        ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $walkInReservation->id,
            'reservation_source' => 'walk_in',
        ]);
    }

    public function test_payment_can_be_created_for_reservation(): void
    {
        $reservation = Reservation::factory()->create();
        $admin = User::factory()->create();

        $payment = Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'status' => PaymentStatus::Verified->value,
            'verified_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'reservation_id' => $reservation->id,
            'verified_by' => $admin->id,
        ]);

        $this->assertEquals($reservation->id, $payment->reservation->id);
        $this->assertEquals($admin->id, $payment->verifiedBy->id);
    }

    public function test_facility_soft_deletes(): void
    {
        $facility = Facility::factory()->create();

        $facility->delete();

        $this->assertSoftDeleted('facilities', [
            'id' => $facility->id,
        ]);
    }

    public function test_foreign_key_constraints(): void
    {
        $user = User::factory()->create();
        $reservation = Reservation::factory()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/foreign key constraint fails/');

        // This should fail because user has a reservation and on delete is restrict
        $user->delete();
    }

    public function test_inquiry_relationships(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();

        $inquiry = Inquiry::factory()->create([
            'user_id' => $user->id,
            'responded_by' => $admin->id,
        ]);

        $this->assertEquals($user->id, $inquiry->user->id);
        $this->assertEquals($admin->id, $inquiry->responder->id);
    }

    public function test_faq_relationships(): void
    {
        $category = FaqCategory::factory()->create();
        $faq = Faq::factory()->create(['faq_category_id' => $category->id]);

        $this->assertEquals($category->id, $faq->category->id);
    }
}
