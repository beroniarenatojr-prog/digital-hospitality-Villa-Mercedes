<?php

namespace Tests\Feature\Customer;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;
use Illuminate\Support\Str;

class CustomerNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $otherCustomer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->customer = User::factory()->create();
        $this->otherCustomer = User::factory()->create();
    }

    private function createNotification(User $user, array $data = [])
    {
        return DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\Reservation\ReservationCreatedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => array_merge(['message' => 'Test Notification Message'], $data),
            'read_at' => null,
        ]);
    }

    public function test_customer_can_view_own_notifications()
    {
        $this->createNotification($this->customer);

        $response = $this->actingAs($this->customer)->get(route('customer.notifications.index'));

        $response->assertStatus(200);
        $response->assertSee('Test Notification Message');
    }

    public function test_customer_cannot_access_another_users_notification()
    {
        $notification = $this->createNotification($this->otherCustomer);

        $response = $this->actingAs($this->customer)->post(route('customer.notifications.mark-as-read', $notification->id));

        $response->assertStatus(404); // Using findOrFail scoped to user's notifications, so it throws 404
    }

    public function test_unread_count_is_correct()
    {
        $this->createNotification($this->customer);
        $this->createNotification($this->customer);

        $response = $this->actingAs($this->customer)->get(route('customer.notifications.index'));

        $response->assertStatus(200);
        $this->assertEquals(2, $this->customer->unreadNotifications()->count());
        $response->assertSee('bg-danger', false)
            ->assertSee('shadow-lg ring-2 ring-white', false);
    }

    public function test_single_notification_can_be_marked_read()
    {
        $notification = $this->createNotification($this->customer);

        $response = $this->actingAs($this->customer)->post(route('customer.notifications.mark-as-read', $notification->id));

        $response->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_affects_only_authenticated_customer()
    {
        $this->createNotification($this->customer);
        $otherNotification = $this->createNotification($this->otherCustomer);

        $response = $this->actingAs($this->customer)->post(route('customer.notifications.mark-all-as-read'));

        $response->assertRedirect();
        
        $this->assertEquals(0, $this->customer->unreadNotifications()->count());
        $this->assertNull($otherNotification->fresh()->read_at); // Other user's notification remains unread
    }

    public function test_notification_contents_cannot_be_modified_by_customer()
    {
        // There are no routes to update/PUT notification data, so it's naturally impossible.
        // We ensure that the mark-as-read route doesn't accept content data.
        $notification = $this->createNotification($this->customer);

        $this->actingAs($this->customer)->post(route('customer.notifications.mark-as-read', $notification->id), [
            'data' => ['message' => 'Hacked message'],
        ]);

        $this->assertEquals('Test Notification Message', $notification->fresh()->data['message']);
    }
}
