<?php

namespace Tests\Feature\Http;

use App\Enums\FacilityStatus;
use App\Enums\FacilityType as FacilityTypeEnum;
use App\Enums\RateType;
use App\Enums\Role;
use App\Models\Amenity;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\GuestReview;
use App\Models\PackageComponent;
use App\Models\Rate;
use App\Models\User;
use App\Support\PublicHomeCache;
use Database\Seeders\FacilityTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicBrowsingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 08:00:00'));
        $this->seed(FacilityTypeSeeder::class);
    }

    private function createFacility(string $typeSlug, bool $isActive = true, string $status = 'available'): Facility
    {
        $type = FacilityType::where('slug', $typeSlug)->first();

        return Facility::factory()->create([
            'facility_type_id' => $type->id,
            'is_active' => $isActive,
            'status' => $status,
            'sort_order' => 1,
        ]);
    }

    private function createBaseRate(Facility $facility): void
    {
        Rate::factory()->create([
            'facility_id' => $facility->id,
            'type' => RateType::Base,
            'is_active' => true,
            'amount' => 1000.00,
        ]);
    }

    public function test_home_page_responds_successfully()
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertViewIs('pages.home');
        $response->assertSee('Villa Mercedes Nature Farm & Resort');
        $response->assertSee('images/hero-aerial.jpg', false);
        $response->assertSee('images/mission-entrance.jpg', false);
        $response->assertSee('Stay, celebrate, and unwind');
        $response->assertSee('Explore the Resort');
        $response->assertSee('Swimming Pools');
        $response->assertSee('4');
        $response->assertSee('Check Availability');
        $response->assertDontSee('Browse Availability');
        $response->assertDontSee('placeholder="2"', false);
        $response->assertSee('Date In');
        $response->assertSee('Date Out');
        // The training module was removed from the product, so the hero must not
        // keep selling it: `/training` is asserted to 404 further down this file.
        $response->assertDontSee('TESDA');
        $response->assertSee('Function halls');
        $response->assertSee('Packages');
        $response->assertSee('Resort pools');
        $response->assertSee('Cottages');
        $response->assertSee(route('rooms'), false);
        $response->assertSee(route('halls'), false);
        $response->assertSee(route('packages'), false);
        $response->assertSee(route('login'), false);
        $response->assertSee(route('register'), false);
        $response->assertSee('value="cottage"', false);
        $response->assertSee('value="pool"', false);
        $response->assertDontSee('/availability/search', false);
        $response->assertDontSee('Availability for your dates');
        $response->assertSee('Create an account or sign in. Your dates and guest count stay with you.');
        // The package card describes the bundle rather than advertising it. The
        // earlier "I want a package when I need rooms and grounds together" copy
        // was written in the guest's voice on a page that is not theirs.
        $response->assertSee('Day-use, night-use, and overnight bundles for rooms and grounds.');
        $response->assertSee('images/rooms &amp; cottage.jpg', false);
        $response->assertSee('Wooden guest cottages at Villa Mercedes', false);
        $response->assertSee('images/event-hosting.jpg', false);
        $response->assertSee('Banquet hall at Villa Mercedes', false);
        $response->assertSee('images/resort pakage.jpg', false);
        $response->assertSee('Pool and grounds at Villa Mercedes', false);
        $response->assertSee('Our hours');
        $response->assertSee('How to reserve your stay');
        $response->assertSee('Mission');
        $response->assertSee('Vision');
        $response->assertSee('Compassion');
        $response->assertSee('Accountability');
        $response->assertSee('Respect');
        $response->assertSee('Ethics');
        $response->assertSee('Experience our world');
        $response->assertSee('A family gathering place in Ilagan that grew into a nature farm and resort');
        $response->assertSeeInOrder(['How to reserve your stay', 'Mission', 'Vision', 'Experience our world']);
        $response->assertDontSee('About Villa Mercedes');
        $response->assertDontSee('Read our full story');
        $response->assertDontSee('View all rooms');
        $response->assertDontSee('Booking takes 3 steps');
        $response->assertDontSee('Cafe & Restobar');
    }

    public function test_rooms_listing_shows_availability_for_stay_query(): void
    {
        $room = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $room->update(['capacity' => 6, 'max_capacity' => 6]);
        $this->createBaseRate($room);

        $this->get(route('rooms', [
            'check_in' => '2026-10-12',
            'check_out' => '2026-10-13',
            'guests' => 3,
        ]))
            ->assertOk()
            ->assertSee('Checking')
            ->assertSee('Oct 12')
            ->assertSee('3 guests')
            ->assertSee($room->name)
            ->assertSee('View details')
            ->assertSee('id="availability-space"', false)
            ->assertSee('Date In')
            ->assertSee('Update dates');
    }

    public function test_room_listing_card_opens_detail_before_availability(): void
    {
        $room = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $this->createBaseRate($room);

        $this->get(route('rooms'))
            ->assertOk()
            ->assertSee($room->name)
            ->assertSee('View details')
            ->assertSee(route('facilities.show', $room), false)
            ->assertDontSee(route('facilities.availability', $room), false);
    }

    public function test_rooms_listing_accepts_checkin_checkout_aliases(): void
    {
        $room = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $room->update(['capacity' => 6, 'max_capacity' => 6]);
        $this->createBaseRate($room);

        $this->get('/rooms?checkin=2026-10-12&checkout=2026-10-13&guests=4')
            ->assertOk()
            ->assertSee($room->name)
            ->assertSee('Available for your selected dates');
    }

    public function test_marketing_pages_respond_successfully(): void
    {
        $pages = [
            'about' => 'pages.about',
            'gallery' => 'pages.gallery',
            'contact' => 'pages.contact',
            'reviews' => 'pages.reviews',
        ];

        foreach ($pages as $route => $view) {
            $response = $this->get(route($route));

            $response->assertOk();
            $response->assertViewIs($view);

            if ($route === 'about') {
                $response
                    ->assertSeeInOrder(['Guest reviews', 'Frequently asked questions', 'Heartful hospitality'])
                    ->assertSee('id="reviews"', false)
                    ->assertSee('id="faqs"', false);
            }
        }

        $this->get('/dining')->assertNotFound();
        $this->get('/training')->assertNotFound();
        $this->get(route('contact'))->assertSee('villamercedesresort@yahoo.com');
        $this->get(route('contact'))
            ->assertSee('Message us on Facebook')
            ->assertSee(config('resort.messenger_url'), false)
            ->assertDontSee('Log in to send an inquiry');

        $this->get(route('reviews'))
            ->assertOk()
            ->assertSee('Customer Reviews')
            ->assertSee('Customer reviews will appear here after customers share a review from their account.')
            ->assertDontSee('1,200 guests')
            ->assertDontSee('Ana R.');
    }

    public function test_reserve_space_opens_the_rooms_listing(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Services')
            ->assertSee(route('rooms'), false)
            ->assertSeeInOrder(['Home', 'Services', 'Resort & Pools', 'Gallery', 'About'])
            ->assertDontSee('Overnight and day-use stays')
            ->assertDontSee('Bundled stays at one price');

        $this->get(route('reserve-space'))
            ->assertRedirect(route('rooms'));
    }

    public function test_public_pages_include_messenger_widget_without_virtual_assistant(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-support-chat', false)
            ->assertSee('aria-label="Open Messenger chat"', false)
            ->assertDontSee('data-message-endpoint', false)
            ->assertDontSee('data-chat-composer', false)
            ->assertDontSee('Virtual assistant')
            ->assertDontSee('Suggested topics')
            ->assertSee('Message us on Messenger')
            ->assertSee('href="'.config('resort.messenger_url').'"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('Opens Messenger in a new tab. Replies arrive there.')
            ->assertDontSee('connect.facebook.net', false);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('data-support-chat', false)
            ->assertSee('Message us on Messenger');
    }

    public function test_empty_messenger_url_omits_the_entire_widget(): void
    {
        config(['resort.messenger_url' => '']);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-support-chat', false)
            ->assertDontSee('aria-label="Open Messenger chat"', false)
            ->assertDontSee('Message us on Messenger');
    }

    public function test_rooms_listing_displays_active_rooms_only()
    {
        $activeRoom = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $this->createBaseRate($activeRoom);

        $inactiveRoom = $this->createFacility(FacilityTypeEnum::Room->value, false);

        $response = $this->get(route('rooms'));

        $response->assertStatus(200);
        $response->assertSee($activeRoom->name);
        $response->assertSee('images/resort pakage.jpg', false);
        $response->assertSee('listing-hero', false);
        $response->assertSee('Choose the space that fits your plans.');
        $response->assertSee('Room categories and individual units, four function halls, and curated packages — each listed with its photos, included guests, and extra-person charges.');
        $response->assertSee('Stay &middot; Celebrate &middot; Unwind', false);
        $response->assertSeeInOrder(['Rooms', 'Function Halls', 'Packages', 'Cottages', 'Pools']);
        $response->assertSee('Min. capacity');
        $response->assertSee('100+');
        $response->assertSee('Price: Low to High');
        $response->assertSee('Price: High to Low');
        $response->assertSee('Largest Capacity');
        $response->assertSee('Recommended');
        $response->assertDontSee($inactiveRoom->name);
        $response->assertDontSee('id="availability-space"', false);
        $response->assertDontSee('Date in');
    }

    public function test_halls_listing_displays_active_halls_only()
    {
        $activeHall = $this->createFacility(FacilityTypeEnum::FunctionHall->value, true);
        $this->createBaseRate($activeHall);

        $response = $this->get(route('halls'));

        $response->assertStatus(200);
        $response->assertSee($activeHall->name);
        $response->assertSee('images/resort pakage.jpg', false);
        $response->assertDontSee('id="availability-space"', false);
        $response->assertDontSee('Included with every stay');
        $response->assertDontSee('NO EXTRA CHARGE');
    }

    public function test_rooms_listing_shows_room_stay_inclusives(): void
    {
        $room = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $this->createBaseRate($room);

        $this->get(route('rooms'))
            ->assertOk()
            ->assertSee('NO EXTRA CHARGE')
            ->assertSee('Included with every stay')
            ->assertSee('The rate you see covers the whole resort, not just your room.')
            ->assertSee('Free WiFi')
            ->assertSee('Fibre, resort-wide')
            ->assertSee('Air Conditioning')
            ->assertSee('In every guest room')
            ->assertSee('Hot/Cold Shower')
            ->assertSee('In every bathroom')
            ->assertSee('Bath Towels')
            ->assertSee('Provided for your stay')
            ->assertSee('Coffee Cups')
            ->assertSee('Ready in-room')
            ->assertSee('Swimming Pool Access')
            ->assertSee('Three spring pools')
            ->assertDontSee('Bottled Water')
            ->assertDontSee('Included with every room')
            ->assertDontSee('Basic Toiletries')
            ->assertDontSee('Farm breakfast')
            ->assertDontSee('Nightly bonfire')
            ->assertDontSee('Exclusive villa use')
            ->assertDontSee('Use of grill');
    }

    public function test_packages_listing_shows_package_stay_inclusives(): void
    {
        $package = $this->createFacility(FacilityTypeEnum::ResortPackage->value, true);
        $this->createBaseRate($package);

        $this->get(route('packages'))
            ->assertOk()
            ->assertSee('NO EXTRA CHARGE')
            ->assertSee('Included with every stay')
            ->assertSee('The rate you see covers the whole resort, not just your room.')
            ->assertSee('Free Wi-Fi')
            ->assertSee('On every package')
            ->assertSee('Private pool')
            ->assertSee('Function hall')
            ->assertSee('Free parking')
            ->assertSee('Exclusive villa use')
            ->assertSee('Use of grill')
            ->assertDontSee('Farm breakfast')
            ->assertDontSee('Nightly bonfire')
            ->assertDontSee('Three spring pools');
    }

    public function test_packages_listing_displays_active_packages_only()
    {
        $activePackage = $this->createFacility(FacilityTypeEnum::ResortPackage->value, true);
        $this->createBaseRate($activePackage);

        $response = $this->get(route('packages'));

        $response->assertStatus(200);
        $response->assertSee($activePackage->name);
        $response->assertSee('images/resort pakage.jpg', false);
        $response->assertDontSee('id="availability-space"', false);
    }

    public function test_facility_detail_page_renders_correctly()
    {
        $room = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $this->createBaseRate($room);

        $amenity = Amenity::factory()->create(['name' => 'Free Wi-Fi']);
        $room->amenities()->attach($amenity->id);

        $response = $this->get(route('facilities.show', $room));

        $response->assertStatus(200);
        $response->assertSee($room->name);
        $response->assertSee($room->description);
        $response->assertSee('Free Wi-Fi');
        $response->assertSee('1,000.00'); // Check price formatting
        $response->assertSee('Add to booking');
    }

    public function test_facility_book_link_preserves_availability_query(): void
    {
        $room = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $this->createBaseRate($room);

        $this->get(route('facilities.show', [
            'facility' => $room,
            'check_in' => '2026-10-12',
            'check_out' => '2026-10-13',
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertSee(route('customer.booking.legacy', $room), false)
            ->assertSee('check_in=2026-10-12', false)
            ->assertSee('check_out=2026-10-13', false)
            ->assertSee('guests=2', false);
    }

    public function test_facility_detail_shows_multiple_photo_captions(): void
    {
        $room = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $this->createBaseRate($room);

        $room->galleryImages()->create([
            'image_path' => 'facilities/bed.jpg',
            'caption' => 'Queen bed facing the garden',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $room->galleryImages()->create([
            'image_path' => 'facilities/bath.jpg',
            'caption' => 'Private bathroom',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->get(route('facilities.show', $room))
            ->assertOk()
            ->assertSee('Queen bed facing the garden')
            ->assertSee('Private bathroom');

        $this->get(route('rooms'))
            ->assertOk()
            ->assertSee('2 photos');
    }

    public function test_rooms_listing_filters_by_minimum_capacity(): void
    {
        $small = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $small->update(['name' => 'Quiet Twin Unique', 'capacity' => 2, 'max_capacity' => 2]);
        $this->createBaseRate($small);

        $large = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $large->update(['name' => 'Family Lodge Unique', 'capacity' => 8, 'max_capacity' => 10]);
        $this->createBaseRate($large);

        $this->get(route('rooms', ['min_capacity' => 6]))
            ->assertOk()
            ->assertSee('Family Lodge Unique')
            ->assertDontSee('Quiet Twin Unique')
            ->assertSee('1 ROOM · 1 CATEGORY');
    }

    public function test_rooms_listing_groups_units_under_category_and_shows_bed_capacity_and_extra_person(): void
    {
        $superiorOne = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $superiorOne->update([
            'name' => 'Superior (Room 01)',
            'capacity' => 2,
            'max_capacity' => 2,
            'sort_order' => 1,
        ]);
        $this->createBaseRate($superiorOne);

        $superiorTwo = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $superiorTwo->update([
            'name' => 'Superior (Room 02)',
            'capacity' => 2,
            'max_capacity' => 2,
            'sort_order' => 2,
        ]);
        $this->createBaseRate($superiorTwo);

        $barkada = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $barkada->update([
            'name' => 'Superior Barkada (Room 10)',
            'capacity' => 6,
            'max_capacity' => 8,
            'sort_order' => 3,
        ]);
        Rate::factory()->create([
            'facility_id' => $barkada->id,
            'type' => RateType::Base,
            'is_active' => true,
            'amount' => 4000.00,
        ]);
        Rate::factory()->create([
            'facility_id' => $barkada->id,
            'type' => RateType::ExtraPerson,
            'is_active' => true,
            'amount' => 500.00,
        ]);

        $this->get(route('rooms'))
            ->assertOk()
            ->assertSee('3 ROOMS · 2 CATEGORIES')
            ->assertSee('id="room-category-0"', false)
            ->assertSeeInOrder([
                'Superior',
                '2 units in this category',
                'Room 01',
                'Room 02',
                'Superior Barkada',
                '1 unit in this category',
                'Room 10',
            ])
            ->assertDontSee('SUPERIOR · ROOM 01')
            ->assertSee('1 bed')
            ->assertSee('2 included')
            ->assertSee('Max 2')
            ->assertSee('6 beds')
            ->assertSee('6 included')
            ->assertSee('Max 8')
            // Read as text, because the symbol and the figure are one amount in two
            // elements: x-amount keeps the sign in its own span so callers can
            // restyle the number without restyling the currency.
            ->assertSeeText('Extra person ₱500.00');
    }

    public function test_halls_listing_does_not_group_venues_as_room_categories(): void
    {
        $hall = $this->createFacility(FacilityTypeEnum::FunctionHall->value, true);
        $hall->update(['name' => 'Diamond Hall Unique']);
        $this->createBaseRate($hall);

        $this->get(route('halls'))
            ->assertOk()
            ->assertSee('Diamond Hall Unique')
            ->assertDontSee('units in this category')
            ->assertDontSee('id="room-category-0"', false);
    }

    public function test_rooms_listing_sorts_by_price_ascending(): void
    {
        $pricey = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $pricey->update(['name' => 'Premium Suite Unique', 'sort_order' => 1]);
        Rate::factory()->create([
            'facility_id' => $pricey->id,
            'type' => RateType::Base,
            'is_active' => true,
            'amount' => 8000.00,
        ]);

        $value = $this->createFacility(FacilityTypeEnum::Room->value, true);
        $value->update(['name' => 'Garden Cabin Unique', 'sort_order' => 2]);
        Rate::factory()->create([
            'facility_id' => $value->id,
            'type' => RateType::Base,
            'is_active' => true,
            'amount' => 2500.00,
        ]);

        $this->get(route('rooms', ['sort' => 'price-asc']))
            ->assertOk()
            ->assertSeeInOrder(['Garden Cabin Unique', 'Premium Suite Unique']);
    }

    public function test_listing_tabs_preserve_stay_query(): void
    {
        $this->get(route('rooms', [
            'check_in' => '2026-10-12',
            'check_out' => '2026-10-13',
            'guests' => 3,
        ]))
            ->assertOk()
            ->assertSee('/halls?check_in=2026-10-12', false)
            ->assertSee('check_out=2026-10-13', false)
            ->assertSee('guests=3', false);
    }

    public function test_home_shows_faq_teaser_from_catalog(): void
    {
        $category = FaqCategory::factory()->create(['is_active' => true]);

        foreach ([
            'Where is Villa Mercedes located?' => 'Villa Mercedes Nature Farm & Resort is at Barangay Namnama, City of Ilagan, Isabela, Philippines.',
            'What is the entrance fee?' => 'Day swimming entrance is ₱100 for adults and ₱70 for children below 4 feet.',
            'What are the pool hours?' => 'Day swimming is 9:00 AM–5:00 PM. Night swimming is 5:00 PM–10:00 PM.',
            'What are the check-in and check-out times?' => 'Check-in is 2:00 PM and check-out is 12:00 PM.',
            'Is the resort pet-friendly?' => 'Yes. Pets are welcome when kept on a leash or in a cage, wear pampers, and pay a ₱500 pet fee.',
        ] as $question => $answer) {
            Faq::factory()->create([
                'faq_category_id' => $category->id,
                'is_active' => true,
                'question' => $question,
                'answer' => $answer,
            ]);
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('GOOD TO KNOW')
            ->assertSee('Questions guests ask')
            // This line must not promise a response time. "Answers messages within
            // the hour, seven days a week" is an operating commitment the resort
            // has not agreed to, and the front desk is the one held to it.
            ->assertSee('Contact the resort for help with your visit.')
            ->assertSee('Message the resort')
            ->assertSee('Where is Villa Mercedes located?')
            ->assertSee('What is the entrance fee?')
            ->assertSee('What are the pool hours?')
            ->assertSee('What are the check-in and check-out times?')
            ->assertSee('Is the resort pet-friendly?')
            ->assertSee('See all FAQs')
            ->assertDontSee('What time is check-in at the farm?');
    }

    public function test_inactive_facility_detail_returns_404()
    {
        $room = $this->createFacility(FacilityTypeEnum::Room->value, false);

        $response = $this->get(route('facilities.show', $room));

        $response->assertStatus(404);
    }

    public function test_package_detail_displays_included_resources()
    {
        $package = $this->createFacility(FacilityTypeEnum::ResortPackage->value, true);
        $this->createBaseRate($package);

        $hallComponent = $this->createFacility(FacilityTypeEnum::FunctionHall->value, true);

        PackageComponent::create([
            'package_facility_id' => $package->id,
            'component_facility_id' => $hallComponent->id,
        ]);

        $response = $this->get(route('facilities.show', $package));

        $response->assertStatus(200);
        $response->assertSee('Included Resources');
        $response->assertSee($hallComponent->name);
    }

    public function test_rooms_listing_eager_loads_facility_type(): void
    {
        $this->seed();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('rooms'))->assertOk();

        $facilityTypeQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains(strtolower($query['query']), 'facility_types'))
            ->count();

        $this->assertLessThanOrEqual(
            2,
            $facilityTypeQueries,
            'Rooms listing should not lazy-load facilityType per card.'
        );

        $lazyGalleryQueries = collect(DB::getQueryLog())
            ->filter(function (array $query): bool {
                $sql = strtolower($query['query']);

                return str_contains($sql, 'from `gallery_images`')
                    && ! str_contains($sql, 'from `facilities`');
            })
            ->count();

        $this->assertSame(
            0,
            $lazyGalleryQueries,
            'Rooms listing should not lazy-load gallery images per card.'
        );
    }

    public function test_home_page_does_not_lazy_load_facility_type_for_service_photos(): void
    {
        $this->seed();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('home'))->assertOk();

        $facilityTypeQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains(strtolower($query['query']), 'facility_types'))
            ->count();

        $this->assertLessThanOrEqual(
            6,
            $facilityTypeQueries,
            'Home should not lazy-load facilityType while resolving service photos.'
        );
    }

    public function test_home_defers_compact_featured_room_cards_to_a_single_request(): void
    {
        PublicHomeCache::forget();

        $featuredRoom = $this->createFacility(FacilityTypeEnum::Room->value, true, FacilityStatus::Available->value);
        $this->createBaseRate($featuredRoom);
        $featuredRoom->galleryImages()->create([
            'image_path' => 'facilities/featured-room.webp',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $maintenanceRoom = $this->createFacility(FacilityTypeEnum::Room->value, true, FacilityStatus::Maintenance->value);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Featured stays')
            ->assertSee('data-home-featured', false)
            ->assertDontSee($featuredRoom->name);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->get(route('home.featured'));

        // The deferred fragment is card-only: it must carry everything a guest
        // needs to act — the room, its photo, the rate it is quoted at, and the
        // one call to action.
        $response
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->assertSee($featuredRoom->name)
            ->assertSee('featured-room.webp')
            ->assertSee('From')
            ->assertSee('1,000.00')
            ->assertSee('per night')
            ->assertSee('View details')
            ->assertDontSee('Open to reserve')
            ->assertDontSee($maintenanceRoom->name)
            ->assertDontSee('per per');

        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('stale-while-revalidate=300', (string) $response->headers->get('Cache-Control'));

        $facilityQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains(strtolower($query['query']), 'from `facilities`'))
            ->count();

        $this->assertSame(1, $facilityQueries, 'Featured home rooms should be loaded with one compact query.');
    }

    public function test_home_and_reviews_show_published_notes_from_customer_accounts(): void
    {
        $this->seed(RoleSeeder::class);

        $guest = User::factory()->create(['name' => 'Isabel Mendoza']);
        $guest->assignRole(Role::Customer->value);

        GuestReview::factory()->featured()->create([
            'user_id' => $guest->id,
            'body' => 'Booked the family room for Ramon and the kids.',
            'stay_label' => 'Family stay',
            'rating' => 5,
            'published_at' => now()->subMonth(),
        ]);

        $category = FaqCategory::factory()->create(['is_active' => true]);
        Faq::factory()->create([
            'faq_category_id' => $category->id,
            'is_active' => true,
            'question' => 'Where is Villa Mercedes located?',
            'answer' => 'Villa Mercedes Nature Farm & Resort is at Barangay Namnama, City of Ilagan, Isabela, Philippines.',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Customer Reviews')
            ->assertSee('Isabel Mendoza')
            ->assertSee('Booked the family room for Ramon and the kids.')
            ->assertSee('Family stay')
            ->assertSee('Read more reviews')
            ->assertSeeInOrder(['Customer Reviews', 'Questions guests ask'])
            ->assertDontSee('Ana R.');

        $this->get(route('reviews'))
            ->assertOk()
            ->assertSee('Isabel Mendoza')
            ->assertSee('Customer Reviews')
            ->assertDontSee('1,200 guests')
            ->assertDontSee('Ana R.')
            ->assertDontSee('Customer reviews will appear here after customers share a review from their account.');
    }

    public function test_unpublished_guest_notes_stay_off_the_public_site(): void
    {
        GuestReview::factory()->featured()->create([
            'is_published' => false,
            'body' => 'This draft should never appear on the public site.',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('This draft should never appear on the public site.')
            ->assertDontSee('Customer Reviews');

        $this->get(route('reviews'))
            ->assertOk()
            ->assertDontSee('This draft should never appear on the public site.')
            ->assertSee('Customer reviews will appear here after customers share a review from their account.');
    }

    public function test_demo_account_reviews_stay_off_the_public_site_when_demo_content_is_disabled(): void
    {
        config(['resort.demo_content' => false]);

        $this->seed(RoleSeeder::class);

        $demoGuest = User::factory()->create([
            'name' => 'Demo Isabel',
            'email' => 'isabel.mendoza@villamercedes.test',
        ]);
        $demoGuest->assignRole(Role::Customer->value);

        GuestReview::factory()->featured()->create([
            'user_id' => $demoGuest->id,
            'body' => 'Seeded demo stay that must not appear in a public release.',
            'is_published' => true,
            'featured_on_home' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Seeded demo stay that must not appear in a public release.')
            ->assertDontSee('Demo Isabel');

        $this->get(route('reviews'))
            ->assertOk()
            ->assertDontSee('Seeded demo stay that must not appear in a public release.');
    }
}
