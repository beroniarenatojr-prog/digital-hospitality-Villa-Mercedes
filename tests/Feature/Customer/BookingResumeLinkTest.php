<?php

namespace Tests\Feature\Customer;

use App\Enums\FacilityStatus;
use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Enums\RateType;
use App\Jobs\SendBookingResumeLink;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Rate;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\Reservation\BookingResumeMail;
use App\Services\BookingResumeLink;
use App\Support\BookingDraft;
use Database\Seeders\FacilityTypeSeeder;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Finishing a booking from an inbox.
 *
 * A draft lives in one browser, so the guest who puts a booking together on a
 * phone and walks away had nothing to come back to. Reaching the confirm step now
 * also arms an emailed link back to it — but only after they have actually
 * stopped working on it, which is a question only the queue can ask (see
 * `App\Jobs\SendBookingResumeLink`), and only for the account it was emailed to.
 *
 * Most of these tests are about the ways it must *not* fire: while the guest is
 * still on the page, after they confirm, after they go back to change the dates,
 * and for anyone else holding the token.
 */
class BookingResumeLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Facility $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FacilityTypeSeeder::class);

        $this->customer = User::factory()->create([
            'name' => 'Maria Santos',
            'gender' => GuestGender::Female,
            'address' => 'Barangay Namnama, City of Ilagan, Isabela',
            'nationality' => 'Filipino',
            'valid_id_type' => GuestIdType::PhilSys,
            'phone' => '09171234567',
            'email' => 'maria.santos@example.com',
        ]);

        $this->room = $this->facility();
    }

    public function test_reaching_the_confirm_step_arms_one_link_and_schedules_it_for_later(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->confirmStep()->assertOk();

        Queue::assertPushed(SendBookingResumeLink::class, 1);
        $this->assertNotNull($this->pendingToken(), 'Reaching the confirm step armed nothing to come back to.');

        $delay = null;
        Queue::assertPushed(SendBookingResumeLink::class, function (SendBookingResumeLink $job) use (&$delay): bool {
            $delay = $job->delay;

            return true;
        });

        // The guest is still here. The email waits for them to stop.
        $this->assertNotNull($delay, 'The resume link would have been sent immediately.');
        $this->assertTrue(now()->addMinutes(19)->lessThan($delay), 'The resume link was scheduled without a delay.');
    }

    public function test_the_confirm_page_keeps_the_automatic_reminder_invisible(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->confirmStep()->assertOk();

        $this->get(route('customer.booking.create', $this->room))
            ->assertOk()
            ->assertDontSee('Not ready to confirm yet?')
            ->assertDontSee('Email me this booking now');

        Queue::assertPushed(SendBookingResumeLink::class, 1);
    }

    public function test_repricing_the_same_confirm_step_does_not_queue_duplicate_resume_jobs(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->confirmStep()->assertOk();
        $firstToken = $this->pendingToken();

        $this->confirmStep()->assertOk();

        $this->assertSame($firstToken, $this->pendingToken());
        Queue::assertPushed(SendBookingResumeLink::class, 1);
    }

    public function test_a_changed_confirm_step_removes_the_superseded_cached_payload(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->confirmStep()->assertOk();
        $firstToken = $this->pendingToken();

        $this->confirmStep(['special_requests' => 'Ground floor, please.'])->assertOk();

        $this->assertNotSame($firstToken, $this->pendingToken());
        $this->assertNull(Cache::get(BookingDraft::linkPayloadKey((string) $firstToken)));
        Queue::assertPushed(SendBookingResumeLink::class, 2);
    }

    public function test_booking_calculation_is_rate_limited_per_account(): void
    {
        config(['auth.booking_calculate.max_attempts' => 2]);
        Queue::fake();
        RateLimiter::clear('booking-calculate|'.$this->customer->id);

        $this->actingAs($this->customer);
        $this->confirmStep()->assertOk();
        $this->confirmStep()->assertOk();
        $this->confirmStep()->assertStatus(429);
    }

    public function test_a_draft_below_the_confirm_step_schedules_nothing(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->priceStep(2)->assertOk();

        Queue::assertNothingPushed();
        $this->assertNull($this->pendingToken());
    }

    public function test_going_back_to_change_the_dates_takes_the_armed_link_away(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->confirmStep()->assertOk();
        $this->assertNotNull($this->pendingToken());

        // The guest returns to step 2 and re-prices a different stay: the link that
        // was armed describes dates they are no longer booking.
        $this->priceStep(2, [
            'check_in' => now()->addDays(20)->setTime(14, 0)->format('Y-m-d H:i'),
            'check_out' => now()->addDays(22)->setTime(12, 0)->format('Y-m-d H:i'),
        ])->assertOk();

        $this->assertNull($this->pendingToken(), 'The armed link survived the guest changing their booking.');

        Notification::fake();
        $this->travel(30)->minutes();
        $this->runPendingJob();

        Notification::assertNothingSentTo($this->customer);
    }

    public function test_a_booking_finished_first_is_never_emailed_about(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->confirmStep()->assertOk();

        // Confirming hands the booking over and clears the draft, link and all.
        BookingDraft::forget($this->room);

        Notification::fake();
        $this->travel(30)->minutes();
        $this->runPendingJob();

        Notification::assertNothingSentTo($this->customer);
    }

    public function test_the_email_waits_until_the_delay_has_passed(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->confirmStep()->assertOk();

        $links = app(BookingResumeLink::class);
        $token = (string) $this->pendingToken();

        Notification::fake();

        // A queue that ignores delays (the `sync` driver) must not turn this into
        // an email that arrives while the guest is still reading the page.
        $links->deliverPending($this->customer->id, $this->room->id, $token);
        Notification::assertNothingSentTo($this->customer);

        $this->travel(1)->minutes();
        $this->runPendingJob();
        Notification::assertNothingSentTo($this->customer);

        $this->travel(30)->minutes();
        $this->runPendingJob();
        Notification::assertSentTo($this->customer, BookingResumeMail::class);
    }

    public function test_the_emailed_link_opens_the_booking_on_the_confirm_step(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->confirmStep([
            'other_guest_names' => [['name' => 'Ana Dela Cruz', 'valid_id' => GuestIdType::PhilSys->value]],
            'special_requests' => 'Ground floor please, arriving with a toddler.',
        ])->assertOk();

        Notification::fake();
        $this->travel(30)->minutes();
        $this->runPendingJob();

        $url = $this->emailedUrl();

        $this->followingRedirects()
            ->get($url)
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Picking up where you left off');

        $config = $this->wizardConfig($this->get(route('customer.booking.create', $this->room)));

        $this->assertTrue($config['resumed'], 'The link did not hand the booking back.');
        $this->assertSame(3, $config['startStep'], 'The link did not open on the confirm step.');
        $this->assertSame($this->arrival(), $config['prefill']['check_in']);
        $this->assertSame($this->departure(), $config['prefill']['check_out']);
        $this->assertSame(2, $config['prefill']['guest_count']);
        $this->assertSame('Ground floor please, arriving with a toddler.', $config['guestPrefill']['special_requests']);
        $this->assertSame(
            [['name' => 'Ana Dela Cruz', 'valid_id' => GuestIdType::PhilSys->value]],
            $config['guestPrefill']['other_guest_names']
        );
    }

    public function test_a_newer_arrival_at_the_confirm_step_overtakes_the_scheduled_email(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->confirmStep()->assertOk();
        $first = (string) $this->pendingToken();

        // Anything the guest touches on the confirm step counts as arriving again:
        // the party size, the extra hours, a preference.
        $this->confirmStep(['special_requests' => 'Late check in, around 9pm.'])->assertOk();
        $second = (string) $this->pendingToken();

        $this->assertNotSame($first, $second, 'A second check in at the confirm step reused the first link.');

        $links = app(BookingResumeLink::class);
        Notification::fake();
        $this->travel(30)->minutes();

        // The overtaken job stays silent...
        $links->deliverPending($this->customer->id, $this->room->id, $first);
        Notification::assertNothingSentTo($this->customer);

        // ...and the current one speaks, describing the booking they last priced.
        $links->deliverPending($this->customer->id, $this->room->id, $second);
        Notification::assertSentTo($this->customer, BookingResumeMail::class, fn (BookingResumeMail $mail): bool => $mail->guestCount === 2
            && $mail->facilityName === $this->room->name);
    }

    public function test_the_guest_can_ask_for_the_link_now_instead_of_waiting(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->confirmStep()->assertOk();

        Notification::fake();

        $this->post(route('customer.booking.resume.send', $this->room))
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo($this->customer, BookingResumeMail::class);

        // Asking for it cancels the automatic copy: one link, not two.
        $this->travel(30)->minutes();
        $this->runPendingJob();

        Notification::assertSentToTimes($this->customer, BookingResumeMail::class, 1);
    }

    public function test_a_mailer_outage_leaves_the_guest_with_their_booking_instead_of_an_error(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->confirmStep()->assertOk();

        $this->app->instance(Dispatcher::class, new class implements Dispatcher
        {
            public function send($notifiables, $notification)
            {
                throw new RuntimeException('The mailer is down.');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null)
            {
                throw new RuntimeException('The mailer is down.');
            }
        });

        $this->post(route('customer.booking.resume.send', $this->room))
            ->assertRedirect()
            ->assertSessionHas('error');

        // The booking itself is untouched, the wizard still opens on it, and no
        // link is left armed for an email that was never sent.
        $this->get(route('customer.booking.create', $this->room))
            ->assertOk()
            ->assertSee('Picking up where you left off');

        $this->assertNull($this->pendingToken());
    }

    public function test_a_link_is_only_ever_honoured_for_the_account_it_was_emailed_to(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->confirmStep()->assertOk();

        Notification::fake();
        $this->travel(30)->minutes();
        $this->runPendingJob();
        $url = $this->emailedUrl();

        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get($url)->assertForbidden();
    }

    public function test_a_guest_who_is_signed_out_is_asked_to_sign_in_and_then_lands_back_on_the_link(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->confirmStep()->assertOk();

        Notification::fake();
        $this->travel(30)->minutes();
        $this->runPendingJob();
        $url = $this->emailedUrl();

        // The link is opened on a device the guest is not signed in on — the whole
        // reason it is emailed. Signing in must come back to it, not to the top of
        // the flow.
        Auth::logout();
        $this->flushSession();

        $this->get($url)->assertRedirect(route('login'));
        $this->assertSame($url, session('url.intended'));
    }

    public function test_the_link_cannot_be_used_to_mail_a_guest_over_and_over(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->confirmStep()->assertOk();

        Notification::fake();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('customer.booking.resume.send', $this->room))->assertRedirect();
        }

        Notification::assertSentToTimes($this->customer, BookingResumeMail::class, 5);

        $this->post(route('customer.booking.resume.send', $this->room))->assertStatus(429);
    }

    public function test_an_expired_link_says_so_instead_of_failing(): void
    {
        $this->actingAs($this->customer)
            ->get(route('customer.booking.resume', ['token' => 'nobody-ever-sent-this']))
            ->assertRedirect(route('customer.reservations.new'))
            ->assertSessionHas('error');
    }

    public function test_a_booking_finished_elsewhere_is_shown_rather_than_offered_again(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);
        $this->confirmStep()->assertOk();
        $token = (string) $this->pendingToken();

        // The guest finished it in another tab after the link was queued.
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->room->id,
        ]);

        $this->get(route('customer.booking.resume', ['token' => $token]))
            ->assertRedirect(route('customer.reservations.show', $reservation))
            ->assertSessionHas('success', BookingResumeLink::COMPLETED_MESSAGE);
    }

    public function test_a_stay_that_has_already_started_is_not_handed_back(): void
    {
        Queue::fake();

        $this->actingAs($this->customer);

        // A same-day visit, priced tonight: the guest reads the email the next day,
        // while the link itself is still inside its 48 hours.
        $this->priceStep(BookingResumeLink::CONFIRM_STEP, [
            'check_in' => now()->addDay()->setTime(14, 0)->format('Y-m-d H:i'),
            'check_out' => now()->addDays(2)->setTime(12, 0)->format('Y-m-d H:i'),
        ])->assertOk();

        $token = (string) $this->pendingToken();

        $this->travel(26)->hours();

        $this->get(route('customer.booking.resume', ['token' => $token]))
            ->assertRedirect(route('customer.reservations.new'))
            ->assertSessionHas('error', BookingResumeLink::STALE_MESSAGE);
    }

    /**
     * One priced step, exactly as the wizard's `refreshQuote` sends it.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function priceStep(int $step, array $overrides = []): TestResponse
    {
        return $this->postJson(route('customer.booking.calculate', $this->room), array_merge([
            'check_in' => $this->arrival(),
            'check_out' => $this->departure(),
            'guestCount' => 2,
            'step' => $step,
        ], $overrides));
    }

    private function confirmStep(array $overrides = []): TestResponse
    {
        return $this->priceStep(BookingResumeLink::CONFIRM_STEP, $overrides);
    }

    /** The token of the link currently armed for this account and space. */
    private function pendingToken(): ?string
    {
        $token = Cache::get(BookingDraft::pendingLinkKey($this->customer->id, $this->room->id));

        return is_string($token) && $token !== '' ? $token : null;
    }

    /** Run the queued job the way a worker would, when the queue arrives. */
    private function runPendingJob(): void
    {
        $token = $this->pendingToken();

        if ($token === null) {
            return;
        }

        (new SendBookingResumeLink($this->customer->id, $this->room->id, $token))
            ->handle(app(BookingResumeLink::class));
    }

    /** The link the guest was last emailed, read from the notification itself. */
    private function emailedUrl(): string
    {
        $url = null;

        Notification::assertSentTo($this->customer, BookingResumeMail::class, function (BookingResumeMail $mail) use (&$url): bool {
            $url = $mail->url;

            return true;
        });

        $this->assertIsString($url, 'No resume link was emailed to the guest.');

        return $url;
    }

    private function arrival(): string
    {
        return now()->addDays(10)->setTime(14, 0)->format('Y-m-d H:i');
    }

    private function departure(): string
    {
        return now()->addDays(12)->setTime(12, 0)->format('Y-m-d H:i');
    }

    /**
     * The wizard's configuration, as the page hands it to the date pickers.
     *
     * @return array<string, mixed>
     */
    private function wizardConfig(TestResponse $response): array
    {
        $matched = preg_match(
            '/id="booking-wizard-config">(.*?)<\/script>/s',
            (string) $response->getContent(),
            $matches
        );

        $this->assertSame(1, $matched, 'The booking wizard did not render its configuration.');

        return json_decode(trim($matches[1]), true, 512, JSON_THROW_ON_ERROR);
    }

    private function facility(): Facility
    {
        $type = FacilityType::query()->where('slug', 'room')->firstOrFail();

        $facility = Facility::factory()->create([
            'facility_type_id' => $type->id,
            'is_active' => true,
            'is_listed' => true,
            'status' => FacilityStatus::Available->value,
            'capacity' => 2,
            'max_capacity' => 4,
        ]);

        Rate::factory()->create([
            'facility_id' => $facility->id,
            'type' => RateType::Base->value,
            'amount' => 5000,
            'unit' => 'per night',
            'is_active' => true,
        ]);

        return $facility;
    }
}
