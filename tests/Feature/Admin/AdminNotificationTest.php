<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $otherAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        SpatieRole::firstOrCreate(['name' => Role::Administrator->value]);
        SpatieRole::firstOrCreate(['name' => Role::Customer->value]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->otherAdmin = User::factory()->create();
        $this->otherAdmin->assignRole(Role::Administrator->value);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createNotification(User $user, array $data = []): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\Reservation\ReservationCreatedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => array_merge(['message' => 'New reservation VM-TEST created.'], $data),
            'read_at' => null,
        ]);
    }

    public function test_administrator_can_view_notifications_page(): void
    {
        $this->createNotification($this->admin);

        $response = $this->actingAs($this->admin)->get(route('admin.notifications.index'));

        $response->assertOk();
        $response->assertSee('New reservation VM-TEST created.');
    }

    public function test_administrator_sees_unread_count_and_message_in_header(): void
    {
        $first = $this->createNotification($this->admin, ['message' => 'Guest requested withdrawal of VM-1001.']);
        $this->createNotification($this->admin, ['message' => 'New reservation VM-1002 created.']);
        $this->createNotification($this->admin, ['message' => 'New reservation VM-1003 created.']);
        $this->createNotification($this->admin, ['message' => 'A new payment was submitted for reservation VM-1004.']);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Guest requested withdrawal of VM-1001.');
        $response->assertSee(route('admin.notifications.open', $first->id), false);
        $response->assertSee('View all notifications');
    }

    public function test_opening_a_notification_marks_it_read_and_opens_the_reservation(): void
    {
        $reservation = Reservation::factory()->create();
        $notification = $this->createNotification($this->admin, [
            'reservation_id' => $reservation->id,
            'message' => "New reservation {$reservation->reservation_code} created.",
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.notifications.open', $notification->id));

        $response->assertRedirect(route('admin.reservations.show', $reservation));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_opening_a_payment_notification_opens_the_payment(): void
    {
        $payment = Payment::factory()->create();
        $notification = $this->createNotification($this->admin, [
            'payment_id' => $payment->id,
            'reservation_id' => $payment->reservation_id,
            'message' => 'A new payment was submitted.',
            'type' => 'App\Notifications\Payment\PaymentSubmittedNotification',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.notifications.open', $notification->id));

        $response->assertRedirect(route('admin.payments.show', $payment));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_administrator_cannot_open_another_admins_notification(): void
    {
        $notification = $this->createNotification($this->otherAdmin);

        $response = $this->actingAs($this->admin)->get(route('admin.notifications.open', $notification->id));

        $response->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_clears_only_the_authenticated_admin(): void
    {
        $this->createNotification($this->admin);
        $this->createNotification($this->admin);
        $otherNotification = $this->createNotification($this->otherAdmin);

        $response = $this->actingAs($this->admin)->post(route('admin.notifications.mark-all-as-read'));

        $response->assertRedirect();
        $this->assertSame(0, $this->admin->unreadNotifications()->count());
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_customer_cannot_access_admin_notifications(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $this->actingAs($customer)
            ->get(route('admin.notifications.index'))
            ->assertRedirect(route('admin.login'));
    }
}
