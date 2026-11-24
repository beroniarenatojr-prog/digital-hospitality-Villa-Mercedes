<?php

namespace Tests\Feature;

use App\Enums\FacilityStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Exceptions\Reservation\ReservationConflictException;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\PaymentDestination;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Carbon\Carbon;
use Database\Seeders\FacilityTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A pending reservation holds its space until the deposit deadline, so the only
 * thing standing between one account and every cottage on the same dates is how
 * many unapproved reservations that account may hold at once.
 */
class CustomerReservationHoldLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $admin;

    private Facility $room;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payments.deposit_workflow' => true,
            'payments.allow_gateway_checkout' => false,
            'resort.policy.max_pending_holds' => 5,
            'resort.phone' => '+63 917 000 0000',
        ]);
        Notification::fake();
        Http::preventStrayRequests();
        Storage::fake('uploads');

        $this->seed([RoleSeeder::class, FacilityTypeSeeder::class]);

        $this->customer = User::factory()->create();
        $this->customer->assignRole(Role::Customer->value);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->room = Facility::factory()->create([
            'facility_type_id' => FacilityType::where('slug', 'room')->firstOrFail()->id,
            'status' => FacilityStatus::Available,
            'capacity' => 4,
            'max_capacity' => 6,
            'is_active' => true,
        ]);
        $this->room->rates()->create([
            'name' => 'Test rate', 'type' => 'base', 'amount' => 3000.01, 'unit' => 'per night', 'is_active' => true,
        ]);

        // An online booking without a destination to pay would be refused for a
        // different reason, and this file is about the ceiling.
        $destination = PaymentDestination::create([
            'method' => 'gcash', 'account_name' => 'Test resort account',
            'account_number' => 'TEST-ONLY', 'qr_path' => 'test.png', 'is_active' => true,
        ]);
        Storage::disk('uploads')->put((string) $destination->qr_path, 'test-only');
    }

    /** A self-service booking, submitted the way the guest's form submits one. */
    private function book(?User $actor = null, ReservationSource $source = ReservationSource::Online): Reservation
    {
        return app(ReservationService::class)->createReservation(
            $this->room,
            $actor ?? $this->customer,
            Carbon::parse('2027-02-01 14:00:00'),
            Carbon::parse('2027-02-03 12:00:00'),
            $source,
            2
        );
    }

    /** Reservations the guest is sitting on while they wait for approval. */
    private function holds(int $count, User|int|null $user = null, ReservationStatus $status = ReservationStatus::Pending): void
    {
        $userId = $user instanceof User ? $user->id : ($user ?? $this->customer->id);

        Reservation::factory()->count($count)->create([
            'user_id' => $userId,
            'status' => $status->value,
        ]);
    }

    public function test_the_ceiling_refuses_the_next_online_booking(): void
    {
        config(['resort.policy.max_pending_holds' => 2]);
        $this->holds(2);

        try {
            $this->book();
            $this->fail('A third reservation should have been refused at the ceiling.');
        } catch (ReservationConflictException $e) {
            $this->assertStringContainsString('waiting for approval', $e->getMessage());
            $this->assertStringContainsString('2', $e->getMessage());
            // The refusal has to leave the guest somewhere to go: bigger groups
            // are booked by the desk.
            $this->assertStringContainsString(config('resort.phone'), $e->getMessage());
        }

        $this->assertSame(2, Reservation::where('user_id', $this->customer->id)->count());
    }

    public function test_the_ceiling_counts_only_reservations_awaiting_approval(): void
    {
        config(['resort.policy.max_pending_holds' => 1]);
        $this->holds(1, status: ReservationStatus::Approved);
        $this->holds(1, status: ReservationStatus::Completed);
        $this->holds(1, status: ReservationStatus::Cancelled);
        $this->holds(1, status: ReservationStatus::Rejected);
        $this->holds(1, status: ReservationStatus::Expired);
        $this->holds(1, status: ReservationStatus::Rescheduled);

        // A booking the guest has already been given, finished, or lost does not
        // consume the hold they are entitled to.
        $this->assertSame(ReservationStatus::Pending, $this->book()->status);
    }

    public function test_another_guests_holds_are_not_the_guests_ceiling(): void
    {
        config(['resort.policy.max_pending_holds' => 1]);

        $stranger = User::factory()->create();
        $stranger->assignRole(Role::Customer->value);
        $this->holds(4, $stranger);

        $this->assertSame(ReservationStatus::Pending, $this->book()->status);
    }

    /**
     * The ceiling is a self-service guard, not a booking ban: the desk books
     * groups, and a walk-in never waits for approval in the first place.
     */
    public function test_the_desk_can_still_book_for_a_guest_at_the_ceiling(): void
    {
        config(['resort.policy.max_pending_holds' => 1]);
        $this->holds(3);

        $walkIn = $this->book($this->admin, ReservationSource::WalkIn);

        $this->assertSame(ReservationStatus::Approved, $walkIn->status);
        $this->assertSame($this->admin->id, $walkIn->user_id);
    }

    public function test_an_operator_can_remove_the_ceiling(): void
    {
        config(['resort.policy.max_pending_holds' => 0]);
        $this->holds(8);

        $this->assertSame(ReservationStatus::Pending, $this->book()->status);
    }
}
