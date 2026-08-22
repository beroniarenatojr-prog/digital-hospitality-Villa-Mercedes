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
