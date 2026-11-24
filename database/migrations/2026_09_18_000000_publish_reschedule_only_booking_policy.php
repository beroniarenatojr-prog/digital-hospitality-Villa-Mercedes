<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * FaqSeeder keys its rows on the question text, and the seeder only runs
     * from DatabaseSeeder — so renaming the policy question would leave the old
     * row live on a deployed install, still promising guests a cancellation
     * window. This moves the published answer with the code.
     */
    private const PREVIOUS_QUESTION = 'Can I cancel or reschedule my booking?';

    private const QUESTION = 'Can I change or reschedule my booking?';

    public function up(): void
    {
        $rows = DB::table('faqs')->where('question', self::PREVIOUS_QUESTION)->orderBy('id')->pluck('id');

        if ($rows->isEmpty()) {
            return;
        }

        // The first row is rewritten in place, so the FAQ keeps the category and
        // position it was seeded into rather than reappearing at the bottom.
        DB::table('faqs')->where('id', $rows->first())->update([
            'question' => self::QUESTION,
            'answer' => $this->answer(),
            'is_active' => true,
            'updated_at' => now(),
        ]);

        // A duplicate left behind on the old question — by an earlier re-seed
        // into another category, say — is taken off the public site. Deactivated
        // rather than deleted, so staff can see what changed and rewrite it.
        DB::table('faqs')->whereIn('id', $rows->slice(1)->all())->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The cancellation promise is not restored. Rewrite the answer through
        // the back office if it ever comes back.
    }

    /**
     * Kept in step with FaqSeeder's reservations category, which is canonical
     * once it runs.
     */
    private function answer(): string
    {
        return sprintf(
            'Reschedule a pending or approved booking online until %d hours before check-in. We do not accept cancellations. If you no longer want a request that is still waiting for approval, withdraw it from My reservations — staff will confirm it, and a withdrawal stays open until %d hours before check-in. After the cutoff, call the resort.',
            (int) config('resort.policy.reschedule_cutoff_hours', 48),
            (int) config('resort.policy.withdrawal_cutoff_hours', 48)
        );
    }
};
