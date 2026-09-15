<?php

namespace Tests\Feature\Admin;

use App\Enums\InquiryStatus;
use App\Enums\Role;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFaqInquiryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private FaqCategory $faqCategory;

    private Faq $faq;

    private Inquiry $inquiry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->customer = User::factory()->create();
        $this->customer->assignRole(Role::Customer->value);

        // Ensure there's at least one FAQ category and FAQ
        $this->faqCategory = FaqCategory::first() ?? FaqCategory::create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->faq = Faq::first() ?? Faq::create([
            'faq_category_id' => $this->faqCategory->id,
            'question' => 'What is check-in time?',
            'answer' => '2:00 PM',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->inquiry = Inquiry::create([
            'user_id' => $this->customer->id,
            'subject' => 'Help with booking',
            'message' => 'I need help booking a room for my family.',
            'status' => InquiryStatus::Open->value,
        ]);
    }

    // ==========================================
    // FAQ TESTS
    // ==========================================

    public function test_unauthenticated_blocked_from_faqs(): void
    {
        $this->get(route('admin.faq-categories.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.faqs.index'))->assertRedirect(route('admin.login'));
    }

    public function test_customer_blocked_from_faqs(): void
    {
        $this->actingAs($this->customer)->get(route('admin.faq-categories.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($this->customer)->get(route('admin.faqs.index'))->assertRedirect(route('admin.login'));
    }

    public function test_administrator_allowed_faq_listing(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.faqs.index'));
        $response->assertStatus(200);
        $response->assertSee($this->faq->question);

        $response = $this->actingAs($this->admin)->get(route('admin.faq-categories.index'));
        $response->assertStatus(200);
        $response->assertSee($this->faqCategory->name);
    }

    public function test_admin_can_create_faq_category(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.faq-categories.store'), [
            'name' => 'Payment Issues',
            'slug' => 'payment-issues',
            'sort_order' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('faq_categories', ['slug' => 'payment-issues']);
    }

    public function test_admin_can_update_faq_category(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.faq-categories.update', $this->faqCategory), [
            'name' => 'Updated Category',
            'slug' => 'updated-category',
            'sort_order' => 10,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('faq_categories', ['id' => $this->faqCategory->id, 'slug' => 'updated-category']);
    }

    public function test_admin_can_toggle_faq_category_status(): void
    {
        $this->assertTrue($this->faqCategory->is_active);

        $response = $this->actingAs($this->admin)->patch(route('admin.faq-categories.status', $this->faqCategory));
        $response->assertRedirect();

        $this->faqCategory->refresh();
        $this->assertFalse($this->faqCategory->is_active);
    }

    public function test_admin_can_create_faq(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.faqs.store'), [
            'faq_category_id' => $this->faqCategory->id,
            'question' => 'Can I bring pets?',
            'answer' => 'No pets allowed.',
            'sort_order' => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('faqs', ['question' => 'Can I bring pets?']);
    }

    public function test_admin_can_update_faq(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.faqs.update', $this->faq), [
            'faq_category_id' => $this->faqCategory->id,
            'question' => 'Updated Question?',
            'answer' => 'Updated Answer.',
            'sort_order' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('faqs', ['id' => $this->faq->id, 'question' => 'Updated Question?']);
    }

    public function test_admin_can_toggle_faq_status(): void
    {
        $this->assertTrue($this->faq->is_active);

        $response = $this->actingAs($this->admin)->patch(route('admin.faqs.status', $this->faq));
        $response->assertRedirect();

        $this->faq->refresh();
        $this->assertFalse($this->faq->is_active);
    }

    public function test_invalid_faq_data_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.faqs.store'), [
            'question' => '', // missing category and answer
        ]);

        $response->assertSessionHasErrors(['faq_category_id', 'question', 'answer']);
    }

    // ==========================================
    // INQUIRY TESTS
    // ==========================================

    public function test_unauthenticated_blocked_from_inquiries(): void
    {
        $this->get(route('admin.inquiries.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.inquiries.show', $this->inquiry))->assertRedirect(route('admin.login'));
    }

    public function test_customer_cannot_access_admin_inquiry_routes(): void
    {
        $this->actingAs($this->customer)->get(route('admin.inquiries.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($this->customer)->get(route('admin.inquiries.show', $this->inquiry))->assertRedirect(route('admin.login'));
        $this->actingAs($this->customer)->post(route('admin.inquiries.respond', $this->inquiry), ['response' => 'test'])->assertRedirect(route('admin.login'));
        $this->actingAs($this->customer)->patch(route('admin.inquiries.status', $this->inquiry), ['status' => 'closed'])->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_inquiry_queue(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inquiries.index'));
        $response->assertStatus(200);
        $response->assertSee($this->inquiry->subject);
    }

    public function test_inquiry_filtering_works(): void
    {
        Inquiry::create([
            'user_id' => $this->customer->id,
            'subject' => 'Closed Inquiry',
            'message' => 'This is resolved.',
            'status' => InquiryStatus::Closed->value,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inquiries.index', ['status' => 'closed']));
        $response->assertStatus(200);
        $response->assertSee('Closed Inquiry');
        $response->assertDontSee('Help with booking'); // The open one
    }

    public function test_inquiry_search_works(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inquiries.index', ['search' => 'booking']));
        $response->assertStatus(200);
        $response->assertSee('Help with booking');
    }

    public function test_admin_can_view_inquiry_detail(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inquiries.show', $this->inquiry));
        $response->assertStatus(200);
        $response->assertSee($this->inquiry->subject);
        $response->assertSee($this->inquiry->message);
    }

    public function test_customer_information_displays_safely(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inquiries.show', $this->inquiry));
        $response->assertStatus(200);
        $response->assertSee($this->customer->name);
        $response->assertSee($this->customer->email);
        $response->assertDontSee($this->customer->password);
    }

    public function test_admin_can_respond(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.inquiries.respond', $this->inquiry), [
            'response' => 'This is an official admin response.',
            'status' => InquiryStatus::Pending->value, // Wait for customer
        ]);

        $response->assertRedirect();

        $this->inquiry->refresh();
        $this->assertEquals('This is an official admin response.', $this->inquiry->admin_response);
        $this->assertEquals($this->admin->id, $this->inquiry->responded_by);
        $this->assertEquals(InquiryStatus::Pending, $this->inquiry->status);
    }

    public function test_admin_can_update_status(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.inquiries.status', $this->inquiry), [
            'status' => InquiryStatus::Closed->value,
        ]);

        $response->assertRedirect();

        $this->inquiry->refresh();
        $this->assertEquals(InquiryStatus::Closed, $this->inquiry->status);
    }

    public function test_invalid_status_transition_rejected(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.inquiries.status', $this->inquiry), [
            'status' => 'invalid-status',
        ]);

        $response->assertSessionHasErrors(['status']);
    }
}
