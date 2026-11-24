<?php

namespace App\Http\Controllers;

use App\Enums\BookingServiceType;
use App\Enums\PagePhotoCollection;
use App\Models\GuestReview;
use App\Models\PagePhoto;
use App\Models\ResortPool;
use App\Services\AvailabilitySearchService;
use App\Services\Customer\CustomerFaqService;
use App\Services\FacilityQueryService;
use App\Support\PublicHomeCache;
use App\Support\StayQuery;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    private const SERVICES_HERO_HEADLINE = 'Choose the space that fits your plans.';

    private const SERVICES_HERO_SUPPORT = 'Room categories and individual units, four function halls, and curated packages — each listed with its photos, included guests, and extra-person charges.';

    public function __construct(
        private readonly FacilityQueryService $queryService,
        private readonly CustomerFaqService $faqService,
        private readonly AvailabilitySearchService $availabilitySearch,
    ) {}

    public function home(): View
    {
        $homeFaqs = $this->faqService->getHomeFaqs();
        $galleryItems = collect(PublicHomeCache::gallery(function (): array {
            $items = PagePhoto::query()
                ->forCollection(PagePhotoCollection::Gallery)
                ->ordered()
                ->limit(8)
                ->get(['id', 'collection', 'image_path', 'title', 'caption'])
                ->map(fn (PagePhoto $photo) => [
                    'title' => $photo->displayTitle(),
                    'caption' => $photo->caption,
                    'url' => $photo->url(),
                ])
                ->all();

            return $items !== [] ? $items : collect(config('resort.gallery'))
                ->map(fn (array $item) => [
                    'title' => $item['title'],
                    'caption' => $item['caption'],
                    'url' => null,
                ])
                ->all();
        }));

        $homeReviews = GuestReview::query()
            ->visibleOnPublicSite()
            ->with(['user:id,name', 'reservation:id,check_in,checked_out_at,completed_at'])
            ->orderByDesc('featured_on_home')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('pages.home', compact('galleryItems', 'homeFaqs', 'homeReviews'));
    }

    /**
     * Rendered only after the featured section approaches the viewport.
     */
    public function featured(): Response
    {
        $featuredRooms = $this->queryService->getHomeFeaturedRooms();

        return response()
            ->view('pages.partials.featured-facilities', compact('featuredRooms'))
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }

    public function about(): View
    {
        $faqs = $this->faqService->getActiveFaqs();
        $reviews = GuestReview::query()
            ->visibleOnPublicSite()
            ->with(['user:id,name', 'reservation:id,check_in,checked_out_at,completed_at'])
            ->orderByDesc('featured_on_home')
            ->orderByDesc('published_at')
            ->get();

        return view('pages.about', compact('faqs', 'reviews'));
    }

    public function reviews(): View
    {
        $reviews = GuestReview::query()
            ->visibleOnPublicSite()
            ->with(['user:id,name', 'reservation:id,check_in,checked_out_at,completed_at'])
            ->orderByDesc('published_at')
            ->get();

        return view('pages.reviews', compact('reviews'));
    }

    public function gallery(): View
    {
        $photos = PagePhoto::query()
            ->forCollection(PagePhotoCollection::Gallery)
            ->ordered()
            ->get();

        return view('pages.gallery', compact('photos'));
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function terms(): View
    {
        return view('pages.terms');
    }

    public function privacy(): View
    {
        return view('pages.privacy');
    }

    public function rooms(Request $request): View
    {
        return $this->listing(
            $request,
            BookingServiceType::Room,
            'Rooms',
            self::SERVICES_HERO_SUPPORT
        );
    }

    public function halls(Request $request): View
    {
        return $this->listing(
            $request,
            BookingServiceType::FunctionHall,
            'Function Halls',
            self::SERVICES_HERO_SUPPORT
        );
    }

    public function packages(Request $request): View
    {
        return $this->listing(
            $request,
            BookingServiceType::ResortPackage,
            'Packages',
            self::SERVICES_HERO_SUPPORT
        );
    }

    public function cottages(Request $request): View
    {
        return $this->listing(
            $request,
            BookingServiceType::Cottage,
            'Cottages',
            'Reserve a poolside cottage for a day or night swimming session.'
        );
    }

    public function pools(Request $request): View
    {
        return $this->listing(
            $request,
            BookingServiceType::Pool,
            'Resort Pools',
            'Pool entrance is paid at the gate. Reserve a cottage or package if you want a booked stay.'
        );
    }

    private function listing(
        Request $request,
        BookingServiceType $serviceType,
        string $title,
        string $description
    ): View {
        StayQuery::normalize($request);
        $filters = $this->listingFilters($request);
        $typeSlug = $serviceType->facilityType()?->value;
        $listingType = $serviceType->listingRouteName();
        $facilities = $typeSlug ? $this->queryService->listPublicFacilities($typeSlug, $filters) : collect();

        return view('pages.facilities', [
            'facilities' => $facilities,
            'packageFamilies' => $listingType === 'packages'
                ? $this->queryService->groupFlyerPackages($facilities)
                : collect(),
            'ungroupedPackages' => $listingType === 'packages'
                ? $facilities->filter(fn ($facility) => $facility->package_family === null)->values()
                : collect(),
            'groupedFacilities' => $listingType === 'rooms'
                ? $this->queryService->groupByListingCategory($facilities)
                : collect([$title => $facilities]),
            'pools' => $serviceType === BookingServiceType::Pool
                ? ResortPool::query()->where('is_active', true)->orderBy('sort_order')->get()
                : collect(),
            'title' => $title,
            'description' => $description,
            'heroHeadline' => in_array($listingType, ['rooms', 'halls', 'packages'], true)
                ? self::SERVICES_HERO_HEADLINE
                : $title,
            'heroSupport' => $description,
            'listingType' => $listingType,
            'serviceType' => $serviceType,
            'sort' => $filters['sort'],
            'minCapacity' => $filters['min_capacity'],
            'availability' => $this->availabilityFor($request, $serviceType),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function availabilityFor(Request $request, BookingServiceType $serviceType): ?array
    {
        if (! StayQuery::isComplete($request)) {
            return null;
        }

        $validator = Validator::make($request->all(), [
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after_or_equal:check_in'],
            'guests' => ['required', 'integer', 'min:1', 'max:500'],
            'session' => ['nullable', 'string', 'max:50', Rule::exists('swimming_sessions', 'slug')->where('is_active', true)],
        ]);

        if ($validator->fails()) {
            return [
                'available' => false,
                'status' => 'invalid',
                'message' => $validator->errors()->first(),
                'results' => [],
                'alternatives' => [],
            ];
        }

        $data = $validator->validated();

        return $this->availabilitySearch->search(
            $serviceType,
            Carbon::parse($data['check_in']),
            Carbon::parse($data['check_out']),
            (int) $data['guests'],
            $data['session'] ?? null,
        );
    }

    /**
     * @return array{min_capacity: int, sort: string}
     */
    private function listingFilters(Request $request): array
    {
        $sort = $request->query('sort', 'recommended');
        $allowed = ['recommended', 'price-asc', 'price-desc', 'capacity'];

        if (! in_array($sort, $allowed, true)) {
            $sort = 'recommended';
        }

        $min = filter_var($request->query('min_capacity'), FILTER_VALIDATE_INT);

        return [
            'min_capacity' => ($min !== false && $min > 0) ? $min : 0,
            'sort' => $sort,
        ];
    }
}
