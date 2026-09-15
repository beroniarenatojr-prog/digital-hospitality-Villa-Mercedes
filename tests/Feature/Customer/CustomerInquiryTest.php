<?php

namespace Tests\Feature\Customer;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerInquiryTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $otherCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create();
        $this->otherCustomer = User::factory()->create();
    }

    public function test_failed_inquiry_validation_restores_input(): void
    {
        $response = $this->actingAs($this->customer)
            ->from(route('customer.inquiries.index'))
            ->post(route('customer.inquiries.store'), [
                'subject' => '',
                'message' => 'Kept after validation fails.',
            ]);

        $response
            ->assertRedirect(route('customer.inquiries.index'))
            ->assertSessionHasErrors('subject');

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Kept after validation fails.')
            ->assertSee('showModal: true', false);
    }

    public function test_customer_can_create_an_inquiry()
    {
        $response = $this->actingAs($this->customer)->post(route('customer.inquiries.store'), [
            'subject' => 'Booking Question',
            'message' => 'Do you allow pets?',
        ]);

        $inquiry = Inquiry::first();
        $response->assertRedirect(route('customer.inquiries.show', $inquiry));

        $this->assertDatabaseHas('inquiries', [
            'user_id' => $this->customer->id,
            'subject' => 'Booking Question',
            'message' => 'Do you allow pets?',
            'status' => InquiryStatus::Open->value,
        ]);
    }

    public function test_customer_can_create_a_trackable_inquiry_from_chat(): void
    {
        $response = $this->actingAs($this->customer)->postJson(route('customer.inquiries.store'), [
            'subject' => 'Support chat follow-up',
            'message' => 'Please help me choose a room for six guests.',
        ]);

        $inquiry = Inquiry::firstOrFail();

        $response
            ->assertCreated()
            ->assertJsonPath('inquiry_id', $inquiry->id)
            ->assertJsonPath('inquiry_url', route('customer.inquiries.show', $inquiry));

        $this->assertDatabaseHas('inquiries', [
            'user_id' => $this->customer->id,
            'subject' => 'Support chat follow-up',
            'status' => InquiryStatus::Open->value,
        ]);
    }

    public function test_customer_can_view_own_inquiries()
    {
        Inquiry::factory()->create([
            'user_id' => $this->customer->id,
            'subject' => 'My First Inquiry',
        ]);

        $response = $this->actingAs($this->customer)->get(route('customer.inquiries.index'));

        $response->assertStatus(200);
        $response->assertSee('My First Inquiry');
    }

    public function test_customer_cannot_view_another_customers_inquiry()
    {
        $inquiry = Inquiry::factory()->create([
            'user_id' => $this->otherCustomer->id,
            'subject' => 'Top Secret',
        ]);

        $response = $this->actingAs($this->customer)->get(route('customer.inquiries.show', $inquiry));

        $response->assertStatus(403); // Access denied
    }

    public function test_administrator_response_is_displayed()
    {
        $admin = User::factory()->create();

        $inquiry = Inquiry::factory()->create([
            'user_id' => $this->customer->id,
            'subject' => 'My Inquiry',
            'admin_response' => 'Yes, we allow pets!',
            'responded_by' => $admin->id,
            'responded_at' => now(),
        ]);

        $response = $this->actingAs($this->customer)->get(route('customer.inquiries.show', $inquiry));

        $response->assertStatus(200);
        $response->assertSee('Yes, we allow pets!');
    }

    public function test_existing_inquiry_status_is_displayed_correctly()
    {
        $inquiry = Inquiry::factory()->create([
            'user_id' => $this->customer->id,
            'status' => InquiryStatus::Closed->value,
        ]);

        $response = $this->actingAs($this->customer)->get(route('customer.inquiries.index'));
        $response->assertStatus(200);
        $response->assertSee(InquiryStatus::Closed->label());

        $response = $this->actingAs($this->customer)->get(route('customer.inquiries.show', $inquiry));
        $response->assertStatus(200);
        $response->assertSee(InquiryStatus::Closed->label());
    }

    public function test_authenticated_customer_sees_inquiry_entry_on_contact_page(): void
    {
        $this->actingAs($this->customer)
            ->get(route('contact'))
            ->assertOk()
            ->assertSee('Send an inquiry')
            ->assertDontSee('Log in to send an inquiry');
    }

    public function test_guest_cannot_post_an_inquiry(): void
    {
        $this->post(route('customer.inquiries.store'), [
            'subject' => 'Anonymous',
            'message' => 'Should not work',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_validation_works_and_cannot_spoof_status_or_user()
    {
        $response = $this->actingAs($this->customer)->post(route('customer.inquiries.store'), [
            'subject' => 'Hacked Inquiry',
            'message' => 'Spoofing status',
            'status' => InquiryStatus::Closed->value, // Should be ignored
            'user_id' => $this->otherCustomer->id, // Should be ignored
        ]);

        $inquiry = Inquiry::first();

        $this->assertEquals($this->customer->id, $inquiry->user_id);
        $this->assertEquals(InquiryStatus::Open, $inquiry->status);
    }
}
