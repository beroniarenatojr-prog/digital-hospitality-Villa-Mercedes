<?php

namespace Tests\Feature\Customer;

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerFaqTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_booking_policy_faq_uses_physical_check_in_and_owner_refund_stages(): void
    {
        $this->seed();

        $this->get(route('faq.index'))
            ->assertOk()
            ->assertSee('Can I change or reschedule my booking?')
            ->assertSee('rescheduling has no notice deadline')
            ->assertSee('physical check-in')
            ->assertSee('the owner approves refunds; administrators record that approval and the funds returned')
            ->assertSee('including no-show')
            ->assertDontSee('We do not accept cancellations.')
            ->assertDontSee('Can I cancel or reschedule my booking?')
            ->assertSee('Can I undo a withdrawal request?')
            ->assertSee('only staff can undo it');
    }

    /**
     * The seeder only runs from DatabaseSeeder, so a deployed install would keep
     * the old promise until this migration is applied. It rewrites the row the
     * seeder keys on and retires any duplicate left on the old question.
     */
    public function test_the_policy_migration_rewrites_a_deployed_faq(): void
    {
        $category = FaqCategory::factory()->create(['is_active' => true]);

        $published = Faq::factory()->create([
            'faq_category_id' => $category->id,
            'question' => 'Can I cancel or reschedule my booking?',
            'answer' => 'After the resort approves a booking, you can cancel it online until 48 hours before check-in.',
            'is_active' => true,
        ]);

        $duplicate = Faq::factory()->create([
            'faq_category_id' => $category->id,
            'question' => 'Can I cancel or reschedule my booking?',
            'answer' => 'You can cancel it online until 48 hours before check-in.',
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_09_18_000000_publish_reschedule_only_booking_policy.php');
        $migration->up();

        $published->refresh();
        $this->assertSame('Can I change or reschedule my booking?', $published->question);
        $this->assertStringContainsString('We do not accept cancellations.', $published->answer);
        $this->assertTrue($published->is_active);

        $this->assertFalse($duplicate->refresh()->is_active, 'A duplicate left on the old question must come off the public page.');

        $this->get(route('faq.index'))
            ->assertOk()
            ->assertSee('Can I change or reschedule my booking?')
            ->assertDontSee('Can I cancel or reschedule my booking?');
    }

    /**
     * The hold ceiling is enforced by the service, so the sentence that states it
     * has to be published — a guest refused their sixth request with no rule
     * anywhere is a guest who calls to argue.
     */
    public function test_seeded_faq_publishes_the_hold_ceiling(): void
    {
        $this->seed();

        $limit = (int) config('resort.policy.max_pending_holds', 5);

        $this->get(route('faq.index'))
            ->assertOk()
            ->assertSee('An account can hold up to '.$limit.' reservations waiting for approval at once')
            ->assertSee('released if that passes unpaid');
    }

    /**
     * Same trap as the policy migration: the seeder only runs from
     * DatabaseSeeder, so a deployed install would keep the old answer beside a
     * service that refuses the booking.
     */
    public function test_the_hold_ceiling_migration_rewrites_a_deployed_faq(): void
    {
        $category = FaqCategory::factory()->create(['is_active' => true]);

        $published = Faq::factory()->create([
            'faq_category_id' => $category->id,
            'question' => 'How far in advance can I book a room?',
            'answer' => 'You can book a room up to 6 months in advance through our online reservation system.',
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_09_19_000000_publish_pending_hold_limit.php');
        $migration->up();

        $published->refresh();
        $this->assertStringContainsString('waiting for approval at once', $published->answer);
        $this->assertTrue($published->is_active);

        $this->get(route('faq.index'))->assertOk()->assertSee('waiting for approval at once');
    }

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
        $response->assertSee('faq-answer-'.$faq->id, false);
        $response->assertSee('x-data="{ active: '.$faq->id.' }"', false);
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

    public function test_seeded_faqs_are_grouped_by_category(): void
    {
        $this->seed();

        $this->get(route('faq.index'))
            ->assertOk()
            ->assertSee('All')
            ->assertSee('General')
            ->assertSee('Facilities')
            ->assertSee('Reservations')
            ->assertSee('Payments')
            ->assertSeeInOrder([
                'Where is Villa Mercedes located?',
                'What is the entrance fee?',
                'How far in advance can I book a room?',
                'What payment methods do you accept?',
            ]);
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
        $response->assertSee('Category One');
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
        $user = User::factory()->create(); // non-admin
        $category = FaqCategory::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.faq-categories.store'), [
            'name' => 'Hacked Category',
        ]);

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest();
        $this->assertDatabaseMissing('faq_categories', ['name' => 'Hacked Category']);
    }
}
