<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\User;
use App\Services\Admin\FaqManagementService;
use App\Services\Customer\CustomerFaqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqServiceTest extends TestCase
{
    use RefreshDatabase;

    private FaqManagementService $adminService;
    private CustomerFaqService $customerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminService = app(FaqManagementService::class);
        $this->customerService = app(CustomerFaqService::class);
    }

    public function test_admin_can_create_and_update_category()
    {
        $admin = User::factory()->create();

        $category = $this->adminService->createCategory($admin, [
            'name' => 'General Information',
            'slug' => 'general-information',
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('faq_categories', ['name' => 'General Information']);

        $this->adminService->updateCategory($admin, $category, ['name' => 'General Info']);

        $this->assertDatabaseHas('faq_categories', ['name' => 'General Info']);
    }

    public function test_customer_can_retrieve_active_categories()
    {
        FaqCategory::factory()->create(['name' => 'Active Cat', 'is_active' => true, 'sort_order' => 1]);
        FaqCategory::factory()->create(['name' => 'Inactive Cat', 'is_active' => false, 'sort_order' => 2]);

        $categories = $this->customerService->getActiveCategories();

        $this->assertCount(1, $categories);
        $this->assertEquals('Active Cat', $categories->first()->name);
    }

    public function test_customer_can_retrieve_active_faqs_and_filter_by_category()
    {
        $cat1 = FaqCategory::factory()->create(['name' => 'Cat 1', 'is_active' => true]);
        $cat2 = FaqCategory::factory()->create(['name' => 'Cat 2', 'is_active' => true]);
        $inactiveCat = FaqCategory::factory()->create(['name' => 'Cat 3', 'is_active' => false]);

        Faq::factory()->create(['faq_category_id' => $cat1->id, 'question' => 'Q1', 'is_active' => true]);
        Faq::factory()->create(['faq_category_id' => $cat1->id, 'question' => 'Q2', 'is_active' => false]);
        Faq::factory()->create(['faq_category_id' => $cat2->id, 'question' => 'Q3', 'is_active' => true]);
        Faq::factory()->create(['faq_category_id' => $inactiveCat->id, 'question' => 'Q4', 'is_active' => true]);

        $allFaqs = $this->customerService->getActiveFaqs();
        // Should only see Q1 and Q3. Q2 is inactive. Q4 is in an inactive category.
        $this->assertCount(2, $allFaqs);
        $this->assertTrue($allFaqs->pluck('question')->contains('Q1'));
        $this->assertTrue($allFaqs->pluck('question')->contains('Q3'));

        $filteredFaqs = $this->customerService->getActiveFaqs($cat1);
        $this->assertCount(1, $filteredFaqs);
        $this->assertEquals('Q1', $filteredFaqs->first()->question);
    }
}
