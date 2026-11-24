<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The published answer to "How far in advance can I book a room?" described
     * the old order of operations: a request took its space off the market until
     * the deposit deadline, which read as "pay to be confirmed". The resort now
     * reviews a request first and opens the 50% deposit on approval, and the FAQ
     * has to say the same thing the guest portal does. FaqSeeder keys its rows on
     * the question text and only runs from DatabaseSeeder, so an answer changed
     * there alone never reaches a deployed install — hence this migration.
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

        DB::table('faqs')->whereIn('id', $rows->all())->update([
            'answer' => $this->answer(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The order of operations is a rule, not a copy edit: rolling back the
        // sentence would only make the published policy lie again.
    }

    /**
     * Kept in step with FaqSeeder's reservations category, which is canonical
     * once it runs.
     */
    private function answer(): string
    {
        return sprintf(
            'You can book a room up to 6 months in advance through our online reservation system. An account can hold up to %d reservations waiting for approval at once — each request takes its space off the market while we review it, and we email you the answer. Once we approve it, the 50%% deposit opens with its own deadline, and the booking is released if that passes unpaid. For a group needing more, call the resort.',
            (int) config('resort.policy.max_pending_holds', 5)
        );
    }
};
