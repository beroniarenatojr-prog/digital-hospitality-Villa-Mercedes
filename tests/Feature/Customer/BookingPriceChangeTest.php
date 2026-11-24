<?php

namespace Tests\Feature\Customer;

use App\Enums\FacilityStatus;
use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Enums\RateType;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Rate;
use App\Models\User;
use Database\Seeders\FacilityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The page half of "the total never moves invisibly".
 *
 * The arithmetic and the wording live in `resources/js/price-change.js`, which
 * `tests/js/price-change.test.mjs` proves directly. What those tests cannot see is
 * whether the wizard actually reaches that code, or whether the words it prints
 * are the space's own — a function hall counts attendees, not guests. Both are
 * asserted here, against the rendered page.
 */
class BookingPriceChangeTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

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
    }

    public function test_the_wizard_hands_the_browser_the_words_and_rates_the_price_lines_need(): void
    {
        $config = $this->wizardConfig($this->wizard('room'));

        $this->assertSame('Base amount', $config['priceLabels']['base']);
        $this->assertSame('Extra guests', $config['priceLabels']['extraPerson']);
        $this->assertSame('Early check in', $config['priceLabels']['early']);
        $this->assertSame('Late check out', $config['priceLabels']['late']);
        $this->assertSame(['one' => 'guest', 'many' => 'guests'], $config['guestWords']);
        $this->assertSame('per night', $config['rates']['baseUnit']);
        $this->assertSame(2, $config['rates']['includedGuests'], 'The extra-guest arithmetic needs to know who is included.');
    }

    public function test_a_function_hall_prices_attendees_not_guests(): void
    {
        $config = $this->wizardConfig($this->wizard('function_hall'));

        $this->assertSame('Extra attendees', $config['priceLabels']['extraPerson']);
        $this->assertSame(['one' => 'attendee', 'many' => 'attendees'], $config['guestWords']);
    }

    public function test_every_total_on_the_page_says_whether_it_is_confirmed(): void
    {
        $response = $this->wizard('room');

        // The summary beside the form and the breakdown on the confirm step both
        // state which figure the guest is reading...
        $response->assertSee('Estimate from published rates. The confirmed total appears after you check availability.');
        $response->assertSee('Confirmed total for these dates, guests and hours.');

        // ...and so does the bar the guest leaves the page with, which names the
        // total or the movement instead of printing a bare figure.
        $response->assertSee('changeNote ? changeNoteHeadline : totalLabel', false);
    }

    public function test_the_breakdown_says_what_each_line_is_made_of_and_what_moved_it(): void
    {
        $response = $this->wizard('room');

        // One definition for every line, with its own arithmetic.
        $response->assertSee('x-for="term in terms"', false);
        $response->assertSee('x-text="term.detail"', false);
        $response->assertSee('x-text="term.label"', false);

        // And the note that explains a movement, beside every total the page
        // prints: the summary, the breakdown on the confirm step, and the bar.
        $response->assertSee('x-text="changeNoteHeadline"', false);
        $response->assertSee('x-text="changeNoteReasons"', false);
        $this->assertSame(
            2,
            substr_count($response->getContent(), 'x-text="changeNoteHeadline"'),
            'Both places the wizard quotes a total must carry the change note.'
        );
    }

    public function test_the_wizard_reaches_the_shared_price_logic_rather_than_its_own_copy(): void
    {
        $wizard = (string) file_get_contents(base_path('resources/views/customer/booking/create.blade.php'));

        $this->assertStringContainsString('window.bookingPrice.priceTerms(', $wizard);
        $this->assertStringContainsString('window.bookingPrice.priceChange(', $wizard);
        $this->assertStringContainsString('window.bookingPrice.inputsFrom(', $wizard);
        $this->assertStringContainsString(
            'window.bookingPrice.stayNights(',
            $wizard,
            'The night count and the price arithmetic must be one implementation.'
        );

        $bundle = (string) file_get_contents(base_path('resources/js/app.js'));
        $this->assertStringContainsString('window.bookingPrice =', $bundle);

        // Every quantity that can move the total reports the movement, so no edit
        // can be the one that changes the figure in silence.
        foreach (['priceInputChanged()', 'rememberTotal()'] as $call) {
            $this->assertStringContainsString($call, $wizard);
        }
    }

    private function wizard(string $type): TestResponse
    {
        $type = FacilityType::query()->where('slug', $type)->firstOrFail();

        $facility = Facility::factory()->create([
            'facility_type_id' => $type->id,
            'is_active' => true,
            'is_listed' => true,
            'status' => FacilityStatus::Available->value,
            'capacity' => 2,
            'max_capacity' => 6,
        ]);

        Rate::factory()->create([
            'facility_id' => $facility->id,
            'type' => RateType::Base->value,
            'amount' => 5000,
            'unit' => 'per night',
            'is_active' => true,
        ]);

        return $this->actingAs($this->customer)
            ->get(route('customer.booking.create', $facility))
            ->assertOk();
    }

    /**
     * The wizard's configuration, as the page hands it to the browser.
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
}
