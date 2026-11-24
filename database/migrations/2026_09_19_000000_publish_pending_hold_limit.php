<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * FaqSeeder keys its rows on the question text and only runs from
     * DatabaseSeeder, so an answer changed in the seeder alone never reaches a
     * deployed install — the reserved FAQ would keep promising that a guest may
     * book as many times as they like while the service refuses the sixth
     * request. This publishes the ceiling with the code that enforces it.
     *
     * The question text is unchanged, so this rewrites the answer in place and
     * the FAQ keeps its category and position.
     */
    private const QUESTION = 'How far in advance can I book a room?';

    public function up(): void
    {
        $rows = DB::table('faqs')->where('question', self::QUESTION)->orderBy('id')->pluck('id');

        if ($rows->isEmpty()) {
            return;
        }

        // Every row on this question is the same published answer, so they are
        // all rewritten rather than one being left on the old promise.
        DB::table('faqs')->whereIn('id', $rows->all())->update([
            'answer' => $this->answer(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The ceiling is a rule, not a copy edit: it is not restored by rolling
        // back the sentence that states it. Change the policy in config first.
    }

    /**
     * Kept in step with FaqSeeder's reservations category, which is canonical
     * once it runs.
     */
    private function answer(): string
    {
        return sprintf(
            'You can book a room up to 6 months in advance through our online reservation system. An account can hold up to %d reservations waiting for approval at once — each one takes its space off the market until the deposit deadline, and is released if that passes unpaid. For a group needing more, call the resort.',
            (int) config('resort.policy.max_pending_holds', 5)
        );
    }
};
