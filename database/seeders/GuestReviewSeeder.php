<?php

namespace Database\Seeders;

use App\Models\GuestReview;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Seeder;

class GuestReviewSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->reviews() as $review) {
            $user = User::query()->where('email', $review['email'])->first();

            if ($user === null) {
                continue;
            }

            $reservation = Reservation::query()
                ->where('user_id', $user->id)
                ->when(
                    $review['facility'] ?? null,
                    fn ($query, $facility) => $query->whereHas('facility', fn ($facilityQuery) => $facilityQuery->where('name', $facility))
                )
                ->where(function ($query) {
                    $query->whereNotNull('completed_at')
                        ->orWhereNotNull('checked_out_at')
                        ->orWhere('check_in', '<=', now());
                })
                ->orderByDesc('check_in')
                ->first();

            $publish = (bool) config('resort.demo_content');

            GuestReview::query()->updateOrCreate(
                ['user_id' => $user->id, 'body' => $review['body']],
                [
                    'reservation_id' => $reservation?->id,
                    'rating' => $review['rating'],
                    'stay_label' => $review['stay_label'],
                    'is_published' => $publish,
                    'featured_on_home' => $publish && $review['featured'],
                    'published_at' => $publish
                        ? ($reservation?->checked_out_at ?? $reservation?->completed_at ?? now()->subWeeks($review['weeks_ago']))
                        : null,
                ]
            );
        }
    }

    /**
     * @return list<array{email: string, facility: string|null, rating: int, body: string, stay_label: string, featured: bool, weeks_ago: int}>
     */
    private function reviews(): array
    {
        return [
            [
                'email' => 'isabel.mendoza@villamercedes.test',
                'facility' => 'Standard Family (Room 21)',
                'rating' => 5,
                'body' => 'Booked the family room for Ramon and the kids. The room was ready when we arrived, and they barely left the pool until checkout.',
                'stay_label' => 'Family stay',
                'featured' => true,
                'weeks_ago' => 2,
            ],
            [
                'email' => 'rafael.sy@villamercedes.test',
                'facility' => 'Standard Family (Room 22)',
                'rating' => 5,
                'body' => 'Our family room stay was quiet at night and easy during the day. Staff remembered the children’s names by the second morning.',
                'stay_label' => 'Family stay',
                'featured' => true,
                'weeks_ago' => 3,
            ],
            [
                'email' => 'queenie.lim@villamercedes.test',
                'facility' => 'Opal Hall',
                'rating' => 5,
                'body' => 'We used Opal Hall for the Lim Logistics seminar. The booking summary showed every peso before we paid, which made the company reimbursement simple.',
                'stay_label' => 'Company seminar',
                'featured' => true,
                'weeks_ago' => 8,
            ],
            [
                'email' => 'carla.delacruz@villamercedes.test',
                'facility' => 'Superior (Room 3)',
                'rating' => 5,
                'body' => 'Superior Room 3 was clean, and the early check-in we requested actually happened. Paolo and I will book the same room next time.',
                'stay_label' => 'Overnight stay',
                'featured' => false,
                'weeks_ago' => 1,
            ],
            [
                'email' => 'diego.ramos@villamercedes.test',
                'facility' => 'Deluxe (Room 26)',
                'rating' => 4,
                'body' => 'Deluxe Room 26 had the space we needed. Checking dates online before we drove up from town saved us a wasted trip.',
                'stay_label' => 'Overnight stay',
                'featured' => false,
                'weeks_ago' => 0,
            ],
            [
                'email' => 'ana.reyes@villamercedes.test',
                'facility' => 'Superior (Room 01)',
                'rating' => 5,
                'body' => 'We asked for extra pillows and a late arrival window. Both were waiting when Marco and I checked in.',
                'stay_label' => 'Couple stay',
                'featured' => false,
                'weeks_ago' => 1,
            ],
        ];
    }
}
