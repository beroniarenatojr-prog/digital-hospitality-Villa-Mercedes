<?php

namespace Tests\Feature\Admin;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Exceptions\Admin\ImmutableActivityLogException;
use App\Models\ActivityLog;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $secondAdmin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::factory()->create(['name' => 'Ana Front Desk']);
        $this->admin->assignRole(Role::Administrator->value);

        $this->secondAdmin = User::factory()->create(['name' => 'Ben Night Shift']);
        $this->secondAdmin->assignRole(Role::Administrator->value);

        $this->customer = User::factory()->create(['name' => 'Guest Caller']);
        $this->customer->assignRole(Role::Customer->value);
    }

    public function test_unauthenticated_user_cannot_view_activity_log(): void
    {
        $this->get(route('admin.activity-log.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_customer_cannot_view_activity_log(): void
    {
        $this->actingAs($this->customer)
            ->get(route('admin.activity-log.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_administrator_can_view_activity_log_section(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.activity-log.index'))
            ->assertOk()
            ->assertSee('Staff activity')
            ->assertSee('Staff member')
            ->assertSee('Action type')
            ->assertSee('cannot be edited or deleted');
    }

    public function test_staff_actions_are_listed_with_name_id_timestamp_and_description(): void
    {
        $reservation = Reservation::factory()->create();

        activity()
            ->causedBy($this->admin)
            ->performedOn($reservation)
            ->event('facility_created')
            ->log("Facility 'Garden Villa' was created.");

        $this->actingAs($this->admin)
            ->get(route('admin.activity-log.index'))
            ->assertOk()
            ->assertSee('Ana Front Desk')
            ->assertSee('Staff #'.$this->admin->id)
            ->assertSee("Facility 'Garden Villa' was created.")
            ->assertSee($reservation->reservation_code)
            ->assertSee(now()->format('M d, Y'));
    }

    public function test_guest_actions_are_not_listed_as_staff_activity(): void
    {
        activity()
            ->causedBy($this->customer)
            ->event('created')
            ->log('Customer submitted a new inquiry.');

        $this->actingAs($this->admin)
            ->get(route('admin.activity-log.index'))
            ->assertOk()
            ->assertDontSee('Customer submitted a new inquiry.')
            ->assertDontSee('Guest Caller');
    }

    public function test_activity_log_can_be_filtered_by_staff_member(): void
    {
        activity()->causedBy($this->admin)->event('facility_created')->log('Ana created a facility.');
        activity()->causedBy($this->secondAdmin)->event('facility_updated')->log('Ben updated a facility.');

        $this->actingAs($this->admin)
            ->get(route('admin.activity-log.index', ['staff_id' => $this->admin->id]))
            ->assertOk()
            ->assertSee('Ana created a facility.')
            ->assertDontSee('Ben updated a facility.');
    }

    public function test_activity_log_can_be_filtered_by_action_type(): void
    {
        activity()->causedBy($this->admin)->event('facility_created')->log('Created a garden villa.');
        activity()->causedBy($this->admin)->event('facility_deleted')->log('Deleted an unused cottage.');

        $this->actingAs($this->admin)
            ->get(route('admin.activity-log.index', ['action_type' => 'create']))
            ->assertOk()
            ->assertSee('Created a garden villa.')
            ->assertDontSee('Deleted an unused cottage.');
    }

    public function test_activity_log_can_be_filtered_by_date_range(): void
    {
        $this->travelTo(now()->subDays(8));
        activity()->causedBy($this->admin)->event('facility_created')->log('Older staff action.');
        $this->travelBack();

        activity()->causedBy($this->admin)->event('facility_updated')->log('Recent staff action.');

        $this->actingAs($this->admin)
            ->get(route('admin.activity-log.index', [
                'date_from' => now()->subDay()->toDateString(),
                'date_until' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Recent staff action.')
            ->assertDontSee('Older staff action.');
    }

    public function test_reservation_approval_is_attributed_to_the_acting_staff_member(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Pending->value,
            'facility_id' => Facility::whereHas('facilityType', fn ($q) => $q->where('slug', 'room'))->firstOrFail()->id,
            'check_in' => now()->addDays(10)->setTime(14, 0),
            'check_out' => now()->addDays(11)->setTime(12, 0),
            'guest_count' => 1,
            'total_price' => 1000,
            'base_price' => 1000,
            'deposit_required' => 500,
            'policy_version' => config('resort.policy.confirmed_version'),
        ]);
        Payment::factory()->create(['reservation_id' => $reservation->id, 'amount' => 500, 'status' => 'verified']);

        $this->actingAs($this->admin)
            ->patch(route('admin.reservations.approve', $reservation))
            ->assertRedirect()->assertSessionMissing('error');

        $this->actingAs($this->admin)
            ->get(route('admin.activity-log.index', ['action_type' => 'approve']))
            ->assertOk()
            ->assertSee('Ana Front Desk')
            ->assertSee('Staff #'.$this->admin->id)
            ->assertSee('to Confirmed');
    }

    public function test_a_staff_sign_in_and_sign_out_are_recorded_and_listed(): void
    {
        $this->post(route('admin.login.store'), [
            'email' => $this->admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('activity_log', [
            'event' => 'staff_signed_in',
            'causer_type' => (new User)->getMorphClass(),
            'causer_id' => $this->admin->id,
        ]);

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('activity_log', [
            'event' => 'staff_signed_out',
            'causer_id' => $this->admin->id,
        ]);

        // The shift has two ends, and both read as words on the log screen
        // rather than as the stored event names.
        $this->assertSame('Sign in', $this->entry('staff_signed_in')->actionLabel());
        $this->assertSame('Sign out', $this->entry('staff_signed_out')->actionLabel());

        $this->actingAs($this->secondAdmin)
            ->get(route('admin.activity-log.index'))
            ->assertOk()
            ->assertSee('Ana Front Desk')
            ->assertSee('Signed in to the back office.')
            ->assertSee('Signed out of the back office.');
    }

    public function test_a_sign_out_after_the_page_had_expired_is_still_recorded(): void
    {
        // The page was rendered under a session that has since died, so the POST
        // arrives with a token that matches nothing and nobody to attribute it
        // to. The shift still ended, and the desk hands over at a fixed time.
        $this->withCsrfEnforced();

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest();

        $entry = $this->entry('staff_signed_out');

        $this->assertNull($entry->causer_id);
        $this->assertSame('session_expired', $entry->properties->get('reason'));

        $this->actingAs($this->admin)
            ->get(route('admin.activity-log.index'))
            ->assertOk()
            // Unattributed, and still on the page rather than hidden behind the
            // staff filter — a record nobody can read is not a record.
            ->assertSee('Signed out of the back office after the page had expired.')
            ->assertSee('System');
    }

    public function test_sign_ins_are_listed_under_their_own_action_type(): void
    {
        activity()->causedBy($this->admin)->event('staff_signed_in')->log('Signed in to the back office.');
        activity()->causedBy($this->admin)->event('staff_signed_out')->log('Signed out of the back office.');

        $this->actingAs($this->admin)
            ->get(route('admin.activity-log.index', ['action_type' => 'sign_in']))
            ->assertOk()
            ->assertSee('Signed in to the back office.')
            ->assertDontSee('Signed out of the back office.');
    }

    public function test_guest_sessions_are_not_recorded_as_staff_shifts(): void
    {
        $this->post('/login', [
            'email' => $this->customer->email,
            'password' => 'password',
        ]);

        $this->actingAs($this->customer)->post('/logout')->assertRedirect('/');

        // A guest signing in or out is not a staff shift, and the log is the
        // place where that distinction has to hold.
        $this->assertDatabaseMissing('activity_log', ['event' => 'staff_signed_in']);
        $this->assertDatabaseMissing('activity_log', ['event' => 'staff_signed_out']);
    }

    public function test_activity_log_entries_cannot_be_updated(): void
    {
        activity()->causedBy($this->admin)->event('created')->log('Original description.');

        $log = ActivityLog::query()->firstOrFail();

        $this->expectException(ImmutableActivityLogException::class);

        $log->description = 'Tampered description';
        $log->save();
    }

    public function test_activity_log_entries_cannot_be_deleted_by_application_code(): void
    {
        activity()->causedBy($this->admin)->event('created')->log('Keep this record.');

        $log = ActivityLog::query()->firstOrFail();

        $this->expectException(ImmutableActivityLogException::class);

        $log->delete();
    }

    public function test_staff_cannot_alter_activity_log_over_http(): void
    {
        activity()->causedBy($this->admin)->event('created')->log('Protected description.');

        $log = ActivityLog::query()->where('description', 'Protected description.')->firstOrFail();

        $this->actingAs($this->admin)
            ->put('/admin/activity-log/'.$log->id, ['description' => 'Changed'])
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->delete('/admin/activity-log/'.$log->id)
            ->assertNotFound();

        $this->assertSame('Protected description.', $log->fresh()->description);
        $this->assertTrue(ActivityLog::query()->whereKey($log->id)->exists());
    }

    private function entry(string $event): ActivityLog
    {
        return ActivityLog::query()->where('event', $event)->latest('id')->firstOrFail();
    }

    /**
     * Every request in the suite normally skips the CSRF check, so a page that
     * had already expired can only be produced with the app reporting a
     * non-testing environment.
     */
    private function withCsrfEnforced(): void
    {
        $this->app['env'] = 'local';
    }
}
