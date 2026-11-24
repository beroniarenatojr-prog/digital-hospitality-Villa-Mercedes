<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Inquiry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InquiryRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_inquiry_routes_are_removed_for_customers_and_staff(): void
    {
        $this->seed(RoleSeeder::class);

        foreach ([Role::Customer, Role::Administrator] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role->value);
            $this->actingAs($user);

            foreach (['/customer/inquiries', '/admin/inquiries'] as $url) {
                $this->get($url)->assertNotFound();
                $this->get($url.'/1')->assertNotFound();
                $this->post($url, ['subject' => 'Question', 'message' => 'Hello'])->assertNotFound();
                $this->post($url.'/1/respond', ['response' => 'Hello'])->assertNotFound();
                $this->patch($url.'/1/status', ['status' => 'closed'])->assertNotFound();
            }
        }

        $this->assertFalse(Route::has('customer.inquiries.index'));
        $this->assertFalse(Route::has('admin.inquiries.index'));
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_guests_can_reach_social_support_without_signing_in(): void
    {
        foreach (['contact', 'faq.index'] as $page) {
            $this->get(route($page))
                ->assertOk()
                ->assertSee(config('resort.messenger_url'), false)
                ->assertDontSee('/customer/inquiries', false)
                ->assertDontSee('Log in to send an inquiry');
        }
    }

    public function test_historical_inquiry_activity_has_no_dead_link(): void
    {
        $inquiry = Inquiry::factory()->create();
        $activity = new ActivityLog;
        $activity->setRelation('subject', $inquiry);

        $this->assertSame($inquiry->subject, $activity->subjectLabel());
        $this->assertNull($activity->subjectUrl());
    }
}
