<?php

return [
    'name' => 'Villa Mercedes Nature Farm & Resort',
    'short_name' => 'Villa Mercedes',
    'tagline' => 'Experience nature, comfort, and heartful hospitality.',
    'location' => 'Barangay Namnama, City of Ilagan, Isabela, Philippines',
    'phone' => '0917 326 8271',
    'phone_href' => env('RESORT_PHONE_HREF', 'tel:+639173268271'),
    'email' => env('RESORT_EMAIL', 'villamercedesresort@yahoo.com'),
    'facebook_url' => 'https://www.facebook.com/VillaMercedesNatureFarmResort',
    'facebook_label' => 'Villa Mercedes Nature Farm & Resort',
    'messenger_url' => env('RESORT_MESSENGER_URL', 'https://m.me/VillaMercedesNatureFarmResort'),
    'instagram_url' => 'https://www.instagram.com/villamercedesresort/',
    'instagram_label' => 'Villa Mercedes on Instagram',

    /*
    |--------------------------------------------------------------------------
    | Resort clock
    |--------------------------------------------------------------------------
    |
    | The resort runs on Philippine time. Check-in, check-out, and payment
    | dates are the wall-clock numbers written on the guest sheet, and every
    | "today", "tonight", arrival list, and occupancy window is a resort
    | calendar day. Resolve those boundaries through App\Support\ResortTime
    | instead of a bare now(): instants are stored in config('app.timezone').
    |
    */
    'timezone' => env('RESORT_TIMEZONE', 'Asia/Manila'),

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
    | Confirmed future bookings use physical check-in as the change/refund
    | boundary. The cutoff values below remain only for historical accepted
    | booking contracts. New policy publication never rewrites stored prices
    | or accepted policy versions. Owner authority must be assigned explicitly.
    |
    */
    'policy' => [
        'version' => '2026-10-confirmed-v1',
        'confirmed_version' => '2026-10-confirmed-v1',
        'published_on' => '2026-10-12',
        'withdrawal_cutoff_hours' => 48,
        'reschedule_cutoff_hours' => 48,

        /*
        | How many reservations one account may hold while they are still waiting
        | for approval. A pending reservation takes its space off the market until
        | the deposit deadline, so without a ceiling one account could hold every
        | cottage and every room on the same dates. Staff-created bookings are not
        | counted: the desk books groups. Set 0 to remove the ceiling. Published in
        | the FAQ, so a change here edits an answer guests have read.
        */
        'max_pending_holds' => 5,
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
        'room_max_early_hours' => 2,
        'hall' => [
            'open' => '09:00',
            'close' => '22:00',
            'standard_hours' => 4,
            'extension_rates' => ['diamond-hall' => 5000, 'sapphire-hall' => 2500, 'emerald-hall' => 3000],
        ],
        'opal_slots' => [
            'day' => ['label' => 'Day slot', 'start' => '09:00', 'end' => '17:00'],
            'night' => ['label' => 'Night slot', 'start' => '17:00', 'end' => '22:00'],
        ],
        'max_additional_hours' => 12,
        'day_use_hours' => ['start' => '08:00', 'end' => '17:00'],
        'night_use_hours' => ['start' => '18:00', 'end' => '23:59'],
        'overnight_hours' => ['check_in' => '14:00', 'check_out' => '12:00'],
    ],

    /**
     * Finishing a booking from an email.
     *
     * A guest who reaches the confirm step and leaves is emailed a link back to
     * it. The link is only worth sending once they have actually walked away —
     * an email telling someone mid-checkout that they left is worse than no
     * email — so it waits `resume_delay_minutes` after their last arrival at the
     * step, and is dropped without a word if they finish inside that window.
     */
    'booking' => [
        'resume_delay_minutes' => (int) env('BOOKING_RESUME_DELAY_MINUTES', 20),
        'resume_expire_hours' => (int) env('BOOKING_RESUME_EXPIRE_HOURS', 48),
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
        ['value' => '4', 'label' => 'Swimming Pools'],
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
