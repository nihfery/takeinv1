<?php

namespace App\Modules\Catalog\Presentation\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Booking\Application\Services\BookingFlowService;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Application\Queries\Filters\PublicProviderEligibilityFilter;
use App\Modules\Catalog\Application\Queries\PublicBranchSearchCriteria;
use App\Modules\Catalog\Application\Queries\SearchPublicBranches;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Review\Infrastructure\Persistence\Models\BranchReview;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicCatalogController extends ApiController
{
    public function __construct(
        private readonly BookingFlowService $bookingFlow,
        private readonly SearchPublicBranches $searchPublicBranches,
        private readonly PublicProviderEligibilityFilter $providerEligibility,
    ) {}

    public function categories(Request $request): JsonResponse
    {
        if ($request->boolean('hierarchy')) {
            $categories = ServiceCategory::query()
                ->roots()
                ->where('status', 'active')
                ->whereHas('children', fn ($query) => $query->publicLeaves())
                ->with(['children' => fn ($query) => $query
                    ->publicLeaves()
                    ->orderBy('sort_order')
                    ->orderBy('name')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (ServiceCategory $category) => $this->categoryPayload($category, true))
                ->values();

            return response()->json(['data' => $categories]);
        }

        $categories = ServiceCategory::query()
            ->with('parent:id,name,slug')
            ->where('status', 'active')
            ->when($request->query('featured'), fn ($query) => $query->where('is_featured', $request->boolean('featured')))
            ->when($request->query('search'), fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($this->perPage($request));

        $categories->setCollection($categories->getCollection()
            ->map(fn (ServiceCategory $category) => $this->categoryPayload($category)));

        return response()->json($categories);
    }

    public function services(Request $request): JsonResponse
    {
        $services = Service::query()
            ->with(['provider.providerProfile', 'serviceCategory.parent'])
            ->publiclyCategorized()
            ->where('status', 'active')
            ->whereHas('provider', fn ($providerQuery) => $this->providerEligibility->apply($providerQuery))
            ->when($request->query('provider_id'), fn ($query, $providerId) => $query->where('provider_id', $providerId))
            ->when($request->query('category'), function ($query, $category) {
                $query->whereHas('serviceCategory', fn ($categoryQuery) => $categoryQuery
                    ->where(fn ($matchQuery) => $matchQuery
                        ->where('name', $category)
                        ->orWhere('slug', $category)
                        ->orWhereHas('parent', fn ($parentQuery) => $parentQuery
                            ->where(fn ($parentMatchQuery) => $parentMatchQuery
                                ->where('name', $category)
                                ->orWhere('slug', $category)))));
            })
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('serviceCategory', fn ($categoryQuery) => $categoryQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhereHas('parent', fn ($parentQuery) => $parentQuery->where('name', 'like', "%{$search}%")));
                });
            })
            ->latest()
            ->paginate($this->perPage($request));

        $services->setCollection($services->getCollection()->map(fn (Service $service) => $this->servicePayload($service)));

        return response()->json($services);
    }

    public function locations(Request $request): JsonResponse
    {
        $locations = ProviderBranch::query()
            ->where('status', 'active')
            ->whereHas('provider', fn ($providerQuery) => $this->providerEligibility->apply($providerQuery))
            ->whereNotNull('city_id')
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('city_id', 'like', "%{$search}%")
                        ->orWhere('state_id', 'like', "%{$search}%")
                        ->orWhere('country_id', 'like', "%{$search}%");
                });
            })
            ->select('country_id', 'state_id', 'city_id')
            ->distinct()
            ->orderBy('country_id')
            ->orderBy('state_id')
            ->orderBy('city_id')
            ->get()
            ->map(fn (ProviderBranch $branch) => [
                'country' => $branch->country_id,
                'state' => $branch->state_id,
                'city' => $branch->city_id,
                'label' => collect([$branch->city_id, $branch->state_id, $branch->country_id])->filter()->implode(', '),
            ])
            ->values();

        return response()->json(['data' => $locations]);
    }

    public function branches(Request $request): JsonResponse
    {
        $request->validate([
            'booking_date' => ['nullable', 'date'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => [
                'nullable',
                'numeric',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($request->filled('min_price') && (float) $value < (float) $request->query('min_price')) {
                        $fail('The maximum price must be greater than or equal to the minimum price.');
                    }
                },
            ],
            'min_rating' => ['nullable', 'numeric', 'between:0,5'],
            'sort' => ['nullable', 'in:recommended,rating_desc,price_asc,price_desc,name_asc'],
        ]);

        $perPage = $this->perPage($request);
        $bookingDate = $request->filled('booking_date')
            ? Carbon::parse($request->query('booking_date'))->startOfDay()
            : null;

        if ($bookingDate && $bookingDate->lt(now()->startOfDay())) {
            return $this->emptyBranchPaginator($request, $perPage);
        }

        $latitude = $request->filled('lat') ? (float) $request->query('lat') : null;
        $longitude = $request->filled('lng') ? (float) $request->query('lng') : null;
        $radiusKm = $request->filled('radius_km')
            ? max(0, (float) $request->query('radius_km'))
            : ($request->filled('radius') ? max(0, (float) $request->query('radius')) : null);
        $hasAdvancedFilters = $request->filled('min_price')
            || $request->filled('max_price')
            || $request->filled('min_rating')
            || ($request->filled('sort') && $request->query('sort') !== 'recommended');

        $branches = $this->searchPublicBranches->handle(new PublicBranchSearchCriteria(
            bookingDate: $bookingDate,
            country: $request->query('country'),
            state: $request->query('state'),
            city: $request->query('city'),
            search: $request->query('search'),
            category: $request->query('category'),
            perPage: $perPage,
            requiresInMemoryFiltering: ($latitude !== null && $longitude !== null)
                || $bookingDate !== null
                || $hasAdvancedFilters
                || $request->filled('category'),
        ));

        if ($branches instanceof Collection) {
            $payloads = $branches
                ->filter(fn (ProviderBranch $branch) => ! $bookingDate || $this->branchAcceptsBookingsOnDate($branch, $bookingDate))
                ->filter(fn (ProviderBranch $branch) => ! $request->filled('category')
                    || $this->branchOffersCategory($branch, (string) $request->query('category')))
                ->map(fn (ProviderBranch $branch) => $this->branchPayload($branch, $latitude, $longitude, $bookingDate?->toDateString()))
                ->filter(fn (array $branch) => $radiusKm === null || (
                    isset($branch['distance_km'])
                    && $branch['distance_km'] <= $radiusKm
                ))
                ->when($request->filled('min_price'), fn (Collection $branches) => $branches->filter(
                    fn (array $branch) => $branch['min_price'] !== null
                        && (float) $branch['min_price'] >= (float) $request->query('min_price')
                ))
                ->when($request->filled('max_price'), fn (Collection $branches) => $branches->filter(
                    fn (array $branch) => $branch['min_price'] !== null
                        && (float) $branch['min_price'] <= (float) $request->query('max_price')
                ))
                ->when($request->filled('min_rating'), fn (Collection $branches) => $branches->filter(
                    fn (array $branch) => (float) ($branch['rating'] ?? 0) >= (float) $request->query('min_rating')
                ));

            $sort = (string) $request->query('sort', 'recommended');
            $payloads = match ($sort) {
                'rating_desc' => $payloads->sortByDesc(fn (array $branch) => (float) ($branch['rating'] ?? 0)),
                'price_asc' => $payloads->sortBy(fn (array $branch) => $branch['min_price'] ?? PHP_FLOAT_MAX),
                'price_desc' => $payloads->sortByDesc(fn (array $branch) => (float) ($branch['min_price'] ?? 0)),
                'name_asc' => $payloads->sortBy(fn (array $branch) => mb_strtolower((string) ($branch['branch_name'] ?? ''))),
                default => $latitude !== null && $longitude !== null
                    ? $payloads->sortBy(fn (array $branch) => $branch['distance_km'] ?? PHP_FLOAT_MAX)
                    : $payloads,
            };
            $payloads = $payloads->values();

            $page = LengthAwarePaginator::resolveCurrentPage();
            $items = $payloads->slice(($page - 1) * $perPage, $perPage)->values();

            return response()->json(new LengthAwarePaginator(
                $items,
                $payloads->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            ));
        }

        $branches->setCollection($branches->getCollection()->map(fn (ProviderBranch $branch) => $this->branchPayload($branch, null, null, null, false)));

        return response()->json($branches);
    }

    private function emptyBranchPaginator(Request $request, int $perPage): JsonResponse
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return response()->json(new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        ));
    }

    public function branch(ProviderBranch $branch): JsonResponse
    {
        abort_unless($this->bookingFlow->branchIsBookable($branch), 404);

        $branch->load([
            'provider:id,name,email',
            'provider.providerProfile:user_id,status,document_status,image',
            'staffs.skills' => fn ($query) => $query
                ->publiclyCategorized()
                ->where('services.status', 'active')
                ->with('serviceCategory.parent'),
            'staffs.schedules',
            'staffs.staffReviews.booking.customer:id,name',
        ]);
        $branch->staffs->loadCount('staffReviews')->loadAvg('staffReviews', 'rating');
        $branch->loadCount('branchReviews')->loadAvg('branchReviews', 'rating');

        $bookingFrequency = $this->bookingFrequencyForBranch($branch);
        $services = $this->sortServicesByBookingFrequency($this->servicesForBranch($branch), $bookingFrequency)
            ->map(fn (Service $service) => $this->servicePayload($service, (int) $bookingFrequency->get($service->id, 0)))
            ->values();
        $staff = $branch->staffs
            ->where('status', 'active')
            ->map(fn (ProviderStaff $staff) => $this->staffPayload($staff))
            ->values();

        $payload = $this->branchPayload($branch);
        $payload['services'] = $services;
        $payload['staff'] = $staff;
        $payload['service_groups'] = $this->groupServicesByCategory($services);
        $payload['available_booking_modes'] = [
            'scheduled' => $services->contains(fn ($service) => (bool) ($service['is_scheduled_enabled'] ?? false)),
            'queue' => $services->contains(fn ($service) => (bool) ($service['is_queue_enabled'] ?? false)),
        ];

        return response()->json(['data' => $payload]);
    }

    public function branchServices(ProviderBranch $branch): JsonResponse
    {
        abort_unless($this->bookingFlow->branchIsBookable($branch), 404);

        $bookingFrequency = $this->bookingFrequencyForBranch($branch);
        $services = $this->sortServicesByBookingFrequency($this->servicesForBranch($branch), $bookingFrequency)
            ->map(fn (Service $service) => $this->servicePayload($service, (int) $bookingFrequency->get($service->id, 0)))
            ->values();

        return response()->json([
            'data' => $services,
            'grouped' => $this->groupServicesByCategory($services),
        ]);
    }

    public function branchReviews(Request $request, ProviderBranch $branch): JsonResponse
    {
        abort_unless($this->bookingFlow->branchIsBookable($branch), 404);

        $branch->loadCount('branchReviews')->loadAvg('branchReviews', 'rating');
        $reviews = BranchReview::query()
            ->with('booking.customer:id,name')
            ->whereHas('booking', fn ($query) => $query->where('branch_id', $branch->id))
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $reviews->getCollection()
                ->map(fn (BranchReview $review) => $this->branchReviewPayload($review))
                ->values(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
            'summary' => $this->branchReviewSummary($branch),
        ]);
    }

    public function reviews(Request $request): JsonResponse
    {
        $reviews = BranchReview::query()
            ->with([
                'booking.customer:id,name',
                'booking.branch:id,provider_id,branch_name,city_id,state_id',
                'booking.branch.provider:id,name',
            ])
            ->whereHas('booking.branch', fn ($branchQuery) => $branchQuery
                ->where('status', 'active')
                ->whereHas('provider', fn ($providerQuery) => $this->providerEligibility->apply($providerQuery)))
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $reviews->getCollection()
                ->map(fn (BranchReview $review) => $this->branchReviewPayload($review, true))
                ->values(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function branchStaff(ProviderBranch $branch): JsonResponse
    {
        abort_unless($this->bookingFlow->branchIsBookable($branch), 404);

        $staff = ProviderStaff::query()
            ->with([
                'skills' => fn ($query) => $query
                    ->publiclyCategorized()
                    ->where('services.status', 'active')
                    ->with('serviceCategory.parent'),
                'schedules',
                'staffReviews.booking.customer:id,name',
            ])
            ->withCount('staffReviews')
            ->withAvg('staffReviews', 'rating')
            ->withCount([
                'bookings as completed_bookings_count' => fn ($query) => $query
                    ->whereIn('status', ['completed', 'order_completed']),
                'bookings as clients_served_count' => fn ($query) => $query
                    ->select(DB::raw('COUNT(DISTINCT customer_id)'))
                    ->whereIn('status', ['completed', 'order_completed']),
            ])
            ->where('branch_id', $branch->id)
            ->where('provider_id', $branch->provider_id)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get()
            ->map(fn (ProviderStaff $staff) => $this->staffPayload($staff))
            ->values();

        return response()->json(['data' => $staff]);
    }

    public function staff(ProviderStaff $staff): JsonResponse
    {
        $branch = $staff->branch;

        abort_unless(
            $staff->status === 'active'
                && $branch
                && (int) $staff->provider_id === (int) $branch->provider_id,
            404
        );

        return $this->branch($branch);
    }

    public function service(Service $service): JsonResponse
    {
        abort_unless($service->status === 'active', 404);
        abort_unless(
            Service::query()->publiclyCategorized()->whereKey($service->getKey())->exists(),
            404
        );
        abort_unless(
            $service->provider?->role === 'provider'
                && optional($service->provider?->providerProfile)->status === 'active'
                && optional($service->provider?->providerProfile)->document_status === 'verified',
            404
        );

        return response()->json([
            'data' => $this->servicePayload($service->load(['provider.providerProfile', 'serviceCategory.parent'])),
        ]);
    }

    public function providers(Request $request): JsonResponse
    {
        $providers = $this->providerEligibility
            ->apply(User::query()->with('providerProfile'))
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json($providers);
    }

    private function branchPayload(
        ProviderBranch $branch,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $bookingDate = null,
        bool $withAvailability = true
    ): array {
        $services = $this->servicesForBranch($branch);
        $minPrice = $services->min('price');
        $serviceCategories = $services
            ->flatMap(fn (Service $service) => [
                $service->serviceCategory?->name ?? $service->category,
                $service->serviceCategory?->parent?->name,
            ])
            ->filter()
            ->unique()
            ->values();
        $serviceTitles = $services
            ->pluck('title')
            ->filter()
            ->unique()
            ->values();
        $serviceSummaries = $services
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'slug' => $service->slug,
                'code' => $service->code,
                'title' => $service->title,
                'category' => $service->category,
                'category_id' => $service->serviceCategory?->id,
                'category_name' => $service->serviceCategory?->name ?? $service->category,
                'category_slug' => $service->serviceCategory?->slug,
                'main_category_id' => $service->serviceCategory?->parent?->id,
                'main_category_name' => $service->serviceCategory?->parent?->name,
                'main_category_slug' => $service->serviceCategory?->parent?->slug,
                'description' => $service->description,
                'price' => (float) ($service->price ?? 0),
                'minimum_duration' => (int) ($service->minimum_duration ?? 0),
                'estimated_duration' => (int) ($service->estimated_duration ?: 30),
            ])
            ->values();
        $branchPhotos = ! empty($branch->images) ? $branch->images : [$branch->image];
        $galleryImages = collect($branchPhotos)
            ->merge($services->pluck('gallery_image'))
            ->filter()
            ->map(fn (?string $path) => $this->storageUrl($path))
            ->filter()
            ->unique()
            ->values();
        $payload = array_merge($branch->toArray(), [
            'provider' => $branch->provider,
            'services_count' => $services->count(),
            'staffs_count' => $branch->staffs_count ?? $branch->staffs()->where('status', 'active')->count(),
            'min_price' => $minPrice !== null ? (float) $minPrice : null,
            'next_available_slot' => $withAvailability ? $this->nextAvailableSlot($branch, $services, $bookingDate) : null,
            'rating' => $this->branchReviewSummary($branch)['rating'],
            'review_count' => $this->branchReviewSummary($branch)['count'],
            'service_categories' => $serviceCategories,
            'service_titles' => $serviceTitles,
            'services' => $serviceSummaries,
            'has_queue_service' => $services->contains(fn (Service $service) => (bool) $service->is_queue_enabled),
            'has_scheduled_service' => $services->contains(fn (Service $service) => (bool) $service->is_scheduled_enabled),
            'supports_pay_at_salon' => $services->contains(fn (Service $service) => ! (bool) $service->requires_dp),
            'location_label' => collect([$branch->city_id, $branch->state_id, $branch->country_id])->filter()->implode(', '),
            'image_url' => $this->storageUrl($branch->image),
            'gallery_images' => $galleryImages,
        ]);

        if ($latitude !== null && $longitude !== null && $branch->latitude !== null && $branch->longitude !== null) {
            $payload['distance_km'] = round($this->distanceBetween(
                $latitude,
                $longitude,
                (float) $branch->latitude,
                (float) $branch->longitude
            ), 1);
        }

        return $payload;
    }

    private function branchReviewSummary(ProviderBranch $branch): array
    {
        $attributes = $branch->getAttributes();
        $count = array_key_exists('branch_reviews_count', $attributes)
            ? (int) $attributes['branch_reviews_count']
            : $branch->branchReviews()->count();
        $average = array_key_exists('branch_reviews_avg_rating', $attributes)
            ? $attributes['branch_reviews_avg_rating']
            : $branch->branchReviews()->avg('rating');

        return [
            'count' => $count,
            'rating' => $average !== null ? round((float) $average, 1) : null,
        ];
    }

    private function branchReviewPayload(BranchReview $review, bool $withBranchContext = false): array
    {
        $payload = [
            'id' => $review->id,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'images' => collect($review->images ?? [])
                ->filter()
                ->map(fn (string $path) => str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
                    ? $path
                    : asset('storage/'.ltrim($path, '/')))
                ->values(),
            'customer_name' => $review->booking?->customer?->name ?: 'Verified customer',
            'created_at' => $review->created_at?->toIso8601String(),
        ];

        if ($withBranchContext) {
            $branch = $review->booking?->branch;
            $payload['branch'] = $branch ? [
                'id' => $branch->id,
                'name' => $branch->branch_name,
                'city' => $branch->city_id,
                'state' => $branch->state_id,
                'provider' => $branch->provider ? [
                    'id' => $branch->provider->id,
                    'name' => $branch->provider->name,
                ] : null,
            ] : null;
        }

        return $payload;
    }

    private function categoryPayload(ServiceCategory $category, bool $withChildren = false): array
    {
        $payload = [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image_url' => $this->storageUrl($category->image),
            'icon_url' => $this->storageUrl($category->icon),
            'is_featured' => (bool) $category->is_featured,
            'sort_order' => (int) $category->sort_order,
            'parent' => $category->parent ? [
                'id' => $category->parent->id,
                'name' => $category->parent->name,
                'slug' => $category->parent->slug,
            ] : null,
        ];

        if ($withChildren) {
            $payload['children'] = $category->children
                ->map(fn (ServiceCategory $child) => $this->categoryPayload($child))
                ->values();
        }

        return $payload;
    }

    private function servicePayload(Service $service, ?int $bookingCount = null): array
    {
        $service->loadMissing('serviceCategory.parent');

        $payload = array_merge($service->toArray(), [
            'category_name' => $service->serviceCategory?->name ?? $service->category,
            'category_slug' => $service->serviceCategory?->slug,
            'main_category_name' => $service->serviceCategory?->parent?->name,
            'main_category_slug' => $service->serviceCategory?->parent?->slug,
            'category' => $service->category,
            'price' => (float) ($service->price ?? 0),
            'minimum_duration' => (int) ($service->minimum_duration ?? 0),
            'estimated_duration' => (int) ($service->estimated_duration ?: 30),
            'maximum_duration' => (int) ($service->maximum_duration ?: 60),
            'is_queue_enabled' => (bool) $service->is_queue_enabled,
            'is_scheduled_enabled' => (bool) $service->is_scheduled_enabled,
            'requires_dp' => (bool) $service->requires_dp,
            'dp_amount' => $service->dp_amount !== null ? (float) $service->dp_amount : null,
            'payment_policy' => $service->payment_policy,
            'image_url' => $this->storageUrl($service->gallery_image),
        ]);

        if ($bookingCount !== null) {
            $payload['booking_count'] = $bookingCount;
        }

        return $payload;
    }

    private function staffPayload(ProviderStaff $staff): array
    {
        $staff->loadMissing([
            'skills' => fn ($query) => $query
                ->publiclyCategorized()
                ->where('services.status', 'active')
                ->with('serviceCategory.parent'),
            'schedules',
            'staffReviews.booking.customer',
        ]);
        $reviewSummary = $this->staffReviewSummary($staff);
        $staffReviews = $staff->staffReviews
            ->sortByDesc('created_at')
            ->take(100)
            ->values();

        return [
            'id' => $staff->id,
            'name' => $staff->full_name ?: $staff->email,
            'first_name' => $staff->first_name,
            'last_name' => $staff->last_name,
            'gender' => $staff->gender,
            'bio' => $staff->bio,
            'role' => $staff->role,
            'image' => $staff->image,
            'image_url' => $this->storageUrl($staff->image),
            'rating' => $reviewSummary['rating'],
            'completed_bookings_count' => (int) ($staff->completed_bookings_count ?? 0),
            'clients_served_count' => (int) ($staff->clients_served_count ?? 0),
            'review_count' => $reviewSummary['count'],
            'reviews' => $staffReviews->map(fn ($review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'customer_name' => $review->booking?->customer?->name ?: 'Verified customer',
                'created_at' => $review->created_at?->toIso8601String(),
            ])->values(),
            'current_status' => $staff->current_status,
            'status' => $staff->status,
            'branch_id' => $staff->branch_id,
            'skills' => $staff->skills->map(fn (Service $service) => [
                'id' => $service->id,
                'title' => $service->title,
                'category_name' => $service->serviceCategory?->name ?? $service->category,
                'price' => (float) ($service->price ?? 0),
                'estimated_duration' => (int) ($service->estimated_duration ?: $service->minimum_duration ?: 0),
            ])->values(),
            'schedules' => $staff->schedules->map(fn ($schedule) => [
                'id' => $schedule->id,
                'day_of_week' => $schedule->day_of_week,
                'start_time' => substr((string) $schedule->start_time, 0, 5),
                'end_time' => substr((string) $schedule->end_time, 0, 5),
                'is_available' => (bool) $schedule->is_available,
            ])->values(),
        ];
    }

    private function staffReviewSummary(ProviderStaff $staff): array
    {
        $attributes = $staff->getAttributes();
        $count = array_key_exists('staff_reviews_count', $attributes)
            ? (int) $attributes['staff_reviews_count']
            : $staff->staffReviews()->count();
        $average = array_key_exists('staff_reviews_avg_rating', $attributes)
            ? $attributes['staff_reviews_avg_rating']
            : $staff->staffReviews()->avg('rating');

        return [
            'count' => $count,
            'rating' => $average !== null ? round((float) $average, 1) : null,
        ];
    }

    private function groupServicesByCategory(Collection $services): array
    {
        return $services
            ->groupBy(fn ($service) => $service['category_name'] ?? $service['category'] ?? 'Lainnya')
            ->map(fn (Collection $items, string $category) => [
                'category' => $category,
                'services' => $items->values(),
            ])
            ->values()
            ->all();
    }

    /** Request-scoped cache of active services per provider to avoid N+1 queries. */
    private array $providerServiceCache = [];

    private function servicesForBranch(ProviderBranch $branch): Collection
    {
        $providerId = $branch->provider_id;

        if (! isset($this->providerServiceCache[$providerId])) {
            $this->providerServiceCache[$providerId] = Service::query()
                ->with(['provider.providerProfile', 'serviceCategory.parent'])
                ->publiclyCategorized()
                ->where('provider_id', $providerId)
                ->where('status', 'active')
                ->latest()
                ->get();
        }

        return $this->providerServiceCache[$providerId]
            ->filter(function (Service $service) use ($branch) {
                $branchIds = $service->branch_ids;

                if (empty($branchIds)) {
                    return true;
                }

                return in_array((int) $branch->id, array_map('intval', (array) $branchIds), true);
            })
            ->values();
    }

    private function branchOffersCategory(ProviderBranch $branch, string $category): bool
    {
        $category = Str::lower(trim($category));

        return $this->servicesForBranch($branch)->contains(fn (Service $service) => collect([
            $service->serviceCategory?->name,
            $service->serviceCategory?->slug,
            $service->serviceCategory?->parent?->name,
            $service->serviceCategory?->parent?->slug,
        ])->filter()->contains(fn (string $value) => Str::lower($value) === $category));
    }

    private function bookingFrequencyForBranch(ProviderBranch $branch): Collection
    {
        return DB::table('booking_services')
            ->join('bookings', 'bookings.id', '=', 'booking_services.booking_id')
            ->where('bookings.branch_id', $branch->id)
            ->whereIn('bookings.status', [
                'pending_payment',
                'confirmed',
                'waiting',
                'checked_in',
                'in_progress',
                'inprogress',
                'rescheduled',
                'completed',
                'order_completed',
            ])
            ->select('booking_services.service_id', DB::raw('COUNT(*) as booking_count'))
            ->groupBy('booking_services.service_id')
            ->pluck('booking_count', 'booking_services.service_id');
    }

    private function sortServicesByBookingFrequency(Collection $services, Collection $bookingFrequency): Collection
    {
        return $services
            ->sort(function (Service $left, Service $right) use ($bookingFrequency): int {
                $leftCount = (int) $bookingFrequency->get($left->id, 0);
                $rightCount = (int) $bookingFrequency->get($right->id, 0);

                return ($rightCount <=> $leftCount)
                    ?: strcasecmp((string) $left->title, (string) $right->title);
            })
            ->values();
    }

    private function nextAvailableSlot(ProviderBranch $branch, Collection $services, ?string $bookingDate = null): ?string
    {
        if ($services->isEmpty()) {
            return null;
        }

        $firstService = $services->first();

        if (! $firstService?->is_scheduled_enabled) {
            return null;
        }

        $slots = $this->bookingFlow->availableSlots($branch, collect([$firstService]), $bookingDate ?: now()->toDateString());

        return $slots[0]['time'] ?? null;
    }

    private function branchAcceptsBookingsOnDate(ProviderBranch $branch, Carbon $date): bool
    {
        if (! $this->branchWorksOnDate($branch, $date)) {
            return false;
        }

        return $branch->staffs
            ->filter(fn (ProviderStaff $staff) => $staff->status === 'active' && $staff->current_status !== 'offline')
            ->contains(fn (ProviderStaff $staff) => $this->staffWorksOnDate($branch, $staff, $date));
    }

    private function branchWorksOnDate(ProviderBranch $branch, Carbon $date): bool
    {
        $dateValue = $date->toDateString();
        $holidays = collect((array) $branch->holidays)
            ->map(fn ($holiday) => substr((string) $holiday, 0, 10))
            ->filter()
            ->all();

        if (in_array($dateValue, $holidays, true)) {
            return false;
        }

        $workingDays = collect((array) $branch->working_days)
            ->map(fn ($day) => Str::lower((string) $day))
            ->filter()
            ->all();

        if (empty($workingDays)) {
            return true;
        }

        return count(array_intersect($workingDays, $this->dayAliases($date))) > 0;
    }

    private function staffWorksOnDate(ProviderBranch $branch, ProviderStaff $staff, Carbon $date): bool
    {
        $staff->loadMissing('schedules');
        $schedules = $staff->schedules
            ->filter(fn ($schedule) => (bool) $schedule->is_available)
            ->values();

        $dayAliases = $this->dayAliases($date);

        if ($schedules->contains(fn ($schedule) => in_array(Str::lower((string) $schedule->day_of_week), $dayAliases, true))) {
            return true;
        }

        $branchStart = substr((string) ($branch->working_start_hour ?: '09:00'), 0, 5);
        $branchEnd = substr((string) ($branch->working_end_hour ?: '18:00'), 0, 5);

        return $branchStart < $branchEnd;
    }

    private function dayAliases(Carbon $date): array
    {
        $aliases = [
            0 => ['0', 'sunday', 'sun', 'minggu', 'ahad'],
            1 => ['1', 'monday', 'mon', 'senin'],
            2 => ['2', 'tuesday', 'tue', 'selasa'],
            3 => ['3', 'wednesday', 'wed', 'rabu'],
            4 => ['4', 'thursday', 'thu', 'kamis'],
            5 => ['5', 'friday', 'fri', 'jumat', 'jum\'at'],
            6 => ['6', 'saturday', 'sat', 'sabtu'],
        ];

        return $aliases[(int) $date->dayOfWeek] ?? [];
    }

    private function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset(str_starts_with($path, 'storage/') ? $path : 'storage/'.ltrim($path, '/'));
    }

    private function distanceBetween(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $earthRadiusKm = 6371;
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
