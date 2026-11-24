<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\User;
use App\Support\LayoutBadges;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist
        SpatieRole::firstOrCreate(['name' => Role::Administrator->value]);
        SpatieRole::firstOrCreate(['name' => Role::Customer->value]);
    }

    protected function tearDown(): void
    {
        // The resort clock is pinned in the request-list tests; leaving it frozen
        // would move every later test's "today".
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unauthenticated_user_cannot_access_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_summary_counts_in_house_spaces_and_the_manila_verification_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-19 00:30:00', 'Asia/Manila'));
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);
        $stay = Reservation::factory()->create([
            'status' => ReservationStatus::Approved,
            'checked_in_at' => now()->subHour(),
            'checked_out_at' => null,
        ]);
        $stay->facility->update(['is_active' => true]);
        Payment::factory()->create([
            'reservation_id' => $stay->id, 'status' => PaymentStatus::Verified,
            'amount' => 123, 'payment_date' => '2026-11-17', 'verified_at' => now(),
        ]);
        Payment::factory()->create([
            'reservation_id' => $stay->id, 'status' => PaymentStatus::Verified,
            'amount' => 456, 'payment_date' => '2026-11-19', 'verified_at' => now()->subDay(),
        ]);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()->assertViewHas('totalOccupiedSpaces', 1)
            ->assertViewHas('paymentsVerifiedToday', fn ($amount) => (float) $amount === 123.0);
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $response = $this->actingAs($customer)->get(route('admin.dashboard'));
        $response->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_administrator_can_access_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.dashboard');

        // Check for layout elements
        $response->assertSee('Villa Mercedes'); // Sidebar branding
        $response->assertSee('Back office');
        $response->assertSee('Today’s operations');
        $response->assertSee('Open reservations');

        // Check for the live operational surface. With no outstanding work the
        // panel explains itself instead of showing three empty counters.
        $response->assertSee('Needs attention');
        $response->assertSee('Nothing is waiting on you');
        $response->assertSee('Next 7 days');

        // Once work exists, each outstanding item is named with its count.
        Reservation::factory()->create(['status' => ReservationStatus::Pending->value]);
        Payment::factory()->create(['status' => PaymentStatus::Pending->value]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertSee('Reservations waiting for a decision');
        $response->assertSee('Payments waiting for verification');
        $response->assertDontSee('Check ins due today');
        $response->assertDontSee('Nothing is waiting on you');

        // Check for data-driven insights and recent activity
        $response->assertDontSee('Recent Reservations');
        $response->assertDontSee('Recent Payments');
        $response->assertSee('Spaces booked today');
        $response->assertSee('Checkouts due today');
        $response->assertDontSee('Verified revenue');

        // Check for Hospitality Operational Cockpit enhancements
        $response->assertSee('Spaces in use');
        $response->assertSee('Payments verified today');
        $response->assertSee('Arrivals and departures');
        $response->assertSee('Recent activity');

        // Decorative and non-actionable dashboard treatments stay removed.
        $response->assertDontSee('Dashboard Overview');
        $response->assertDontSee('Welcome back');
        $response->assertDontSee('Total Reservations (This Month)');
        $response->assertDontSee('Verified Payments (This Month)');
        $response->assertDontSee('Total Customers');
        $response->assertDontSee('Active Facilities');
        $response->assertDontSee('bg-gradient-to-r', false);
    }

    public function test_dashboard_renders_pending_group_payment_without_a_reservation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);
        $group = ReservationGroup::factory()->create(['guest_name' => 'Group Guest']);
        Payment::factory()->create([
            'reservation_id' => null,
            'reservation_group_id' => $group->id,
            'status' => PaymentStatus::Pending->value,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Group Guest')
            ->assertSee('Group ' . $group->group_code);
    }

    public function test_the_dashboard_lists_the_requests_customers_sent_today(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        $customer = User::factory()->create(['name' => 'Ana Cruz']);
        $customer->assignRole(Role::Customer->value);

        // Yesterday morning at the resort.
        Carbon::setTestNow(Carbon::parse('2026-09-16 10:00:00', 'Asia/Manila'));
        Reservation::factory()->create([
            'guest_name' => 'Yesterday Request',
            'guest_count' => 2,
            'check_in' => '2026-11-20',
            'check_out' => '2026-11-22',
        ]);

        // Half an hour after Manila midnight, which is still yesterday in UTC.
        // It belongs to the resort's day, so it is one of today's requests.
        Carbon::setTestNow(Carbon::parse('2026-09-17 00:30:00', 'Asia/Manila'));
        $afterMidnight = Reservation::factory()->create([
            'guest_name' => 'After Midnight Request',
            'guest_count' => 2,
            'check_in' => '2026-11-20',
            'check_out' => '2026-11-22',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-17 09:00:00', 'Asia/Manila'));
        Reservation::factory()->create([
            'user_id' => $customer->id,
            'guest_name' => 'Maria Santos',
            'guest_count' => 4,
            'check_in' => '2026-11-20',
            'check_out' => '2026-11-22',
        ]);

        // A walk-in is encoded by staff at the desk, so it is not a request
        // waiting to be read.
        Reservation::factory()->create([
            'guest_name' => 'Walk In Guest',
            'guest_count' => 2,
            'status' => ReservationStatus::Approved->value,
            'reservation_source' => ReservationSource::WalkIn->value,
            'check_in' => '2026-11-20',
            'check_out' => '2026-11-22',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-17 09:30:00', 'Asia/Manila'));

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $html = $response->getContent();

        $response->assertOk();
        $response->assertSee('New requests today');

        // Today's two requests are listed, last night's and the walk-in are not.
        $response->assertSee('After Midnight Request');
        $response->assertSee('Maria Santos');
        $response->assertDontSee('Yesterday Request');
        $response->assertDontSee('Walk In Guest');

        // The request booked for someone else names the customer who sent it.
        $response->assertSee('booked by Ana Cruz');

        // Oldest first: the request that has waited longest is the one to read.
        $this->assertLessThan(
            strpos($html, 'Maria Santos'),
            strpos($html, 'After Midnight Request'),
            'The newest request is listed above an older one, so the queue hides the wait.'
        );

        // The row opens the record, where approving and rejecting happen.
        $response->assertSee('href="'.route('admin.reservations.show', $afterMidnight).'"', false);

        // Slide-over drawer wiring exists for on-screen triage
        $response->assertSee('Booking Triage');
        $response->assertSee('slide-over-title');
    }

    public function test_departures_due_has_its_own_box_and_links_to_reservation_details(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);
        Carbon::setTestNow(Carbon::parse('2026-09-17 09:00:00', 'Asia/Manila'));

        $departure = Reservation::factory()->create([
            'status' => ReservationStatus::Approved->value,
            'check_in' => '2026-09-16 14:00:00',
            'check_out' => '2026-09-17 12:00:00',
            'late_check_out_hours' => 1,
            'checked_in_at' => '2026-09-16 14:05:00',
            'checked_out_at' => null,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Arriving today')
            ->assertSee('Checkouts due today')
            ->assertSee('lg:grid-cols-2', false)
            ->assertSee('lg:col-span-2', false)
            ->assertSee('1:00 PM')
            ->assertSee('href="'.route('admin.reservations.show', $departure).'"', false);

        $this->actingAs($admin)
            ->get(route('admin.dashboard.attention'))
            ->assertDontSee('Checkouts due today');
    }

    public function test_the_dashboard_says_when_no_request_came_in_today(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('No new requests today');

        // The refresh fragment says the same thing on a quiet day: a poll that
        // emptied the list is the one case where what replaces it is words
        // rather than rows.
        $fragment = $this->actingAs($admin)->get(route('admin.dashboard.new-requests'));

        $fragment->assertOk();
        $fragment->assertSee('No new requests today');
        $fragment->assertSee('data-panel-count="0"', false);
    }

    public function test_the_request_panel_is_wired_to_refresh_itself(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        Carbon::setTestNow(Carbon::parse('2026-09-17 09:00:00', 'Asia/Manila'));
        $request = Reservation::factory()->create([
            'guest_name' => 'Refresh Wiring Guest',
            'guest_count' => 2,
            'check_in' => '2026-11-20',
            'check_out' => '2026-11-22',
        ]);

        $html = $this->actingAs($admin)->get(route('admin.dashboard'))->getContent();

        // The panel hands the browser the three things a poll needs: where to
        // look, how often, and where the answer goes.
        $this->assertStringContainsString('data-live-panel', $html);
        $this->assertStringContainsString('data-live-panel-url="'.route('admin.dashboard.new-requests').'"', $html);
        $this->assertStringContainsString('data-live-panel-interval="20000"', $html);
        $this->assertStringContainsString('data-live-panel-noun="request"', $html);
        $this->assertStringContainsString('data-live-panel-status', $html);
        $this->assertStringContainsString('data-panel-badge', $html);

        // Every row can be told apart, and every row ships its "New" marker
        // hidden: nothing is new to somebody who has only just arrived.
        $this->assertStringContainsString('data-live-row="'.$request->id.'"', $html);
        $this->assertSame(
            substr_count($html, 'data-live-row='),
            substr_count($html, 'data-live-row-new class="hidden'),
            'Every row needs a marker, and every marker has to ship hidden: a request that was already waiting when the screen opened is not new to the person reading it.'
        );
    }

    public function test_the_refresh_fragment_is_the_request_list_and_nothing_else(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        Carbon::setTestNow(Carbon::parse('2026-09-17 09:00:00', 'Asia/Manila'));

        $customer = User::factory()->create(['name' => 'Ana Cruz']);
        $customer->assignRole(Role::Customer->value);

        Reservation::factory()->create([
            'user_id' => $customer->id,
            'guest_name' => 'Maria Santos',
            'guest_count' => 4,
            'check_in' => '2026-11-20',
            'check_out' => '2026-11-22',
        ]);

        // A walk-in is encoded at the desk, so it is not a request waiting to
        // be read — the same rule the panel itself follows.
        Reservation::factory()->create([
            'guest_name' => 'Walk In Guest',
            'guest_count' => 2,
            'status' => ReservationStatus::Approved->value,
            'reservation_source' => ReservationSource::WalkIn->value,
            'check_in' => '2026-11-20',
            'check_out' => '2026-11-22',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard.new-requests'));
        $html = $response->getContent();

        $response->assertOk();
        $response->assertSee('Maria Santos');
        $response->assertSee('booked by Ana Cruz');
        $response->assertDontSee('Walk In Guest');

        // Nothing a poll replaces, and nothing it does not: the answer is the
        // list, its count, and the fingerprint a poll compares.
        $response->assertSee('data-live-panel-body', false);
        $response->assertSee('data-panel-count="1"', false);
        $response->assertSee('data-panel-signature', false);

        // Not the page it was cut out of, so a poll can never draw the back
        // office twice into one panel.
        $response->assertDontSee('admin-sidebar', false);
        $response->assertDontSee('Needs attention');
        $response->assertDontSee('Today’s operations');

        // A refresh nobody may serve from memory: a remembered list is a
        // request nobody sees.
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_the_refresh_fragment_shows_a_request_that_arrived_after_the_page_was_opened(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        Carbon::setTestNow(Carbon::parse('2026-09-17 09:00:00', 'Asia/Manila'));

        $opened = $this->actingAs($admin)->get(route('admin.dashboard'))->getContent();

        $this->assertStringNotContainsString('Late Shift Request', $opened);

        // The booking a customer sends while the dashboard sits open at the desk.
        Carbon::setTestNow(Carbon::parse('2026-09-17 09:41:00', 'Asia/Manila'));
        $late = Reservation::factory()->create([
            'guest_name' => 'Late Shift Request',
            'guest_count' => 2,
            'check_in' => '2026-11-20',
            'check_out' => '2026-11-22',
        ]);

        $fragment = $this->actingAs($admin)->get(route('admin.dashboard.new-requests'))->getContent();

        $this->assertStringContainsString('Late Shift Request', $fragment);
        $this->assertStringContainsString(route('admin.reservations.show', $late), $fragment);

        // The fingerprint moved, which is what tells the browser to draw the new
        // list instead of leaving the one it already has.
        $this->assertNotSame($this->signatureIn($opened), $this->signatureIn($fragment));
    }

    public function test_a_quiet_poll_is_told_apart_from_one_that_changed_something(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        Carbon::setTestNow(Carbon::parse('2026-09-17 09:00:00', 'Asia/Manila'));

        $request = Reservation::factory()->create([
            'guest_name' => 'Held Request',
            'guest_count' => 2,
            'status' => ReservationStatus::Pending->value,
            'check_in' => '2026-11-20',
            'check_out' => '2026-11-22',
        ]);

        $first = $this->actingAs($admin)->get(route('admin.dashboard.new-requests'))->getContent();
        $second = $this->actingAs($admin)->get(route('admin.dashboard.new-requests'))->getContent();

        $this->assertSame(
            $this->signatureIn($first),
            $this->signatureIn($second),
            'A poll that found nothing new has to look like the poll before it, or the list is rebuilt under the reader for no reason.'
        );

        // Another staff member approves the request while this screen is open.
        Carbon::setTestNow(Carbon::parse('2026-09-17 10:00:00', 'Asia/Manila'));
        $request->forceFill(['status' => ReservationStatus::Approved->value])->save();

        $third = $this->actingAs($admin)->get(route('admin.dashboard.new-requests'))->getContent();

        $this->assertNotSame(
            $this->signatureIn($second),
            $this->signatureIn($third),
            'A request that changed must not be left on screen saying what it used to say.'
        );
    }

    public function test_the_refresh_fragments_are_not_served_to_anyone_but_staff(): void
    {
        $this->get(route('admin.dashboard.new-requests'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.dashboard.attention'))->assertRedirect(route('admin.login'));

        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $this->actingAs($customer)
            ->get(route('admin.dashboard.new-requests'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($customer)
            ->get(route('admin.dashboard.attention'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_the_attention_counts_are_wired_to_refresh_themselves(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        Carbon::setTestNow(Carbon::parse('2026-09-17 09:00:00', 'Asia/Manila'));

        // The pending count is the layout's short-lived cache, and the cache
        // outlives a test that rolled its own reservations back, so this shift
        // is started cold rather than from whatever the last test left.
        LayoutBadges::forgetPendingReservations();

        $html = $this->actingAs($admin)->get(route('admin.dashboard'))->getContent();

        $this->assertStringContainsString('data-live-panel-url="'.route('admin.dashboard.attention').'"', $html);
        $this->assertStringContainsString('data-live-panel-interval="20000"', $html);
        $this->assertStringContainsString('data-panel-summary', $html);

        $fragment = $this->actingAs($admin)->get(route('admin.dashboard.attention'));
        $fragment->assertOk();

        // The counts and nothing around them: a poll can never draw the back
        // office a second time into one panel.
        $fragment->assertSee('data-live-panel-body', false);
        $fragment->assertSee('data-panel-signature', false);
        $fragment->assertDontSee('admin-sidebar', false);
        $fragment->assertDontSee('Today’s operations');

        // A shift with nothing waiting says so, in the same words the page uses.
        $fragment->assertSee('Nothing is waiting on you');

        $this->assertStringContainsString('no-store', $fragment->headers->get('Cache-Control'));

        // One phrase, written twice: beside the panel title, and in the answer
        // that replaces it. A difference here is what a staff member would see
        // as a total that stopped agreeing with its own counts.
        preg_match('/data-panel-summary[^>]*>(.*?)<\/span>/s', $html, $header);
        preg_match('/data-panel-summary="([^"]*)"/', $fragment->getContent(), $answer);

        $this->assertSame(
            trim($header[1] ?? ''),
            $answer[1] ?? '',
            'The total beside the panel title and the total a poll copies in are written in two files, and they disagree.'
        );
    }

    public function test_the_attention_fragment_counts_work_that_arrives_mid_shift(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        Carbon::setTestNow(Carbon::parse('2026-09-17 09:00:00', 'Asia/Manila'));

        $before = $this->actingAs($admin)->get(route('admin.dashboard'))->getContent();

        // A request arrives and a proof is filed against it while the dashboard
        // sits open: two attention counts move.
        Carbon::setTestNow(Carbon::parse('2026-09-17 09:25:00', 'Asia/Manila'));
        $request = Reservation::factory()->create([
            'guest_name' => 'Mid Shift Request',
            'guest_count' => 2,
            'status' => ReservationStatus::Pending->value,
            'reservation_source' => ReservationSource::Online->value,
            'check_in' => '2026-11-20',
            'check_out' => '2026-11-22',
        ]);
        Payment::factory()->create([
            'reservation_id' => $request->id,
            'status' => PaymentStatus::Pending->value,
            'payment_date' => now()->toDateString(),
        ]);

        $fragment = $this->actingAs($admin)->get(route('admin.dashboard.attention'))->getContent();

        $this->assertNotSame(
            $this->signatureIn($before),
            $this->signatureIn($fragment),
            'Two counts moved while the screen was open, and the panel would not have noticed.'
        );

        // The rows are back, in the words the page uses for them.
        $this->assertStringContainsString('Reservations waiting for a decision', $fragment);
        $this->assertStringContainsString('Payments waiting for verification', $fragment);
        $this->assertStringNotContainsString('Check ins due today', $fragment);
        $this->assertStringNotContainsString('Checkouts due today', $fragment);
        $this->assertStringNotContainsString('Nothing is waiting on you', $fragment);

        // The header number is the rows it stands over, never a poll behind them.
        $this->assertSame(
            array_sum($this->countsIn($fragment)),
            $this->statedTotalIn($fragment),
            'The total beside the panel title does not add up to the counts under it.'
        );
    }

    public function test_a_quiet_attention_poll_is_told_apart_from_one_that_moved(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        Carbon::setTestNow(Carbon::parse('2026-09-17 09:00:00', 'Asia/Manila'));

        $request = Reservation::factory()->create([
            'guest_name' => 'Waiting On Approval',
            'guest_count' => 2,
            'status' => ReservationStatus::Pending->value,
            'check_in' => '2026-11-20',
            'check_out' => '2026-11-22',
        ]);

        $first = $this->actingAs($admin)->get(route('admin.dashboard.attention'))->getContent();
        $second = $this->actingAs($admin)->get(route('admin.dashboard.attention'))->getContent();

        $this->assertSame(
            $this->signatureIn($first),
            $this->signatureIn($second),
            'A poll that found the same work waiting has to look like the poll before it, or the counts are redrawn under the reader for no reason.'
        );

        // The staff member beside them approves it from the decision board.
        $request->forceFill(['status' => ReservationStatus::Approved->value])->save();

        $third = $this->actingAs($admin)->get(route('admin.dashboard.attention'))->getContent();

        $this->assertNotSame(
            $this->signatureIn($second),
            $this->signatureIn($third),
            'A count that moved must not be left on screen saying what it used to say.'
        );

        // Nothing is waiting any more, so the panel says so rather than showing
        // rows of noughts — and the total a poll hands the header agrees.
        $this->assertStringContainsString('Nothing is waiting on you', $third);
        $this->assertSame(0, $this->statedTotalIn($third));
    }

    /**
     * The three attention counts as the panel draws them.
     *
     * @return list<int>
     */
    private function countsIn(string $html): array
    {
        preg_match_all('/<span class="flex h-10 w-10[^"]*"[^>]*>\s*(\d+)\s*<\/span>/', $html, $matches);

        $this->assertNotContains('0', $matches[1], 'Zero counts should not occupy an attention row.');

        return array_map(intval(...), $matches[1]);
    }

    /**
     * The total the fragment hands to the panel header, as a number.
     */
    private function statedTotalIn(string $html): int
    {
        preg_match('/data-panel-summary="(\d+) items?"/', $html, $matches);

        $this->assertArrayHasKey(
            1,
            $matches,
            'The attention fragment states no total, so a poll could never move the one beside the panel title.'
        );

        return (int) $matches[1];
    }

    /**
     * The fingerprint the panel carries, which is what a poll compares.
     */
    private function signatureIn(string $html): string
    {
        preg_match('/data-panel-signature="([^"]*)"/', $html, $matches);

        $this->assertNotSame('', $matches[1] ?? '', 'The request panel carries no fingerprint, so a poll cannot tell a change from a quiet answer.');

        return $matches[1];
    }

    public function test_shared_table_shell_has_one_vocabulary(): void
    {
        $table = Blade::render('<x-admin.table />');
        $compactBadge = Blade::render('<x-badge status="pending" variant="compact" />');
        $defaultBadge = Blade::render('<x-badge status="pending" />');

        // One shell for every list screen: a single border and scroll container,
        // warm tokens, and none of the per-page bleed margins or shadows.
        $this->assertStringContainsString('rounded-xl border border-border bg-surface', $table);
        $this->assertStringContainsString('overflow-x-auto', $table);
        $this->assertStringNotContainsString('shadow', $table);
        $this->assertStringNotContainsString('lg:-mx-8', $table);
        $this->assertStringNotContainsString('variant', $table);
        $this->assertStringContainsString('rounded-sm border border-current', $compactBadge);
        $this->assertStringContainsString('rounded-full', $defaultBadge);
    }

    public function test_admin_shell_keeps_sidebar_in_document_flow(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        $html = $this->actingAs($admin)->get(route('admin.dashboard'))->getContent();

        $this->assertStringContainsString('class="admin-shell"', $html);
        $this->assertStringContainsString('class="admin-sidebar"', $html);
        $this->assertStringContainsString('class="admin-main"', $html);
        $this->assertDoesNotMatchRegularExpression('/<aside[^>]*\bfixed\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/<aside[^>]*\babsolute\b/', $html);
    }
}
