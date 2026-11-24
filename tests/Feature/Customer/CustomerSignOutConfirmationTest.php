<?php

namespace Tests\Feature\Customer;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guests end a session from more than one place: the account menu in the public
 * header, the phone menu under it, and the account bar on the account pages.
 * Each of those is a click that throws away whatever is half-typed, so each of
 * them asks first — and every one of them is still a real form posting the
 * logout, because a confirmation that only works while the script runs would
 * leave a guest signed in with no way out.
 */
class CustomerSignOutConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_every_guest_sign_out_control_asks_first(): void
    {
        foreach ([
            'the public site' => route('home'),
            'the account pages' => route('customer.reservations.index'),
        ] as $label => $url) {
            $html = $this->actingAs($this->customer())->get($url)->getContent();

            $this->assertStringContainsString('role="dialog"', $html, "A sign-out control on {$label} does not ask first.");
            $this->assertStringContainsString('Sign out?', $html, "The sign-out dialog on {$label} does not ask anything.");
            $this->assertStringContainsString('Stay signed in', $html, "The sign-out dialog on {$label} offers no way to stay signed in.");

            // Each control contributes two forms carrying the logout action — the
            // trigger and the dialog's own — and one dialog, whose title is the
            // string counted here. A control that skips the question breaks the
            // pairing, which is the point of counting: there is no other "Sign
            // out?" on these pages, and other dialogs on them are not sign-outs.
            $this->assertSame(
                2 * substr_count($html, 'Sign out?'),
                substr_count($html, 'action="'.route('logout').'"'),
                "A sign-out control on {$label} is not paired with a confirmation."
            );
        }
    }

    public function test_a_guest_sign_out_still_posts_without_the_script(): void
    {
        $html = $this->actingAs($this->customer())
            ->get(route('customer.reservations.index'))
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<form method="POST" action="'.preg_quote(route('logout'), '/').'">.*?<button[^>]*type="submit"[^>]*@click\.prevent/s',
            $html,
            'A guest sign-out control is not a submit button that JavaScript merely improves.'
        );

        $this->assertMatchesRegularExpression(
            '/name="_token" value="[A-Za-z0-9]{40}"/',
            $html,
            'A guest sign-out form carries no CSRF token, so submitting it without the script would be refused.'
        );
    }

    public function test_a_guest_waiting_on_verification_can_still_sign_out_with_a_confirmation(): void
    {
        $customer = $this->customer(['email_verified_at' => null]);

        $html = $this->actingAs($customer)->get(route('verification.notice'))->getContent();

        $this->assertStringContainsString('role="dialog"', $html, 'The verification page sign-out does not ask first.');
        $this->assertStringContainsString('action="'.route('logout').'"', $html, 'The verification page sign-out is not a form.');
    }

    private function customer(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::Customer->value);

        return $user;
    }
}
