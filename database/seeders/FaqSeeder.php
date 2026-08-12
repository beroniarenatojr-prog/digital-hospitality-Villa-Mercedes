<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $reservationsCategory = FaqCategory::query()->where('slug', Str::slug('Reservations'))->first();
        $paymentsCategory = FaqCategory::query()->where('slug', Str::slug('Payments'))->first();

        if ($reservationsCategory) {
            $faqs = [
                [
                    'question' => 'How far in advance can I book a room?',
                    'answer' => 'You can book a room up to 6 months in advance through our online reservation system.',
                ],
                [
                    'question' => 'Can I cancel or reschedule my booking?',
                    'answer' => 'Yes, cancellations and rescheduling are allowed up to 48 hours before your check-in date without penalty.',
                ]
            ];

            foreach ($faqs as $index => $faq) {
                Faq::query()->firstOrCreate(
                    ['question' => $faq['question']],
                    array_merge($faq, [
                        'faq_category_id' => $reservationsCategory->id,
                        'sort_order' => $index,
                    ])
                );
            }
        }

        if ($paymentsCategory) {
            $faqs = [
                [
                    'question' => 'What payment methods do you accept?',
                    'answer' => 'We accept Bank Transfer, GCash, Maya, and Cash for walk-in guests.',
                ],
                [
                    'question' => 'How long does payment verification take?',
                    'answer' => 'Proof of payments are typically verified by our staff within 24 hours during business days.',
                ]
            ];

            foreach ($faqs as $index => $faq) {
                Faq::query()->firstOrCreate(
                    ['question' => $faq['question']],
                    array_merge($faq, [
                        'faq_category_id' => $paymentsCategory->id,
                        'sort_order' => $index,
                    ])
                );
            }
        }
    }
}
