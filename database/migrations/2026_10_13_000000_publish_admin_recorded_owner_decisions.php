<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update only the known published policy FAQ; preserve all accepted booking contracts.
        DB::table('faqs')->whereIn('question', ['Can I change or reschedule my booking?', 'Can I cancel or reschedule my booking?'])
            ->orderBy('id')->get()->each(function ($faq) {
                $answer = str_replace([
                    'Only the owner approves and records returned funds',
                    'only the owner approves and records returned funds',
                    'only the owner approves and records funds returned',
                ], 'The owner approves refunds; administrators record that approval and the funds returned', $faq->answer);
                DB::table('faqs')->where('id', $faq->id)->update(['answer' => $answer, 'updated_at' => now()]);
            });
    }

    public function down(): void
    {
        // Publication corrections must not restore obsolete guest-facing policy claims.
    }
};
