<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One word per state, on both sides of the front desk.
 *
 * The guest portal and the back office used to be two vocabularies for the same
 * booking: staff read the stored value — "pending", "approved", "unavailable" —
 * while the guest read the label — "Pending", "Approved", "Under Maintenance".
 * The same reservation therefore had two names depending on who was looking at
 * it, and a guest on the phone reading their screen was not using the staff's
 * words.
 *
 * Every state now comes from one enum that owns its word (`label()`) and its
 * colour (`tone()`), and one component (`x-badge`) renders it on both sides.
 * This asserts the outcome rather than the mechanism: for each state, the word
 * the staff screen shows and the word the guest screen shows must be identical,
 * and neither side may show the stored value instead.
 */
class StatusVocabularyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->customer = User::factory()->create();
        $this->customer->assignRole(Role::Customer->value);
    }

    public function test_a_reservation_state_reads_the_same_to_staff_and_to_the_guest(): void
    {
        $facility = Facility::factory()->create();

        foreach (ReservationStatus::cases() as $status) {
            $reservation = Reservation::factory()->create([
                'user_id' => $this->customer->id,
                'facility_id' => $facility->id,
                'status' => $status->value,
            ]);

            $this->assertSameWord(
                $status,
                $this->chipWords($this->actingAs($this->admin)->get(route('admin.reservations.show', $reservation))->getContent()),
                $this->chipWords($this->actingAs($this->customer)->get(route('customer.reservations.show', $reservation))->getContent()),
            );
        }
    }

    public function test_a_payment_state_reads_the_same_to_staff_and_to_the_guest(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'facility_id' => Facility::factory()->create()->id,
            'status' => ReservationStatus::Approved->value,
        ]);

        $payments = [];

        foreach (PaymentStatus::cases() as $status) {
            $payments[$status->value] = Payment::factory()->create([
                'reservation_id' => $reservation->id,
                'status' => $status->value,
            ]);
        }

        $guestPage = $this->actingAs($this->customer)
            ->get(route('customer.payments.index', $reservation))
            ->getContent();

        foreach (PaymentStatus::cases() as $status) {
            $this->assertSameWord(
                $status,
                $this->chipWords($this->actingAs($this->admin)->get(route('admin.payments.show', $payments[$status->value]))->getContent()),
                $this->chipWords($guestPage),
            );
        }
    }

    /**
     * The word is on both screens, and the stored value is on neither.
     *
     * @param  list<string>  $staffWords
     * @param  list<string>  $guestWords
     */
    private function assertSameWord(\BackedEnum $state, array $staffWords, array $guestWords): void
    {
        $label = $state->label();
        $context = ' for the state "'.$state->value.'"';

        $this->assertContains($label, $staffWords, 'The staff screen does not say "'.$label.'"'.$context.'.');
        $this->assertContains($label, $guestWords, 'The guest screen does not say "'.$label.'"'.$context.'.');

        // The stored value is a database spelling, never a word for a guest or a
        // staff member to read. It used to leak into the badge unchanged.
        if ($state->value !== $label) {
            $this->assertNotContains($state->value, $staffWords, 'The staff screen shows the stored value "'.$state->value.'" instead of "'.$label.'".');
            $this->assertNotContains($state->value, $guestWords, 'The guest screen shows the stored value "'.$state->value.'" instead of "'.$label.'".');
        }
    }

    /**
     * The words a reader sees inside a status chip, on either side of the desk.
     *
     * Deliberately narrow: a chip is a span wearing one of the tone backgrounds,
     * so this reads the badge component's own output rather than every word on
     * the page — a heading that happens to contain "Pending" is not a status.
     *
     * @return list<string>
     */
    private function chipWords(string $html): array
    {
        preg_match_all(
            '/<span[^>]*class="[^"]*bg-(?:success|warning|danger|info|secondary)(?:-light)?[^"]*"[^>]*>(.*?)<\/span>/s',
            $html,
            $matches,
        );

        $words = array_map(
            fn (string $inner): string => trim(preg_replace('/\s+/', ' ', strip_tags($inner)) ?? ''),
            $matches[1] ?? [],
        );

        return array_values(array_unique(array_filter($words)));
    }
}
