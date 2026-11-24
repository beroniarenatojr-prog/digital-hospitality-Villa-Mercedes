<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->timestamp('cleaning_required_at')->nullable();
            $table->timestamp('cleaning_completed_at')->nullable();
        });
        foreach (['reservations', 'reservation_groups'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->json('booking_workflow')->nullable();
            });
        }
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $this->publishFaqs();
    }

    public function publishFaqs(): void
    {
        // Publish only known policy FAQ rows; never rewrite accepted booking terms or saved amounts.
        DB::table('faqs')->whereIn('question', ['Can I change or reschedule my booking?', 'Can I cancel or reschedule my booking?'])->update([
            'answer' => 'For future bookings: rescheduling has no notice deadline before physical check-in. Staff check the new service, dates, availability and price difference; replacements require guest acceptance. Before physical check-in, including no-show, verified payments qualify for full refund. Staff receive the request and notify the owner. The owner approves refunds; administrators record that approval and the funds returned. Request, approval and money returned are separate stages. Historical bookings retain their accepted terms and prices.',
            'updated_at' => now(),
        ]);
        $answers = [
            'What are the check-in and check-out times?' => ['general', 'Standard overnight rooms are 2 PM–noon. Same-day rooms use selected times and one full base rate with no automatic early or late fees. Online overnight early requests are up to two hours at ₱200 per room hour; earlier arrangements require staff. Less than 20 minutes early is free; 20–60 minutes is one hour, then each further started hour. Staff-approved late checkout is ₱300 per room hour after a fresh conflict check. Rooms await staff cleaning release after departure. Other services retain their own schedules.'],
            'Is the resort pet-friendly?' => ['general', 'Yes. Pets cost ₱500 once per booking, including multiple services. Diapers and a cage or leash are required; pets cannot enter swimming pools.'],
            'Can I book a room for same-day use?' => ['reservations', 'Yes. Select arrival and departure on the same date, such as 9 AM–5 PM. One full stored room base rate applies without automatic early or late fees, subject to fresh availability checks at submission and approval. Rooms remain unavailable after departure until staff mark cleaning complete.'],
            'How are children and pets charged for rooms and packages?' => ['reservations', 'Children under one year are free and excluded from capacity for rooms and packages. Declare older children within guest count; the owner decides their charge after the base deposit. Revised totals are shown and disputed charges are held for staff discussion, not automatic cancellation. Pets cost ₱500 once per booking. Diapers and a cage or leash are required; pets cannot enter swimming pools.'],
            'How much do I pay to reserve?' => ['payments', 'At least 50% down payment is required; 100% online payment is allowed. Proof stays under review until verified. Verified payment and remaining balance are shown separately; the balance is due at physical check-in. Multiple services share one booking and one deposit calculation. Overpayments remain visible for owner decision.'],
        ];
        foreach ($answers as $question => [$categorySlug, $answer]) {
            $existing = DB::table('faqs')->where('question', $question);
            if ($existing->exists()) {
                $existing->update(['answer' => $answer, 'updated_at' => now()]);
            } elseif ($categoryId = DB::table('faq_categories')->where('slug', $categorySlug)->value('id')) {
                DB::table('faqs')->insert(['question' => $question, 'answer' => $answer, 'faq_category_id' => $categoryId,
                    'is_active' => true, 'sort_order' => 100, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        foreach (['reservations', 'reservation_groups'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('booking_workflow'));
        }
        Schema::table('facilities', fn (Blueprint $table) => $table->dropColumn(['cleaning_required_at', 'cleaning_completed_at']));
    }
};
