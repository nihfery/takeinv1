<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Api\ApiController;
use App\Models\CustomerCart;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartController extends ApiController
{
    private const MAX_PAYLOAD_BYTES = 131072;
    private const CART_TTL_DAYS = 7;

    public function show(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'customer');

        $carts = CustomerCart::query()
            ->where('customer_id', $request->user()->id)
            ->latest('saved_at')
            ->latest('updated_at')
            ->get();
        $carts = $this->prepareCartsForResponse($carts);

        return response()->json([
            'data' => $carts,
            'count' => $carts->count(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'customer');

        $carts = CustomerCart::query()
            ->where('customer_id', $request->user()->id)
            ->latest('saved_at')
            ->latest('updated_at')
            ->get([
                'id',
                'customer_id',
                'branch_id',
                'salon_name',
                'total_amount',
                'total_duration',
                'current_step',
                'payload',
                'saved_at',
                'expires_at',
                'updated_at',
            ]);

        $carts = $this->prepareCartsForResponse($carts)
            ->each(fn (CustomerCart $cart) => $cart->makeHidden('payload'))
            ->values();

        return response()->json([
            'data' => $carts,
            'has_cart' => $carts->isNotEmpty(),
            'count' => $carts->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'customer');

        $validator = Validator::make($request->all(), $this->cartRules());
        $validator->after(fn ($validator) => $this->validateCartPayload($validator, $request->input('payload')));

        $validated = $validator->validate();
        $payload = $this->trustedServiceSelectionPayload($validated['payload']);
        $summary = $this->summaryFromPayload($payload);

        $cart = CustomerCart::query()->updateOrCreate(
            [
                'customer_id' => $request->user()->id,
                'branch_id' => $summary['branch_id'],
            ],
            [
                ...$summary,
                'payload' => $payload,
                'saved_at' => now(),
                'expires_at' => now()->addDays(self::CART_TTL_DAYS),
            ]
        );

        return response()->json([
            'message' => 'Cart berhasil disimpan.',
            'data' => $cart,
        ]);
    }

    public function destroy(Request $request, ?CustomerCart $cart = null): JsonResponse
    {
        $this->authorizeRole($request, 'customer');

        if ($cart) {
            abort_unless((int) $cart->customer_id === (int) $request->user()->id, 404);

            $cart->delete();

            return response()->json([
                'message' => 'Item cart berhasil dihapus.',
            ]);
        }

        CustomerCart::query()
            ->where('customer_id', $request->user()->id)
            ->delete();

        return response()->json([
            'message' => 'Cart berhasil dihapus.',
        ]);
    }

    private function summaryFromPayload(array $payload): array
    {
        $branchId = $payload['branchId'] ?? $payload['branch_id'] ?? $payload['salonId'] ?? null;

        return [
            'branch_id' => is_numeric($branchId) ? (int) $branchId : null,
            'salon_name' => $payload['salonName'] ?? $payload['salon_name'] ?? null,
            'total_amount' => $this->totalAmountFromPayload($payload),
            'total_duration' => $this->totalDurationFromPayload($payload),
            'current_step' => max(1, min(3, (int) ($payload['currentStep'] ?? $payload['current_step'] ?? 1))),
        ];
    }

    private function prepareCartsForResponse(Collection $carts): Collection
    {
        return $carts
            ->map(fn (CustomerCart $cart) => $this->prepareCartForResponse($cart))
            ->filter()
            ->values();
    }

    private function prepareCartForResponse(CustomerCart $cart): ?CustomerCart
    {
        if ($cart->expires_at && $cart->expires_at->isPast()) {
            $cart->delete();
            return null;
        }

        try {
            $payload = $this->trustedServiceSelectionPayload($cart->payload ?? []);
        } catch (ValidationException) {
            $cart->delete();
            return null;
        }

        $cart->forceFill([
            ...$this->summaryFromPayload($payload),
            'payload' => $payload,
            'expires_at' => $cart->expires_at ?: now()->addDays(self::CART_TTL_DAYS),
        ]);

        if ($cart->isDirty()) {
            $cart->save();
        }

        return $cart;
    }

    private function serviceSelectionPayload(array $payload): array
    {
        unset(
            $payload['addons'],
            $payload['staff'],
            $payload['staffAdjustment'],
            $payload['date'],
            $payload['time']
        );

        $payload['currentStep'] = 1;
        $payload['duration'] = $this->totalDurationFromPayload($payload);
        $payload['subtotal'] = $this->totalAmountFromPayload($payload);
        $payload['discount'] = 0;
        $payload['total'] = $payload['subtotal'];

        return $payload;
    }

    private function trustedServiceSelectionPayload(array $payload): array
    {
        $payload = $this->serviceSelectionPayload($payload);
        $branch = $this->cartBranch($payload);
        $services = $this->cartServices($payload, $branch);
        $servicePayload = $services
            ->map(fn (Service $service) => $this->serviceSnapshot($service, $payload))
            ->values()
            ->all();
        $availableServices = $this->servicesForBranch($branch)
            ->map(fn (Service $service) => $this->serviceSnapshot($service, $payload))
            ->values()
            ->all();

        $payload = [
            ...$payload,
            'salonId' => $branch->id,
            'branchId' => $branch->id,
            'branch_id' => $branch->id,
            'salonSlug' => $payload['salonSlug'] ?? Str::slug($branch->branch_name) . '-' . $branch->id,
            'salonName' => $branch->branch_name,
            'salonImage' => $branch->image_url,
            'salonAddress' => $branch->address,
            'availableServices' => $availableServices,
            'services' => $servicePayload,
            'currentStep' => 1,
        ];
        $payload['duration'] = $this->totalDurationFromPayload($payload);
        $payload['subtotal'] = $this->totalAmountFromPayload($payload);
        $payload['discount'] = 0;
        $payload['total'] = $payload['subtotal'];

        return $payload;
    }

    private function cartBranch(array $payload): ProviderBranch
    {
        $branchId = $payload['branchId'] ?? $payload['branch_id'] ?? $payload['salonId'] ?? null;

        if (! is_numeric($branchId)) {
            throw ValidationException::withMessages([
                'payload.branchId' => 'Branch cart tidak valid.',
            ]);
        }

        $branch = ProviderBranch::query()
            ->with('provider.providerProfile')
            ->whereKey((int) $branchId)
            ->first();

        if (! $branch || ! $this->branchIsBookable($branch)) {
            throw ValidationException::withMessages([
                'payload.branchId' => 'Branch sudah tidak tersedia untuk booking.',
            ]);
        }

        return $branch;
    }

    private function cartServices(array $payload, ProviderBranch $branch): Collection
    {
        $serviceIds = collect($payload['services'] ?? [])
            ->pluck('id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($serviceIds->isEmpty() || $serviceIds->count() !== count($payload['services'] ?? [])) {
            throw ValidationException::withMessages([
                'payload.services' => 'Service cart tidak valid.',
            ]);
        }

        $services = Service::query()
            ->with('serviceCategory')
            ->whereIn('id', $serviceIds)
            ->get()
            ->sortBy(fn (Service $service) => $serviceIds->search((int) $service->id))
            ->values();

        if ($services->count() !== $serviceIds->count()) {
            throw ValidationException::withMessages([
                'payload.services' => 'Ada service yang sudah tidak tersedia.',
            ]);
        }

        $invalidService = $services->first(fn (Service $service) => $service->status !== 'active'
            || (int) $service->provider_id !== (int) $branch->provider_id
            || ! $this->serviceBelongsToBranch($service, $branch));

        if ($invalidService) {
            throw ValidationException::withMessages([
                'payload.services' => 'Ada service yang sudah tidak tersedia di branch ini.',
            ]);
        }

        return $services;
    }

    private function serviceSnapshot(Service $service, array $payload): array
    {
        $existing = collect($payload['services'] ?? [])
            ->first(fn ($item) => is_array($item) && (string) ($item['id'] ?? '') === (string) $service->id) ?? [];

        return [
            'id' => $service->id,
            'name' => $service->title,
            'category' => $service->serviceCategory?->name ?? $service->category,
            'desc' => $service->description,
            'description' => $service->description,
            'duration' => (int) ($service->estimated_duration ?: 30),
            'price' => (float) ($service->price ?? 0),
            'discountPrice' => null,
            'popular' => (bool) ($existing['popular'] ?? false),
            'featured' => (bool) ($existing['featured'] ?? false),
            'slug' => $service->slug,
            'code' => $service->code,
        ];
    }

    private function servicesForBranch(ProviderBranch $branch): Collection
    {
        return Service::query()
            ->with('serviceCategory')
            ->where('provider_id', $branch->provider_id)
            ->where('status', 'active')
            ->orderBy('title')
            ->get()
            ->filter(fn (Service $service) => $this->serviceBelongsToBranch($service, $branch))
            ->values();
    }

    private function branchIsBookable(ProviderBranch $branch): bool
    {
        $profile = optional($branch->provider?->providerProfile);
        
        return $branch->status === 'active'
            && $branch->provider?->role === 'provider'
            && $profile->status === 'active'
            && $profile->document_status === 'verified';
    }

    private function serviceBelongsToBranch(Service $service, ProviderBranch $branch): bool
    {
        $branchIds = $service->branch_ids;

        if (empty($branchIds)) {
            return true;
        }

        return in_array((int) $branch->id, array_map('intval', (array) $branchIds), true);
    }

    private function cartRules(): array
    {
        $serviceRules = [
            'id' => ['required'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'desc' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration' => ['required', 'integer', 'min:1', 'max:1440'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'discountPrice' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'popular' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'slug' => ['nullable', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:120'],
        ];

        return [
            'payload' => ['required', 'array'],
            'payload.salonId' => ['required'],
            'payload.branchId' => ['nullable', 'integer'],
            'payload.branch_id' => ['nullable', 'integer'],
            'payload.salonSlug' => ['nullable', 'string', 'max:180'],
            'payload.salonName' => ['required', 'string', 'max:255'],
            'payload.salonImage' => ['nullable', 'string', 'max:2048'],
            'payload.salonAddress' => ['nullable', 'string', 'max:500'],
            'payload.salonRating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'payload.salonReviews' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'payload.availableServices' => ['nullable', 'array', 'max:100'],
            ...$this->nestedItemRules('payload.availableServices.*', $serviceRules, false),
            'payload.services' => ['required', 'array', 'min:1', 'max:20'],
            ...$this->nestedItemRules('payload.services.*', $serviceRules),
            'payload.addons' => ['nullable', 'array', 'max:20'],
            'payload.addons.*.id' => ['required_with:payload.addons'],
            'payload.addons.*.name' => ['required_with:payload.addons', 'string', 'max:255'],
            'payload.addons.*.desc' => ['nullable', 'string', 'max:1000'],
            'payload.addons.*.parentService' => ['nullable', 'string', 'max:255'],
            'payload.addons.*.duration' => ['required_with:payload.addons', 'integer', 'min:1', 'max:1440'],
            'payload.addons.*.price' => ['required_with:payload.addons', 'numeric', 'min:0', 'max:999999999'],
            'payload.staffAdjustment' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'payload.date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'payload.time' => ['nullable', 'date_format:H:i'],
            'payload.duration' => ['nullable', 'integer', 'min:0', 'max:2880'],
            'payload.subtotal' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'payload.discount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'payload.total' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'payload.currentStep' => ['required', 'integer', 'min:1', 'max:3'],
        ];
    }

    private function nestedItemRules(string $prefix, array $rules, bool $required = true): array
    {
        $nestedRules = [];

        foreach ($rules as $key => $rule) {
            if (! $required && in_array('required', $rule, true)) {
                $rule = array_values(array_filter($rule, fn ($item) => $item !== 'required'));
                array_unshift($rule, 'required_with:' . explode('.*', $prefix)[0]);
            }

            $nestedRules["{$prefix}.{$key}"] = $rule;
        }

        return $nestedRules;
    }

    private function validateCartPayload($validator, mixed $payload): void
    {
        if (! is_array($payload)) {
            return;
        }

        $encodedPayload = json_encode($payload);
        if ($encodedPayload === false || strlen($encodedPayload) > self::MAX_PAYLOAD_BYTES) {
            $validator->errors()->add('payload', 'Cart payload terlalu besar.');
        }

        $unknownKeys = array_diff(array_keys($payload), [
            'salonId',
            'branchId',
            'branch_id',
            'salonSlug',
            'salonName',
            'salonImage',
            'salonAddress',
            'salonRating',
            'salonReviews',
            'availableServices',
            'services',
            'addons',
            'staff',
            'staffAdjustment',
            'date',
            'time',
            'duration',
            'subtotal',
            'discount',
            'total',
            'currentStep',
        ]);

        if ($unknownKeys !== []) {
            $validator->errors()->add('payload', 'Cart payload memiliki field yang tidak dikenal.');
        }

        $this->validateIdentifier($validator, 'payload.salonId', $payload['salonId'] ?? null);

        $allowedServiceKeys = [
            'id',
            'name',
            'category',
            'desc',
            'description',
            'duration',
            'price',
            'discountPrice',
            'popular',
            'featured',
            'slug',
            'code',
        ];
        $this->validateAllowedItemKeys($validator, $payload, 'services', $allowedServiceKeys);
        $this->validateAllowedItemKeys($validator, $payload, 'availableServices', $allowedServiceKeys);
        $this->validateCollectionIdentifiers($validator, $payload, 'services');
        $this->validateCollectionIdentifiers($validator, $payload, 'availableServices');
        $this->validateAllowedItemKeys($validator, $payload, 'addons', [
            'id',
            'name',
            'desc',
            'parentService',
            'duration',
            'price',
        ]);
        $this->validateCollectionIdentifiers($validator, $payload, 'addons');

        $staff = $payload['staff'] ?? null;
        if ($staff !== null && $staff !== 'any') {
            if (! is_array($staff)) {
                $validator->errors()->add('payload.staff', 'Staff tidak valid.');
            } else {
                $unknownStaffKeys = array_diff(array_keys($staff), ['id', 'name', 'role', 'photo', 'rating', 'reviews']);
                if ($unknownStaffKeys !== []) {
                    $validator->errors()->add('payload.staff', 'Data staff memiliki field yang tidak dikenal.');
                }

                foreach (['id', 'name'] as $requiredKey) {
                    if (! isset($staff[$requiredKey]) || ! is_scalar($staff[$requiredKey]) || trim((string) $staff[$requiredKey]) === '') {
                        $validator->errors()->add("payload.staff.{$requiredKey}", 'Staff tidak lengkap.');
                    }
                }

                foreach (['id', 'name', 'role', 'photo'] as $staffKey) {
                    if (isset($staff[$staffKey]) && strlen((string) $staff[$staffKey]) > 255) {
                        $validator->errors()->add("payload.staff.{$staffKey}", 'Data staff terlalu panjang.');
                    }
                }

                if (isset($staff['rating']) && (! is_numeric($staff['rating']) || (float) $staff['rating'] < 0 || (float) $staff['rating'] > 5)) {
                    $validator->errors()->add('payload.staff.rating', 'Rating staff tidak valid.');
                }

                if (isset($staff['reviews']) && (! is_numeric($staff['reviews']) || (int) $staff['reviews'] < 0)) {
                    $validator->errors()->add('payload.staff.reviews', 'Jumlah review staff tidak valid.');
                }
            }
        }

        if ((int) ($payload['currentStep'] ?? 1) >= 3 && $staff === null) {
            $validator->errors()->add('payload.staff', 'Staff wajib dipilih sebelum masuk ke step waktu.');
        }

        if (($payload['time'] ?? '') !== '' && ($payload['date'] ?? '') === '') {
            $validator->errors()->add('payload.date', 'Tanggal wajib ada jika waktu dipilih.');
        }
    }

    private function validateAllowedItemKeys($validator, array $payload, string $collectionKey, array $allowedKeys): void
    {
        foreach (($payload[$collectionKey] ?? []) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            if (array_diff(array_keys($item), $allowedKeys) !== []) {
                $validator->errors()->add("payload.{$collectionKey}.{$index}", 'Item cart memiliki field yang tidak dikenal.');
            }
        }
    }

    private function validateCollectionIdentifiers($validator, array $payload, string $collectionKey): void
    {
        foreach (($payload[$collectionKey] ?? []) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $this->validateIdentifier($validator, "payload.{$collectionKey}.{$index}.id", $item['id'] ?? null);
        }
    }

    private function validateIdentifier($validator, string $field, mixed $value): void
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            $validator->errors()->add($field, 'Identifier tidak valid.');
            return;
        }

        if (strlen((string) $value) > 120) {
            $validator->errors()->add($field, 'Identifier terlalu panjang.');
        }
    }

    private function totalAmountFromPayload(array $payload): float
    {
        $total = 0;

        foreach ($payload['services'] ?? [] as $service) {
            $total += (float) ($service['discountPrice'] ?? $service['price'] ?? 0);
        }

        foreach ($payload['addons'] ?? [] as $addon) {
            $total += (float) ($addon['price'] ?? 0);
        }

        return $total + (float) ($payload['staffAdjustment'] ?? 0);
    }

    private function totalDurationFromPayload(array $payload): int
    {
        $duration = 0;

        foreach ($payload['services'] ?? [] as $service) {
            $duration += (int) ($service['duration'] ?? 0);
        }

        foreach ($payload['addons'] ?? [] as $addon) {
            $duration += (int) ($addon['duration'] ?? 0);
        }

        return $duration;
    }
}
