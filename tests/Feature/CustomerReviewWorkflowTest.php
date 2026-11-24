<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\{GuestReview, Reservation, User};
use App\Notifications\Reservation\{ReviewInvitation, ReviewInvitationMail};
use App\Services\{ReservationService, ReviewInvitationService};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Notification};
use Tests\TestCase;

class CustomerReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $admin;
    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.deposit_workflow' => true, 'mail.default' => 'smtp']);
        Notification::fake();
        Http::preventStrayRequests();
        $this->seed(RoleSeeder::class);
        $this->customer = User::factory()->create(['name' => 'Maria Santos']);
        $this->customer->assignRole('Customer');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Administrator');
        $this->reservation = Reservation::factory()->create(['user_id' => $this->customer->id, 'status' => ReservationStatus::Approved,
            'checked_in_at' => now()->subDay(), 'checked_out_at' => null]);
    }

    private function finishStay(): void
    {
        app(ReservationService::class)->checkOut($this->reservation, $this->admin);
    }

    public function test_checkout_creates_one_invitation_per_channel_even_on_repeated_requests(): void
    {
        $this->finishStay();
        app(ReviewInvitationService::class)->invite($this->reservation);
        app(ReviewInvitationService::class)->invite($this->reservation);
        $this->assertNotNull($this->reservation->fresh()->review_invited_at);
        Notification::assertSentToTimes($this->customer, ReviewInvitation::class, 1);
        Notification::assertSentToTimes($this->customer, ReviewInvitationMail::class, 1);
        Notification::assertSentTo($this->customer, ReviewInvitation::class, fn ($n) => $n->channel === 'database');
        $this->actingAs($this->customer)->get(route('customer.reservations.show', $this->reservation))->assertOk()->assertSee('Rate your experience');
    }

    public function test_unfinished_cancelled_and_unowned_stays_cannot_be_reviewed(): void
    {
        $this->actingAs($this->customer);
        foreach ([ReservationStatus::Pending, ReservationStatus::Approved, ReservationStatus::Cancelled, ReservationStatus::Completed] as $status) {
            $this->reservation->update(['status' => $status, 'checked_out_at' => null]);
            $this->post(route('customer.reviews.store', $this->reservation), ['rating' => 5, 'public_consent' => 1])->assertForbidden();
        }
        $other = User::factory()->create();
        $this->reservation->update(['status' => ReservationStatus::Completed, 'checked_out_at' => now(), 'user_id' => $other->id]);
        $this->post(route('customer.reviews.store', $this->reservation), ['rating' => 5, 'public_consent' => 1])->assertForbidden();
        $this->assertDatabaseCount('guest_reviews', 0);
    }

    public function test_mail_is_not_invited_when_outbound_delivery_is_unconfigured(): void
    {
        config(['mail.default' => 'array']);
        $this->finishStay();
        Notification::assertSentToTimes($this->customer, ReviewInvitation::class, 1);
        Notification::assertNotSentTo($this->customer, ReviewInvitationMail::class);
    }

    public function test_review_requires_valid_rating_and_explicit_visibility_but_not_comment(): void
    {
        $this->finishStay();
        $this->actingAs($this->customer)->post(route('customer.reviews.store', $this->reservation), ['rating' => 6])->assertSessionHasErrors(['rating', 'public_consent']);
        $this->post(route('customer.reviews.store', $this->reservation), ['rating' => 1, 'public_consent' => 0])->assertSessionHas('success');
        $review = GuestReview::firstOrFail();
        $this->assertNull($review->body);
        $this->assertFalse($review->is_published);
        $this->assertFalse($review->public_consent);
        $this->actingAs($this->admin)->patch(route('admin.reviews.update', $review), ['action' => 'publish'])->assertRedirect()->assertSessionHasErrors('action');
        $this->assertFalse($review->fresh()->is_published);
    }

    public function test_one_review_is_editable_until_moderation_and_low_rating_can_be_published(): void
    {
        $this->finishStay();
        $this->actingAs($this->customer)->post(route('customer.reviews.store', $this->reservation), ['rating' => 2, 'body' => 'Room needed cleaning.', 'public_consent' => 1])->assertSessionHas('success');
        $this->post(route('customer.reviews.store', $this->reservation), ['rating' => 1, 'body' => 'The room needed more cleaning.', 'public_consent' => 1])->assertSessionHas('success');
        $this->assertDatabaseCount('guest_reviews', 1);
        $review = GuestReview::firstOrFail();
        $this->get(route('reviews'))->assertDontSee('The room needed more cleaning.');
        $this->actingAs($this->admin)->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertSee('The room needed more cleaning.')
            ->assertDontSee('Mark reviewed privately');
        $this->patch(route('admin.reviews.update', $review), ['action' => 'publish', 'rating' => 5, 'body' => 'Changed by admin'])->assertSessionHas('success');
        $this->assertSame(1, $review->fresh()->rating);
        $this->assertSame('The room needed more cleaning.', $review->fresh()->body);
        $this->get(route('reviews'))->assertOk()->assertSee('The room needed more cleaning.')->assertSee('Maria S.')->assertDontSee($this->reservation->reservation_code);
        $this->actingAs($this->customer)->post(route('customer.reviews.store', $this->reservation), ['rating' => 5, 'public_consent' => 1])->assertStatus(409);
        $this->actingAs($this->admin)->patch(route('admin.reviews.update', $review), ['action' => 'private'])->assertRedirect()->assertSessionHasErrors('action');
        $this->assertTrue($review->fresh()->is_published);
        $this->patch(route('admin.reviews.update', $review), ['action' => 'unpublish', 'reason' => 'Customer requested privacy.'])->assertSessionHas('success');
        $this->get(route('reviews'))->assertDontSee('The room needed more cleaning.');
    }

    public function test_published_legacy_review_stays_visible_when_unpublish_reason_is_missing(): void
    {
        $review = GuestReview::factory()->create(['user_id' => $this->customer->id, 'reservation_id' => $this->reservation->id,
            'public_consent' => null, 'is_published' => true, 'body' => 'Existing published review.']);

        $this->actingAs($this->admin)->patch(route('admin.reviews.update', $review), ['action' => 'unpublish'])
            ->assertRedirect()->assertSessionHasErrors("reason.{$review->id}");
        $this->assertTrue($review->fresh()->is_published);
        $this->get(route('admin.reviews.index'))->assertOk()
            ->assertSee('Moderation note')
            ->assertSee('Record the privacy, spam, or abuse reason.');

        $this->patch(route('admin.reviews.update', $review), ['action' => 'unpublish', 'reason' => 'Customer requested removal.'])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertFalse($review->fresh()->is_published);
    }

    public function test_existing_published_reviews_keep_their_visibility(): void
    {
        $review = GuestReview::factory()->create(['user_id' => $this->customer->id, 'reservation_id' => $this->reservation->id,
            'public_consent' => null, 'is_published' => true, 'body' => 'Existing published review.']);
        $this->get(route('reviews'))->assertOk()->assertSee($review->body);
    }
}
