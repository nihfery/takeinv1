<?php

namespace App\Modules\Booking\Presentation\Api\Customer;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Booking\Application\Services\BookingFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GraphqlController extends ApiController
{
    public function __construct(private readonly BookingFlowService $bookingFlow)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'operationName' => ['nullable', 'string'],
        ]);

        $query = $validated['query'];
        $variables = $validated['variables'] ?? [];
        $operation = $validated['operationName'] ?: $this->operationFromQuery($query);

        try {
            $data = match ($operation) {
                'CustomerBookingPage' => [
                    'customerBookingPage' => $this->customerBookingPage($variables),
                ],
                'CustomerBookingEligibleStaff' => [
                    'customerBookingEligibleStaff' => $this->customerBookingEligibleStaff($variables),
                ],
                'CustomerBookingAvailability' => [
                    'customerBookingAvailability' => $this->customerBookingAvailability($variables, $request),
                ],
                default => throw ValidationException::withMessages([
                    'operationName' => 'GraphQL operation tidak tersedia untuk customer landing.',
                ]),
            };

            return response()->json(['data' => $data]);
        } catch (ValidationException $exception) {
            return response()->json([
                'data' => null,
                'errors' => $this->graphqlErrors($exception->errors()),
            ], 422);
        }
    }

    private function customerBookingPage(array $variables): array
    {
        $validated = validator($variables, [
            'branchId' => ['required', 'integer', Rule::exists('provider_branches', 'id')],
            'serviceIds' => ['nullable', 'array'],
            'serviceIds.*' => ['integer', Rule::exists('services', 'id')],
            'bookingDate' => ['nullable', 'date', 'after_or_equal:today'],
            'staffId' => ['nullable', 'integer', Rule::exists('provider_staffs', 'id')],
            'heldBookingId' => ['nullable', 'integer', Rule::exists('bookings', 'id')],
            'bookingType' => ['nullable', Rule::in(['scheduled', 'queue'])],
            'participantCount' => ['nullable', 'integer', 'min:1', 'max:5'],
        ])->validate();

        $branch = $this->bookableBranch((int) $validated['branchId']);
        $branch->loadMissing(['provider.providerProfile', 'staffs.skills', 'staffs.schedules']);
        $branch->staffs->loadCount('staffReviews')->loadAvg('staffReviews', 'rating');
        $branch->loadCount('branchReviews')->loadAvg('branchReviews', 'rating');

        $services = $this->servicesForBranch($branch)
            ->map(fn (Service $service) => $this->servicePayload($service))
            ->values();
        $staff = $branch->staffs
            ->where('status', 'active')
            ->map(fn (ProviderStaff $staff) => $this->staffPayload($staff))
            ->values();
        $payload = [
            'branch' => array_merge($this->branchPayload($branch), [
                'services' => $services,
                'staff' => $staff,
                'service_groups' => $this->groupServicesByCategory($services),
            ]),
            'booking_preview' => null,
        ];

        $serviceIds = collect($validated['serviceIds'] ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($serviceIds !== []) {
            $payload['booking_preview'] = $this->availabilityPayload($branch, [
                'service_ids' => $serviceIds,
                'booking_type' => $validated['bookingType'] ?? 'scheduled',
                'booking_date' => $validated['bookingDate'] ?? null,
                'staff_id' => $validated['staffId'] ?? null,
                'held_booking_id' => $validated['heldBookingId'] ?? null,
                'participant_count' => $validated['participantCount'] ?? 1,
            ]);
        }

        return $payload;
    }

    private function customerBookingEligibleStaff(array $variables): array
    {
        $validated = validator($variables, [
            'branchId' => ['required', 'integer', Rule::exists('provider_branches', 'id')],
            'serviceIds' => ['required', 'array', 'min:1'],
            'serviceIds.*' => ['required', 'integer', Rule::exists('services', 'id')],
            'bookingDate' => ['nullable', 'date', 'after_or_equal:today'],
            'staffId' => ['nullable', 'integer', Rule::exists('provider_staffs', 'id')],
            'bookingType' => ['nullable', Rule::in(['scheduled', 'queue'])],
            'participantCount' => ['nullable', 'integer', 'min:1', 'max:5'],
        ])->validate();

        $branch = $this->bookableBranch((int) $validated['branchId']);
        $payload = $this->availabilityPayload($branch, [
            'service_ids' => $validated['serviceIds'],
            'booking_type' => $validated['bookingType'] ?? 'scheduled',
            'booking_date' => $validated['bookingDate'] ?? null,
            'staff_id' => $validated['staffId'] ?? null,
            'participant_count' => $validated['participantCount'] ?? 1,
        ]);

        $payload['available_slots'] = [];
        $payload['queue_estimation'] = null;

        return $payload;
    }

    private function customerBookingAvailability(array $variables, Request $request): array
    {
        $validated = validator($variables, [
            'branchId' => ['required', 'integer', Rule::exists('provider_branches', 'id')],
            'serviceIds' => ['required', 'array', 'min:1'],
            'serviceIds.*' => ['required', 'integer', Rule::exists('services', 'id')],
            'bookingDate' => ['nullable', 'date', 'after_or_equal:today'],
            'staffId' => ['nullable', 'integer', Rule::exists('provider_staffs', 'id')],
            'heldBookingId' => ['nullable', 'integer', Rule::exists('bookings', 'id')],
            'bookingType' => ['nullable', Rule::in(['scheduled', 'queue'])],
            'participantCount' => ['nullable', 'integer', 'min:1', 'max:5'],
        ])->validate();

        $branch = $this->bookableBranch((int) $validated['branchId']);
        $services = $this->bookingFlow->servicesForBooking(
            $branch,
            $this->normalizedIds($validated['serviceIds']),
            $validated['bookingType'] ?? 'scheduled'
        );
        $customer = $this->optionalCustomer($request);
        $heldBookingId = null;

        if ($customer?->id && filled($validated['heldBookingId'] ?? null)) {
            $heldBookingId = Booking::query()
                ->whereKey($validated['heldBookingId'])
                ->where('customer_id', $customer->id)
                ->whereIn('status', [BookingFlowService::STATUS_PENDING_HOLD, 'pending'])
                ->whereNotNull('hold_expires_at')
                ->where('hold_expires_at', '>', now())
                ->value('id');
        }

        if (! $heldBookingId && $customer?->id) {
            $heldBookingId = $this->bookingFlow->activeHoldIdForCustomerSelection(
                (int) $customer->id,
                $branch,
                $services,
                $validated['bookingDate'] ?? null,
                filled($validated['staffId'] ?? null) ? (int) $validated['staffId'] : null,
                (int) ($validated['participantCount'] ?? 1)
            );
        }

        return $this->bookingFlow->availabilityPayload(
            $branch,
            $services,
            $validated['bookingDate'] ?? null,
            filled($validated['staffId'] ?? null) ? (int) $validated['staffId'] : null,
            $validated['bookingType'] ?? 'scheduled',
            $heldBookingId ? (int) $heldBookingId : null,
            $customer?->id ? (int) $customer->id : null,
            (int) ($validated['participantCount'] ?? 1)
        );
    }

    private function optionalCustomer(Request $request): ?User
    {
        $user = $request->user('sanctum') ?: $request->user() ?: Auth::guard('web')->user();

        return $user instanceof User && $user->role === 'customer'
            ? $user
            : null;
    }

    private function availabilityPayload(ProviderBranch $branch, array $payload): array
    {
        $bookingType = $payload['booking_type'] ?? 'scheduled';
        $services = $this->bookingFlow->servicesForBooking(
            $branch,
            $this->normalizedIds($payload['service_ids'] ?? []),
            $bookingType
        );

        return $this->bookingFlow->availabilityPayload(
            $branch,
            $services,
            $payload['booking_date'] ?? null,
            filled($payload['staff_id'] ?? null) ? (int) $payload['staff_id'] : null,
            $bookingType,
            filled($payload['held_booking_id'] ?? null) ? (int) $payload['held_booking_id'] : null,
            null,
            (int) ($payload['participant_count'] ?? 1)
        );
    }

    private function bookableBranch(int $branchId): ProviderBranch
    {
        $branch = ProviderBranch::query()
            ->with('provider.providerProfile')
            ->whereKey($branchId)
            ->where('status', 'active')
            ->firstOrFail();

        abort_unless($this->bookingFlow->branchIsBookable($branch), 404);

        return $branch;
    }

    private function normalizedIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function operationFromQuery(string $query): ?string
    {
        if (preg_match('/\b(query|mutation)\s+([A-Za-z0-9_]+)/', $query, $matches)) {
            return $matches[2];
        }

        foreach (['CustomerBookingPage', 'CustomerBookingEligibleStaff', 'CustomerBookingAvailability'] as $operation) {
            if (str_contains($query, $operation) || str_contains($query, lcfirst($operation))) {
                return $operation;
            }
        }

        return null;
    }

    private function branchPayload(ProviderBranch $branch): array
    {
        $branchPhotos = ! empty($branch->images) ? $branch->images : [$branch->image];

        return array_merge($branch->toArray(), [
            'id' => $branch->id,
            'name' => $branch->branch_name,
            'branch_name' => $branch->branch_name,
            'provider' => $branch->provider,
            'rating' => $branch->branch_reviews_avg_rating !== null
                ? round((float) $branch->branch_reviews_avg_rating, 1)
                : null,
            'review_count' => (int) ($branch->branch_reviews_count ?? 0),
            'location_label' => collect([$branch->city_id, $branch->state_id, $branch->country_id])->filter()->implode(', '),
            'image_url' => $this->storageUrl($branch->image),
            'gallery_images' => collect($branchPhotos)->filter()->map(fn (?string $path) => $this->storageUrl($path))->values(),
        ]);
    }

    private function servicePayload(Service $service): array
    {
        $service->loadMissing('serviceCategory');

        return array_merge($service->toArray(), [
            'name' => $service->title,
            'title' => $service->title,
            'category_name' => $service->serviceCategory?->name ?? $service->category,
            'category' => $service->category,
            'price' => (float) ($service->price ?? 0),
            'minimum_duration' => (int) ($service->minimum_duration ?? 0),
            'estimated_duration' => (int) ($service->estimated_duration ?: 30),
            'maximum_duration' => (int) ($service->maximum_duration ?: 60),
            'is_queue_enabled' => (bool) $service->is_queue_enabled,
            'is_scheduled_enabled' => (bool) $service->is_scheduled_enabled,
            'requires_dp' => (bool) $service->requires_dp,
            'dp_amount' => $service->dp_amount !== null ? (float) $service->dp_amount : null,
            'image_url' => $this->storageUrl($service->gallery_image),
        ]);
    }

    private function staffPayload(ProviderStaff $staff): array
    {
        $staff->loadMissing('skills', 'schedules');

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
            'rating' => $this->staffReviewRating($staff),
            'current_status' => $staff->current_status,
            'status' => $staff->status,
            'branch_id' => $staff->branch_id,
            'skills' => $staff->skills->map(fn (Service $service) => [
                'id' => $service->id,
                'title' => $service->title,
                'category_name' => $service->serviceCategory?->name ?? $service->category,
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

    private function staffReviewRating(ProviderStaff $staff): ?float
    {
        $attributes = $staff->getAttributes();
        $average = array_key_exists('staff_reviews_avg_rating', $attributes)
            ? $attributes['staff_reviews_avg_rating']
            : $staff->staffReviews()->avg('rating');

        return $average !== null ? round((float) $average, 1) : null;
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

    private function servicesForBranch(ProviderBranch $branch): Collection
    {
        $bookingFrequency = $this->bookingFrequencyForBranch($branch);

        return Service::query()
            ->with('serviceCategory')
            ->where('provider_id', $branch->provider_id)
            ->where('status', 'active')
            ->get()
            ->filter(fn (Service $service) => $this->serviceBelongsToBranch($service, $branch))
            ->sort(function (Service $left, Service $right) use ($bookingFrequency): int {
                $leftCount = (int) $bookingFrequency->get($left->id, 0);
                $rightCount = (int) $bookingFrequency->get($right->id, 0);

                return ($rightCount <=> $leftCount)
                    ?: strcasecmp((string) $left->title, (string) $right->title);
            })
            ->values();
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

    private function serviceBelongsToBranch(Service $service, ProviderBranch $branch): bool
    {
        $branchIds = $service->branch_ids;

        if (empty($branchIds)) {
            return true;
        }

        return in_array((int) $branch->id, array_map('intval', (array) $branchIds), true);
    }

    private function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    private function graphqlErrors(array $errors): array
    {
        return collect($errors)
            ->flatMap(fn (array $messages, string $field) => collect($messages)->map(fn (string $message) => [
                'message' => $message,
                'path' => [$field],
            ]))
            ->values()
            ->all();
    }
}
