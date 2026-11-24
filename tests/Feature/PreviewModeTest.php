<?php

namespace Tests\Feature;

use Tests\TestCase;

class PreviewModeTest extends TestCase
{
    public function test_preview_responses_are_not_indexable_without_visible_demo_notices(): void
    {
        config(['preview.enabled' => true, 'preview.public_interface' => true]);

        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertDontSee('Demonstration only');

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Demonstration guest account')
            ->assertDontSee('customer@villamercedes.test');
    }

    public function test_public_preview_hides_the_entire_back_office(): void
    {
        config(['preview.enabled' => true, 'preview.public_interface' => true]);

        $this->get('/admin/login')->assertNotFound();
        $this->get('/admin/dashboard')->assertNotFound();
        $this->get('/payment-destinations/1/qr')->assertNotFound();
    }

    public function test_private_preview_keeps_the_admin_login_available(): void
    {
        config(['preview.enabled' => true, 'preview.public_interface' => false]);

        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('Demonstration only');
    }

    public function test_registration_and_payment_actions_are_disabled(): void
    {
        config(['preview.enabled' => true, 'preview.public_interface' => true]);

        $this->get('/register')
            ->assertForbidden()
            ->assertSee('This action is unavailable');

        $this->post('/customer/reservations/1/payments')
            ->assertForbidden()
            ->assertSee('This action is unavailable');
    }
}
