<?php

namespace App\Modules\Catalog\Presentation\Api\Provider;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Provider\Application\Support\ProviderAccountScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $services = Service::query()
            ->with('provider.providerProfile')
            ->where('provider_id', $this->providerId($request))
            ->latest();
        ProviderAccountScope::applyServiceBranchScope($services, $this->providerBranchId($request));

        $services = $services->paginate($this->perPage($request));

        return response()->json($services);
    }

    public function store(Request $request): JsonResponse
    {
        $providerId = $this->providerId($request);
        $validated = $this->validateService($request);

        $service = Service::create(array_merge($validated, [
            'provider_id' => $providerId,
            'slug' => $this->uniqueSlug($providerId, $validated['title']),
            'branch_ids' => $this->validBranchIds($request, $providerId, $validated['branch_ids'] ?? []),
            'gallery_image' => $this->storeUploadedFile($request, 'gallery_image', 'service-gallery'),
            'status' => $validated['status'] ?? 'active',
            'verify_status' => 'pending',
        ]));

        return response()->json(['message' => 'Service has been added.', 'data' => $service], 201);
    }

    public function show(Request $request, Service $service): JsonResponse
    {
        $this->authorizeService($request, $service);

        return response()->json(['data' => $service->load('provider.providerProfile')]);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $providerId = $this->providerId($request);
        $this->authorizeService($request, $service);
        $validated = $this->validateService($request, $service->id);

        $service->update(array_merge($validated, [
            'slug' => $service->title === $validated['title']
                ? $service->slug
                : $this->uniqueSlug($providerId, $validated['title'], $service->id),
            'branch_ids' => $this->branchIdsForWrite($request, $providerId, $validated['branch_ids'] ?? [], $service),
            'gallery_image' => $this->replaceUploadedFile($request, 'gallery_image', $service->gallery_image, 'service-gallery'),
            'status' => $validated['status'] ?? $service->status,
        ]));

        return response()->json(['message' => 'Service has been updated.', 'data' => $service->refresh()]);
    }

    public function updateBranch(Request $request, Service $service): JsonResponse
    {
        $providerId = $this->providerId($request);
        $this->authorizeService($request, $service);

        $validated = $request->validate([
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer'],
        ]);

        $service->update([
            'branch_ids' => $this->branchIdsForWrite($request, $providerId, $validated['branch_ids'] ?? [], $service),
        ]);

        return response()->json(['message' => 'Service branch assignment has been updated.', 'data' => $service->refresh()]);
    }

    public function updateGallery(Request $request, Service $service): JsonResponse
    {
        $this->authorizeService($request, $service);

        $validated = $request->validate([
            'gallery_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
            'video_url' => ['nullable', 'url', 'max:255'],
        ]);

        $service->update([
            'gallery_image' => $this->replaceUploadedFile($request, 'gallery_image', $service->gallery_image, 'service-gallery'),
            'video_url' => $validated['video_url'] ?? null,
        ]);

        return response()->json(['message' => 'Service gallery has been updated.', 'data' => $service->refresh()]);
    }

    public function toggleStatus(Request $request, Service $service): JsonResponse
    {
        $this->authorizeService($request, $service);

        $service->update(['status' => $service->status === 'active' ? 'inactive' : 'active']);

        return response()->json(['message' => 'Service status has been updated.', 'data' => $service->refresh()]);
    }

    public function destroy(Request $request, Service $service): JsonResponse
    {
        $this->authorizeService($request, $service);

        if ($service->gallery_image) {
            Storage::disk('public')->delete($service->gallery_image);
        }

        $service->delete();

        return response()->json(['message' => 'Service has been deleted.']);
    }

    private function validateService(Request $request, ?int $serviceId = null): array
    {
        $providerId = $this->providerId($request);
        $code = trim((string) $request->input('code', ''));
        $request->merge(['code' => $code === '' ? null : $code]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('services', 'code')
                    ->where(fn ($query) => $query->where('provider_id', $providerId))
                    ->ignore($serviceId),
            ],
            'category' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'description' => ['nullable', 'string'],
            'includes' => ['nullable', 'string'],
            'price_type' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'minimum_duration' => ['nullable', 'integer', 'min:0'],
            'estimated_duration' => ['nullable', 'integer', 'min:1'],
            'maximum_duration' => ['nullable', 'integer', 'min:1'],
            'is_queue_enabled' => ['nullable', 'boolean'],
            'is_scheduled_enabled' => ['nullable', 'boolean'],
            'requires_dp' => ['nullable', 'boolean'],
            'dp_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_policy' => ['nullable', 'string', 'max:1000'],
            'slots' => ['nullable', 'array'],
            'slots.*' => ['nullable', 'array'],
            'additional_services' => ['nullable', 'array'],
            'additional_services.*.name' => ['nullable', 'string', 'max:255'],
            'additional_services.*.price' => ['nullable', 'numeric', 'min:0'],
            'additional_services.*.description' => ['nullable', 'string', 'max:255'],
            'holidays' => ['nullable', 'array'],
            'holidays.*.date' => ['nullable', 'date'],
            'holidays.*.full_day' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer'],
            'gallery_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
            'video_url' => ['nullable', 'url', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ], [
            'code.unique' => 'Product Code sudah digunakan oleh service lain di bisnis Anda.',
        ]);

        $validated['slots'] = $this->cleanSlots($validated['slots'] ?? []);
        $validated['additional_services'] = $this->cleanAdditionalServices($validated['additional_services'] ?? []);
        $validated['holidays'] = $this->cleanHolidays($validated['holidays'] ?? []);

        return $validated;
    }

    private function cleanSlots(array $slots): array
    {
        $cleaned = [];

        foreach ($slots as $day => $rows) {
            foreach ((array) $rows as $row) {
                if (! empty($row['start']) && ! empty($row['end'])) {
                    $cleaned[$day][] = ['start' => $row['start'], 'end' => $row['end']];
                }
            }
        }

        return $cleaned;
    }

    private function cleanAdditionalServices(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row) => trim($row['name'] ?? '') !== '' || trim((string) ($row['price'] ?? '')) !== '' || trim($row['description'] ?? '') !== '')
            ->map(fn ($row) => [
                'name' => $row['name'] ?? null,
                'price' => $row['price'] ?? null,
                'description' => $row['description'] ?? null,
            ])
            ->values()
            ->toArray();
    }

    private function cleanHolidays(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row) => ! empty($row['date']))
            ->map(fn ($row) => [
                'date' => $row['date'],
                'full_day' => ! empty($row['full_day']),
            ])
            ->values()
            ->toArray();
    }

    private function validBranchIds(Request $request, int $providerId, array $branchIds): array
    {
        if ($this->providerBranchId($request) !== null) {
            abort_if($this->providerBranchId($request) < 1, 403, 'Akun cabang belum terhubung ke branch.');

            return [$this->providerBranchId($request)];
        }

        return ProviderBranch::where('provider_id', $providerId)
            ->whereIn('id', $branchIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    private function branchIdsForWrite(Request $request, int $providerId, array $branchIds, ?Service $service = null): array
    {
        $validBranchIds = $this->validBranchIds($request, $providerId, $branchIds);

        if ($this->providerBranchId($request) === null || ! $service) {
            return $validBranchIds;
        }

        $existingBranchIds = collect($service->branch_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        return $existingBranchIds->isEmpty()
            ? []
            : $existingBranchIds->merge($validBranchIds)->unique()->values()->all();
    }

    private function uniqueSlug(int $providerId, string $title, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($title) ?: 'service';
        $slug = $baseSlug;
        $counter = 1;

        while (Service::where('provider_id', $providerId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function authorizeService(Request $request, Service $service): void
    {
        abort_if((int) $service->provider_id !== $this->providerId($request), 403);
        abort_unless(ProviderAccountScope::serviceBelongsToBranch($service, $this->providerBranchId($request)), 403);
    }
}
