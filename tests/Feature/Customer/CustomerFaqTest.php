<?php

namespace Tests\Feature\Customer;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerFaqTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_faq_page_loads()
    {
        $response = $this->get(route('faq.index'));

        $response->assertStatus(200);
        $response->assertSee('Frequently Asked Questions');
    }

    public function test_active_faq_records_display()
    {
        $category = FaqCategory::factory()->create(['is_active' => true]);
        $faq = Faq::factory()->create([
            'faq_category_id' => $category->id,
            'is_active' => true,
            'question' => 'What is the active question?',
        ]);

        $response = $this->get(route('faq.index'));

        $response->assertStatus(200);
        $response->assertSee($category->name);
        $response->assertSee('What is the active question?');
    }

    public function test_inactive_faqs_are_hidden()
    {
        $category = FaqCategory::factory()->create(['is_active' => true]);
        
        $activeFaq = Faq::factory()->create([
            'faq_category_id' => $category->id,
            'is_active' => true,
            'question' => 'Active FAQ',
        ]);
        
        $inactiveFaq = Faq::factory()->create([
            'faq_category_id' => $category->id,
            'is_active' => false,
            'question' => 'Inactive FAQ',
        ]);

        $inactiveCategory = FaqCategory::factory()->create(['is_active' => false, 'name' => 'Hidden Category']);
        $faqInInactiveCategory = Faq::factory()->create([
            'faq_category_id' => $inactiveCategory->id,
            'is_active' => true,
            'question' => 'Should be hidden',
        ]);

        $response = $this->get(route('faq.index'));

        $response->assertStatus(200);
        $response->assertSee('Active FAQ');
        $response->assertDontSee('Inactive FAQ');
        $response->assertDontSee('Hidden Category');
        $response->assertDontSee('Should be hidden');
    }

    public function test_category_filtering_works()
    {
        $category1 = FaqCategory::factory()->create(['is_active' => true, 'name' => 'Category One']);
        $category2 = FaqCategory::factory()->create(['is_active' => true, 'name' => 'Category Two']);

        Faq::factory()->create([
            'faq_category_id' => $category1->id,
            'is_active' => true,
            'question' => 'Question 1',
        ]);

        Faq::factory()->create([
            'faq_category_id' => $category2->id,
            'is_active' => true,
            'question' => 'Question 2',
        ]);

        $response = $this->get(route('faq.index', ['category_id' => $category1->id]));

        $response->assertStatus(200);
        $response->assertSee('Question 1');
        $response->assertDontSee('Question 2');
    }

    public function test_empty_faq_state_works()
    {
        $response = $this->get(route('faq.index'));

        $response->assertStatus(200);
        $response->assertSee('No FAQs available');
    }

    public function test_unauthorized_faq_mutation_is_impossible_from_customer_routes()
    {
        // There are no post/patch routes for FAQs on the customer side.
        // Even attempting to hit admin routes should fail.
        $user = \App\Models\User::factory()->create(); // non-admin
        $category = FaqCategory::factory()->create();
        
        $response = $this->actingAs($user)->postJson(route('admin.faq-categories.store'), [
            'name' => 'Hacked Category',
        ]);

        // Admins can do this, but regular users will get 403 Forbidden
        $response->assertStatus(403);
    }
}
