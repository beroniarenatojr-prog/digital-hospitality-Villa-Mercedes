<?php

namespace Tests\Feature\Customer;

use App\Enums\FacilityStatus;
use App\Enums\RateType;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Rate;
use App\Models\SwimmingSession;
use App\Models\User;
use Database\Seeders\FacilityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notices on the cottage booking form.
 *
 * The form used to open with two alert boxes already filled in — "Something went
 * wrong" and "Success" — on a visit where nothing had been submitted. Both were
 * the same mistake: `:message="session('error')"` was passed to a component that
 * reads its text from a slot, so the value landed in `$attributes` as an HTML
 * attribute, the body rendered empty, and the boxes appeared on every visit.
 *
 * Two rules come out of that, and both are checked here: the form must not greet
 * a guest with a notice about nothing, and the alert component itself must not
 * draw a titled frame with no text inside it. The second rule is the one that
 * makes the first one survivable — a future page cannot ship the same blank box.
 */
class CottageBookingPageTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Facility $cottage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FacilityTypeSeeder::class);

        $this->customer = User::factory()->create();

        $type = FacilityType::query()->where('slug', 'cottage')->firstOrFail();

        $this->cottage = Facility::factory()->create([
            'facility_type_id' => $type->id,
            'is_active' => true,
            'status' => FacilityStatus::Available->value,
            'capacity' => 10,
            'max_capacity' => 20,
        ]);

        Rate::factory()->create([
            'facility_id' => $this->cottage->id,
            'type' => RateType::Base->value,
            'amount' => 500,
            'unit' => 'per session',
            'is_active' => true,
        ]);

        SwimmingSession::create([
            'name' => 'Day Swimming',
            'slug' => 'day-swimming-notice-test',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'adult_entrance_fee' => 100,
            'cottage_fee' => 500,
            'children_allowed' => true,
            'is_active' => true,
        ]);
    }

    public function test_the_cottage_form_opens_with_no_notice_at_all(): void
    {
        $html = (string) $this->actingAs($this->customer)
            ->get(route('customer.booking.create', $this->cottage))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Something went wrong', $html, 'The form promised a failure that never happened.');
        $this->assertSame(0, substr_count($html, 'role="alert"'), 'The form opened with a notice about nothing.');
    }

    public function test_a_cottage_notice_is_still_announced_exactly_once(): void
    {
        $notice = 'This cottage is no longer available for that visit.';

        $html = (string) $this->actingAs($this->customer)
            ->withSession(['error' => $notice])
            ->get(route('customer.booking.create', $this->cottage))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, $notice), 'The guest must read the reason once, not twice.');
    }

    public function test_an_alert_with_nothing_to_say_is_not_rendered(): void
    {
        $empty = (string) $this->blade('<x-alert type="error" :message="$message" />', ['message' => null]);

        $this->assertSame('', trim($empty), 'An empty alert is a coloured box that reads as a real failure.');
        $this->assertStringNotContainsString('message=', $empty, 'A `message` attribute must not leak into the markup.');
    }

    public function test_an_alert_renders_its_message_however_it_was_passed(): void
    {
        $slot = (string) $this->blade('<x-alert type="success">Your request was sent.</x-alert>');
        $attribute = (string) $this->blade('<x-alert type="success" :message="$message" />', ['message' => 'Your request was sent.']);

        $this->assertStringContainsString('Your request was sent.', $slot);
        $this->assertStringContainsString('Your request was sent.', $attribute);
    }

    public function test_an_alert_with_only_a_title_still_renders(): void
    {
        // The reservation wizard fills its alert body from Alpine, so the markup
        // it ships is a titled box with an empty <span> in it. Silence there
        // would remove a real error the guest is about to be shown.
        $html = (string) $this->blade('<x-alert type="danger" title="We couldn\'t continue"><span x-text="error"></span></x-alert>');

        // The title is escaped as any Blade attribute is; the apostrophe is not
        // the point of this test, the box surviving on its title alone is.
        $this->assertStringContainsString('We couldn&#039;t continue', $html);
        $this->assertStringContainsString('x-text="error"', $html);
    }
}
