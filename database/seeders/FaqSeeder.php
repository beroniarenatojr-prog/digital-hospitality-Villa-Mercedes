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
        $checkOutLabel = $this->formatClock($checkOut);

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
                    'answer' => 'Yes. Pets cost ₱500 once per booking, including multiple services. Diapers and a cage or leash are required; pets cannot enter swimming pools.',
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
                    'answer' => sprintf(
                        'You can book a room up to 6 months in advance through our online reservation system. An account can hold up to %d reservations waiting for approval at once — each request takes its space off the market while we review it, and we email you the answer. Submit the initial 50%% deposit upon booking, which staff verify to confirm your reservation, and the booking is released if that passes unpaid. For a group needing more, call the resort.',
                        (int) config('resort.policy.max_pending_holds', 5)
                    ),
                ],
                [
                    'question' => 'Can I change or reschedule my booking?',
                    'answer' => 'For new bookings, rescheduling has no notice deadline until staff record physical check-in. Staff check the service, date, availability, and price difference before confirming. Before physical check-in, including no-show, verified payments qualify for a full refund: staff receive the request and notify the owner; the owner approves refunds; administrators record that approval and the funds returned. Request, approval, and returned funds are separate stages. Existing bookings retain their accepted policy and saved prices.',
                ],
                [
                    'question' => 'Can I book a room for same-day use?',
                    'answer' => 'Yes. Select check in and check out on the same date, such as 9 AM–5 PM. One full room base rate applies without automatic early or late fees. Overnight stays remain 2 PM–noon. Online overnight early requests are up to two hours at ₱200 per room hour; earlier check ins require staff. Less than 20 minutes early is free, 20–60 minutes is one hour, then each further started hour. Approved late checkout is ₱300 per room hour after a fresh conflict check. Rooms remain unavailable after check out until staff mark cleaning complete.',
                ],
                [
                    'question' => 'How are children and pets charged for rooms and packages?',
                    'answer' => 'Children under one year are free and excluded from capacity for rooms and packages. Declare older children within guest count; the owner decides their charge after the base deposit. Revised totals are shown and disputed charges are held for staff discussion. Pets cost ₱500 once per booking, including multiple services. Diapers and a cage or leash are required; pets cannot enter swimming pools.',
                ],
                [
                    'question' => 'Can I undo a withdrawal request?',
                    'answer' => 'No — once you ask to withdraw, only staff can undo it. If you changed your mind, call the resort while the reservation is still pending so they can keep it.',
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
                    'question' => 'How much do I pay to reserve?',
                    'answer' => 'At least 50% down payment is required; 100% online payment is allowed. Proof stays under review until verified. Verified payment and remaining balance are shown separately; the balance is due at physical check-in. Multiple services share one booking and one deposit calculation. Overpayments remain visible for owner decision.',
                ],
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
