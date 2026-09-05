<?php

return [
    'name' => 'Villa Mercedes Nature Farm & Resort',
    'short_name' => 'Villa Mercedes',
    'tagline' => 'Experience nature, comfort, and heartful hospitality.',
    'location' => 'Barangay Namnama, City of Ilagan, Isabela, Philippines',
    'phone' => '0917 326 8271',
    'phone_href' => 'tel:+639173268271',
    'email' => 'villamercedesresort@yahoo.com',
    'facebook_url' => 'https://www.facebook.com/VillaMercedesNatureFarmResort',
    'facebook_label' => 'Villa Mercedes Nature Farm & Resort',
    'hours' => 'Open daily — visit our Facebook page for current hours and announcements.',

    /*
    |--------------------------------------------------------------------------
    | Booking policy
    |--------------------------------------------------------------------------
    |
    | The FAQ promises guests a free cancel/reschedule window. These values are
    | the single source of truth for that promise so the published policy and
    | the enforced policy cannot drift apart.
    |
    */
    'policy' => [
        'cancellation_cutoff_hours' => 48,
        'reschedule_cutoff_hours' => 48,
    ],

    /*
    |--------------------------------------------------------------------------
    | Occupancy
    |--------------------------------------------------------------------------
    |
    | A cottage held until 9PM is occupied until 9PM, not until check-out time.
    | 'max_additional_hours' bounds how far an occupancy window can stretch
    | beyond the booked period, which also bounds the conflict search window.
    |
    */
    'stay' => [
        'max_additional_hours' => 12,
        'day_use_hours' => ['start' => '08:00', 'end' => '17:00'],
        'night_use_hours' => ['start' => '18:00', 'end' => '23:59'],
        'overnight_hours' => ['check_in' => '14:00', 'check_out' => '12:00'],
    ],

    'mission' => 'To provide a heartful hospitality service that will engrave a unique experience in the heart and mind of every guest.',
    'vision' => 'To be the guests’ preferred destination of choice — a place they return to and remember.',

    'values' => [
        ['letter' => 'C', 'title' => 'Compassion', 'description' => 'Being kind and caring to everyone we encounter.'],
        ['letter' => 'A', 'title' => 'Accountability', 'description' => 'Being responsible for our words, actions, and results.'],
        ['letter' => 'R', 'title' => 'Respect', 'description' => 'Valuing everyone and treating people with dignity and professionalism.'],
        ['letter' => 'E', 'title' => 'Ethics', 'description' => 'Doing the right thing in an honest, fair, and responsible way.'],
    ],

    'stats' => [
        ['value' => '3', 'label' => 'Swimming Pools'],
        ['value' => 'Hotel & Cottages', 'label' => 'Stay Options'],
        ['value' => 'Cafe & Restobar', 'label' => 'On-site Dining'],
        ['value' => 'TESDA NC II', 'label' => 'Training Programs'],
    ],

    'training' => [
        'intro' => 'Villa Mercedes is an Enterprise-Based Education and Training (EBET) provider. We welcome guests who want to stay — and students who want to build hospitality skills.',
        'note' => 'Scholarship details, schedules, and slot availability change with TESDA offerings. Confirm the latest requirements on our Facebook page or by calling us.',
        'programs' => [
            [
                'title' => 'Housekeeping NC II',
                'duration' => 'About 58 days',
                'description' => 'Hands-on training in lodging operations — preparing rooms, maintaining cleanliness, and delivering guest-ready service in a real resort setting.',
                'highlights' => [
                    'Learn in a live hotel and resort environment',
                    'Practice guest-room and public-area standards',
                    'Build skills for hospitality employment',
                ],
            ],
            [
                'title' => 'Barista NC II',
                'duration' => 'About 23 days',
                'description' => 'Coffee and beverage service training connected to our cafe and restobar — from drink preparation to guest-facing service.',
                'highlights' => [
                    'Train where guests actually dine and unwind',
                    'Develop barista and customer-service skills',
                    'Prepare for cafe, hotel, and restaurant work',
                ],
            ],
        ],
        'benefits' => [
            'TESDA-registered enterprise-based training',
            'Scholarship packages may include free training and assessment',
            'Meals, transportation, and a daily living allowance may apply when a scholarship is available',
        ],
        'requirements' => [
            'Valid government-issued ID',
            'PSA birth certificate',
            'TESDA scholarship or program eligibility as announced',
            'Willingness to train on-site at the resort',
        ],
    ],

    'dining' => [
        'headline' => 'Cafe & Restobar',
        'description' => 'Dine in a glass-walled space with warm lighting, wood interiors, and a relaxed restobar atmosphere — part of the stay, not just an extra stop.',
        'highlights' => [
            'Cafe service for guests and visitors',
            'Restobar setting for gatherings and evenings out',
            'Ideal after a swim or before an event in the function hall',
        ],
    ],

    'gallery' => [
        ['title' => 'Resort team', 'caption' => 'The people behind Villa Mercedes hospitality.'],
        ['title' => 'Night lodge', 'caption' => 'Warm lights over our wooden architecture.'],
        ['title' => 'Cafe & restobar', 'caption' => 'Glass-walled dining with a restobar mood.'],
        ['title' => 'Pool at night', 'caption' => 'Evening swimming under resort lighting.'],
        ['title' => 'Function hall', 'caption' => 'A venue for celebrations, meetings, and trainings.'],
        ['title' => 'Pool by day', 'caption' => 'Nature-framed swimming for all ages.'],
        ['title' => 'Guest wings', 'caption' => 'Rooms and cottages for overnight stays.'],
        ['title' => 'TESDA training', 'caption' => 'Housekeeping and barista programs on site.'],
    ],
];
