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
    'messenger_url' => env('RESORT_MESSENGER_URL', 'https://m.me/VillaMercedesNatureFarmResort'),
    'instagram_url' => 'https://www.instagram.com/villamercedesresort/',
    'instagram_label' => 'Villa Mercedes on Instagram',

    /*
    | Seeded guest reviews and other demonstration copy. Keep this false in a
    | public release so @villamercedes.test testimonials never appear as live reviews.
    */
    'demo_content' => (bool) env('RESORT_DEMO_CONTENT', env('APP_ENV') !== 'production'),

    'hours' => 'Open daily — visit our Facebook page for current hours and announcements.',
    'maps_query' => 'Villa Mercedes Nature Farm and Resort, Barangay Namnama, Ilagan, Isabela, Philippines',

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
        ['value' => '4', 'label' => 'Function Halls'],
        ['value' => 'Packages', 'label' => 'Day & Overnight'],
    ],

    'gallery' => [
        ['title' => 'Resort team', 'caption' => 'The people behind Villa Mercedes hospitality.'],
        ['title' => 'Night lodge', 'caption' => 'Warm lights over our wooden architecture.'],
        ['title' => 'Pool at night', 'caption' => 'Evening swimming under resort lighting.'],
        ['title' => 'Function hall', 'caption' => 'A venue for celebrations, meetings, and events.'],
        ['title' => 'Pool by day', 'caption' => 'Nature-framed swimming for all ages.'],
        ['title' => 'Guest wings', 'caption' => 'Rooms and cottages for overnight stays.'],
    ],
];
