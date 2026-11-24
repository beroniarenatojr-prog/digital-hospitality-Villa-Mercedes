<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\CloseOutdatedBookings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloseOutdatedBookingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_outdated_requests_and_payment_reviews_are_closed(): void
    {
        $pendingEnded = Reservation::factory()->create([
            'status' => ReservationStatus::Pending->value,
            'check_in' => now()->subDays(4)->setTime(14, 0),
            'check_out' => now()->subDays(2)->setTime(12, 0),
        ]);
        $pendingProof = Payment::factory()->create([
            'reservation_id' => $pendingEnded->id,
            'status' => PaymentStatus::Pending->value,
        ]);

        $unpaidEnded = Reservation::factory()->create([
            'status' => ReservationStatus::Approved->value,
            'check_in' => now()->subDays(5)->setTime(14, 0),
            'check_out' => now()->subDays(3)->setTime(12, 0),
        ]);
        $unpaidProof = Payment::factory()->create([
            'reservation_id' => $unpaidEnded->id,
            'status' => PaymentStatus::Pending->value,
        ]);

        $paidEnded = Reservation::factory()->create([
            'status' => ReservationStatus::Approved->value,
            'check_in' => now()->subDays(3)->setTime(14, 0),
            'check_out' => now()->subDay()->setTime(12, 0),
        ]);
        Payment::factory()->create([
            'reservation_id' => $paidEnded->id,
            'status' => PaymentStatus::Verified->value,
            'amount' => $paidEnded->total_price,
        ]);

        $alreadyExpired = Reservation::factory()->create([
            'status' => ReservationStatus::Expired->value,
            'check_in' => now()->subDays(10)->setTime(14, 0),
            'check_out' => now()->subDays(8)->setTime(12, 0),
            'expired_at' => now()->subDays(9),
        ]);
        $staleExpiredProof = Payment::factory()->create([
            'reservation_id' => $alreadyExpired->id,
            'status' => PaymentStatus::Pending->value,
        ]);

        $current = Reservation::factory()->create([
            'status' => ReservationStatus::Pending->value,
            'check_in' => now()->addDays(4)->setTime(14, 0),
            'check_out' => now()->addDays(5)->setTime(12, 0),
        ]);
        $currentProof = Payment::factory()->create([
            'reservation_id' => $current->id,
            'status' => PaymentStatus::Pending->value,
        ]);

        $this->artisan('reservations:close-outdated')
            ->expectsOutput('Closed outdated bookings: 3 payment reviews rejected, 1 expired, 1 cancelled, 1 completed.')
            ->assertSuccessful();

        $this->assertSame(ReservationStatus::Expired, $pendingEnded->fresh()->status);
        $this->assertSame(PaymentStatus::Rejected, $pendingProof->fresh()->status);
        $this->assertSame(CloseOutdatedBookings::PAYMENT_REASON, $pendingProof->fresh()->rejection_reason);

        $this->assertSame(ReservationStatus::Cancelled, $unpaidEnded->fresh()->status);
        $this->assertSame(CloseOutdatedBookings::CANCEL_REASON, $unpaidEnded->fresh()->cancellation_reason);
        $this->assertSame(PaymentStatus::Rejected, $unpaidProof->fresh()->status);

        $this->assertSame(ReservationStatus::Completed, $paidEnded->fresh()->status);
        $this->assertNotNull($paidEnded->fresh()->checked_out_at);

        $this->assertSame(PaymentStatus::Rejected, $staleExpiredProof->fresh()->status);

        $this->assertSame(ReservationStatus::Pending, $current->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $currentProof->fresh()->status);
    }
}
