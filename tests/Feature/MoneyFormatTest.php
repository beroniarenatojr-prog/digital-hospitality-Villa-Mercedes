<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Money;
use App\Support\ReservationArrivalMail;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * One resort, one peso format.
 *
 * The same amount is read in at least four places: a facility page, a booking
 * preview that recalculates as the guest types, the guest's reservation, the
 * staff ledger, and the arrival-pass email. A figure that looks different in two
 * of them reads as two different figures — "₱500" next to "₱500.00" is a support
 * call about a discrepancy that does not exist.
 *
 * So there is one definition (`App\Support\Money`) and the rest mirrors it. The
 * mirror is the risk: the browser cannot share PHP, and an email body cannot
 * render a Blade component. This asserts the mirrors agree with the definition,
 * because a comment promising they do is not a guarantee.
 */
class MoneyFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_format_is_two_decimals_with_thousands_separators(): void
    {
        $this->assertSame('1,350.50', Money::format(1350.5));
        $this->assertSame('1,350.50', Money::format('1350.5'));
        $this->assertSame('1,250.00', Money::format(1250));
        $this->assertSame('1,234,567.89', Money::format(1234567.89));

        // A third decimal is rounded away rather than printed: money that renders
        // as "1,350.556" is money nobody can reconcile against a statement.
        $this->assertSame('1,350.56', Money::format(1350.556));

        $this->assertSame('0.00', Money::format(0));
        $this->assertSame('0.00', Money::format(null));
    }

    public function test_the_symbol_is_never_separated_from_its_figure(): void
    {
        $this->assertSame('₱1,350.50', Money::of(1350.5));
        $this->assertSame('₱0.00', Money::of(0));
    }

    public function test_the_amount_component_renders_the_one_format_and_cannot_wrap(): void
    {
        $html = Blade::render('<x-amount :value="1350.5" />');

        $this->assertStringContainsString('1,350.50', $html);
        $this->assertStringContainsString(Money::SYMBOL, $html);

        // The figure and the sign are one unbreakable unit, and the digits are
        // tabular so a column of amounts lines up on the decimal point.
        $this->assertStringContainsString('whitespace-nowrap', $html);
        $this->assertStringContainsString('tabular-nums', $html);
    }

    public function test_the_browser_mirror_formats_money_exactly_as_the_server_does(): void
    {
        $node = $this->nodeBinary();

        if ($node === null) {
            $this->markTestSkipped('Node is not available to run the browser money mirror.');
        }

        // The values a booking preview actually recalculates: a whole peso, a
        // half peso, a big total, and a rate with a cent in it.
        $values = [0, 5, 0.5, 1250, 1350.5, 999.99, 12345.6, 1000000, 1234567.89];

        $module = str_replace('\\', '/', resource_path('js/money.js'));
        $probe = tempnam(sys_get_temp_dir(), 'money-mirror').'.mjs';

        file_put_contents($probe, implode("\n", [
            'import { pesos } from '.json_encode('file:///'.$module).';',
            'const values = '.json_encode($values).';',
            'console.log(JSON.stringify(values.map((value) => pesos(value))));',
        ]));

        exec(escapeshellarg($node).' '.escapeshellarg($probe).' 2>&1', $output, $status);
        unlink($probe);

        $printed = json_decode(trim(implode("\n", $output)), true);

        $this->assertSame(0, $status, 'The browser money mirror failed to run: '.implode("\n", $output));
        $this->assertSame(
            array_map(fn ($value) => Money::of($value), $values),
            $printed,
            'The browser formats money differently from App\Support\Money, so a live preview '
            .'would show a figure the saved reservation does not.',
        );
    }

    public function test_the_email_and_the_guest_page_quote_the_same_amount(): void
    {
        $this->seed(RoleSeeder::class);

        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $reservation = Reservation::factory()->create([
            'user_id' => $customer->id,
            'facility_id' => Facility::factory()->create()->id,
            'base_price' => 1350.5,
            'additional_charges' => 0,
            'total_price' => 1350.5,
            'status' => ReservationStatus::Approved->value,
            'guest_name' => $customer->name,
        ]);

        // What the guest reads on the page, rendered by x-amount. The component
        // puts the sign in its own span so a caller can restyle the figure, so
        // the comparison is made on the page's text rather than on its markup:
        // "₱" and "1,350.50" are two elements but one amount.
        $page = $this->actingAs($customer)->get(route('customer.reservations.show', $reservation));
        $page->assertOk();
        $this->assertStringContainsString(Money::of($reservation->total_price), $this->textOf($page->getContent()));

        // ...and what the same guest reads in the email, which is a mail body and
        // therefore cannot use the component. Both sides are compared as text,
        // with entities decoded, because that is what a person reads.
        $mail = html_entity_decode(
            ReservationArrivalMail::message($reservation, 'Here is a copy of your reservation.')->render(),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );

        $this->assertStringContainsString(Money::of($reservation->total_price), $mail);
    }

    /**
     * The page as a person reads it: tags removed, whitespace collapsed. Used
     * wherever the answer is "does the reader see this figure", which is not the
     * same question as "does this markup contain this substring".
     */
    private function textOf(?string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function nodeBinary(): ?string
    {
        if (! function_exists('exec')) {
            return null;
        }

        exec('node --version 2>&1', $output, $status);

        return $status === 0 ? 'node' : null;
    }
}
