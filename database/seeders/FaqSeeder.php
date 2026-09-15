<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Services\Customer\CustomerFaqService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $general = $this->category('General');
        $reservations = $this->category('Reservations');
        $payments = $this->category('Payments');
        $facilities = $this->category('Facilities');

        $checkIn = config('resort.stay.overnight_hours.check_in', '14:00');
        $checkOut = config('resort.stay.overnight_hours.check_out', '12:00');
        $checkInLabel = $this->formatClock($checkIn);
        $checkOutLabel = $this->formatClock($checkOut, noonAsNn: true);

        if ($general) {
            $this->upsertFaqs($general, [
                [
                    'question' => CustomerFaqService::HOME_FAQ_QUESTIONS[0],
                    'answer' => 'Villa Mercedes Nature Farm & Resort is at '.config('resort.location').'.',
                ],
                [
                    'question' => CustomerFaqService::HOME_FAQ_QUESTIONS[3],
                    'answer' => "Check-in is {$checkInLabel} and check-out is {$checkOutLabel}. Early check-in and late check-out may be arranged for a fee, subject to availability on the day of your stay.",
                ],
                [
                    'question' => CustomerFaqService::HOME_FAQ_QUESTIONS[4],
                    'answer' => 'Yes. Pets are welcome when kept on a leash or in a cage, wear pampers, and pay a ₱500 pet fee.',
                ],
            ]);
        }

        if ($facilities) {
            $this->upsertFaqs($facilities, [
                [
                    'question' => CustomerFaqService::HOME_FAQ_QUESTIONS[1],
                    'answer' => 'Day swimming entrance is ₱100 for adults and ₱70 for children below 4 feet. Night swimming is ₱120 per swimmer. Cottage rental is separate (₱500 per session). Entrance is paid at the resort.',
                ],
                [
                    'question' => CustomerFaqService::HOME_FAQ_QUESTIONS[2],
                    'answer' => 'Day swimming is 9:00 AM–5:00 PM. Night swimming is 5:00 PM–10:00 PM. Children are not allowed to swim during the night session.',
                ],
                [
                    'question' => 'What are the public swimming hours and entrance fees?',
                    'answer' => 'Day swimming is 9:00 AM–5:00 PM: adults ₱100 and children below 4 feet ₱70. Night swimming is 5:00 PM–10:00 PM at ₱120 per swimmer. Children are not allowed to swim during the night session. Entrance is paid at the resort.',
                ],
            ]);
        }

        if ($reservations) {
            $this->upsertFaqs($reservations, [
                [
                    'question' => 'How far in advance can I book a room?',
                    'answer' => 'You can book a room up to 6 months in advance through our online reservation system.',
                ],
                [
                    'question' => 'Can I cancel or reschedule my booking?',
                    'answer' => sprintf(
                        'You can reschedule a pending or approved booking online until %d hours before check-in. After the resort approves a booking, you can cancel it online until %d hours before check-in. To withdraw a request that is still waiting for approval, submit a withdrawal from My reservations — staff will confirm it. After the cutoff, call the resort.',
                        (int) config('resort.policy.reschedule_cutoff_hours', 48),
                        (int) config('resort.policy.cancellation_cutoff_hours', 48)
                    ),
                ],
                [
                    'question' => 'Can I undo a withdrawal request?',
                    'answer' => 'No. Once you ask to withdraw a pending reservation, the request stays until staff confirm it. If you changed your mind, call the resort so they can keep the reservation while it is still pending.',
                ],
                [
                    'question' => 'How do I reserve a cottage for public swimming?',
                    'answer' => 'Open Resort & Pools, choose a date and day or night swimming session, then select an available numbered cottage. Sign in or create an account to continue with your saved selection. A cottage costs ₱500 per session. Pool entrance is paid separately when you arrive.',
                ],
            ]);
        }

        if ($payments) {
            $this->upsertFaqs($payments, [
                [
                    'question' => 'What payment methods do you accept?',
                    'answer' => 'We accept Bank Transfer, GCash, Maya, and Cash for walk-in guests.',
                ],
                [
                    'question' => 'How long does payment verification take?',
                    'answer' => 'Proof of payments are typically verified by our staff within 24 hours during business days.',
                ],
            ], firstOrCreate: false);
        }
    }

    private function category(string $name): ?FaqCategory
    {
        return FaqCategory::query()->where('slug', Str::slug($name))->first();
    }

    /**
     * @param  list<array{question: string, answer: string}>  $faqs
     */
    private function upsertFaqs(FaqCategory $category, array $faqs, bool $firstOrCreate = false): void
    {
        foreach ($faqs as $index => $faq) {
            $attributes = array_merge($faq, [
                'faq_category_id' => $category->id,
                'is_active' => true,
                'sort_order' => $index,
            ]);

            if ($firstOrCreate) {
                Faq::query()->firstOrCreate(
                    ['question' => $faq['question']],
                    $attributes
                );

                continue;
            }

            Faq::query()->updateOrCreate(
                ['question' => $faq['question']],
                $attributes
            );
        }
    }

    private function formatClock(string $time, bool $noonAsNn = false): string
    {
        $carbon = \Illuminate\Support\Carbon::createFromFormat('H:i', $time);

        if ($noonAsNn && $carbon->format('H:i') === '12:00') {
            return '12:00 NN';
        }

        return $carbon->format('g:i A');
    }
}
