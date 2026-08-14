<?php

namespace Tests\Feature;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\Admin\InquiryManagementService;
use App\Services\Customer\CustomerInquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class InquiryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InquiryManagementService $adminService;
    private CustomerInquiryService $customerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminService = app(InquiryManagementService::class);
        $this->customerService = app(CustomerInquiryService::class);
    }

    public function test_customer_can_submit_and_view_own_inquiries()
    {
        $customer = User::factory()->create();

        $this->customerService->submitInquiry($customer, 'Room Query', 'Do you have early check in?');

        $inquiries = $this->customerService->getCustomerInquiries($customer);
        $this->assertCount(1, $inquiries);
        $this->assertEquals('Room Query', $inquiries->first()->subject);
        $this->assertEquals(InquiryStatus::Open, $inquiries->first()->status);
    }

    public function test_customer_cannot_view_another_customers_inquiries()
    {
        $customer1 = User::factory()->create();
        $customer2 = User::factory()->create();

        $inquiry = $this->customerService->submitInquiry($customer1, 'Query 1', 'Message 1');

        $this->expectException(AccessDeniedHttpException::class);
        $this->customerService->getInquiry($customer2, $inquiry);
    }

    public function test_admin_can_respond_and_update_status()
    {
        $customer = User::factory()->create();
        $admin = User::factory()->create();

        $inquiry = $this->customerService->submitInquiry($customer, 'Query', 'Msg');

        $this->adminService->respondToInquiry($inquiry, $admin, 'Yes we do.');

        $this->assertEquals('Yes we do.', $inquiry->fresh()->admin_response);
        $this->assertEquals($admin->id, $inquiry->fresh()->responded_by);
        $this->assertEquals(InquiryStatus::Pending, $inquiry->fresh()->status);

        $this->adminService->updateInquiryStatus($inquiry, $admin, InquiryStatus::Closed);
        $this->assertEquals(InquiryStatus::Closed, $inquiry->fresh()->status);
    }
}
