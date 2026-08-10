<?php

namespace App\Modules\Catalog\Presentation\Web\Provider;

use App\Http\Controllers\Controller;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use App\Modules\Provider\Application\Support\ProviderAccountScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Modules\Subscription\Application\Services\ProviderEntitlementService;

class ServiceController extends Controller
{
    private function providerId(): int
    {
        $user = Auth::user();

        if (!$user) {
            abort(401);
        }

        return ProviderAccountScope::providerId($user);
    }

    private function branchId(): ?int
    {
        return ProviderAccountScope::branchId(Auth::user());
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $status = (string) $request->get('status', 'all');
        $documentStatus = (string) $request->get('document_status', 'all');
        $priceType = (string) $request->get('price_type', 'all');
        $sortBy = (string) $request->get('sort_by', 'created_at');
        $sortDirection = strtolower((string) $request->get('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->get('per_page', 10);

        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $allowedStatuses = ['all', 'active', 'inactive'];
        $allowedDocumentStatuses = ['all', 'verified', 'submitted', 'pending', 'rejected'];
        $allowedPriceTypes = ['all', 'fixed', 'hourly'];
        $sortColumns = [
            'title' => 'services.title',
            'category' => 'services.category',
            'code' => 'services.code',
            'price' => 'services.price',
            'status' => 'services.status',
            'document_status' => 'provider_profiles.document_status',
            'created_at' => 'services.created_at',
        ];

        $status = in_array($status, $allowedStatuses, true) ? $status : 'all';
        $documentStatus = in_array($documentStatus, $allowedDocumentStatuses, true) ? $documentStatus : 'all';
        $priceType = in_array($priceType, $allowedPriceTypes, true) ? $priceType : 'all';
        $sortBy = array_key_exists($sortBy, $sortColumns) ? $sortBy : 'created_at';

        $baseQuery = Service::query()
            ->leftJoin('provider_profiles as provider_profiles', 'provider_profiles.user_id', '=', 'services.provider_id')
            ->where('services.provider_id', $this->providerId())
            ->select('services.*', DB::raw('provider_profiles.document_status as provider_document_status'));
        ProviderAccountScope::applyServiceBranchScope($baseQuery, $this->branchId());

        $summary = [
            'total' => (clone $baseQuery)->count('services.id'),
            'active' => (clone $baseQuery)->where('services.status', 'active')->count('services.id'),
            'verified' => (clone $baseQuery)->where('provider_profiles.document_status', 'verified')->count('services.id'),
            'revenue' => (clone $baseQuery)->sum('services.price'),
        ];

        $query = (clone $baseQuery)
            ->when($status !== 'all', fn ($builder) => $builder->where('services.status', $status))
            ->when($documentStatus !== 'all', function ($builder) use ($documentStatus) {
                if ($documentStatus === 'pending') {
                    $builder->where(function ($query) {
                        $query->whereNull('provider_profiles.document_status')
                            ->orWhere('provider_profiles.document_status', 'pending');
                    });

                    return;
                }

                $builder->where('provider_profiles.document_status', $documentStatus);
            })
            ->when($priceType !== 'all', fn ($builder) => $builder->where('services.price_type', $priceType));

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('services.title', 'like', '%' . $search . '%')
                    ->orWhere('services.slug', 'like', '%' . $search . '%')
                    ->orWhere('services.category', 'like', '%' . $search . '%')
                    ->orWhere('services.code', 'like', '%' . $search . '%')
                    ->orWhere('services.status', 'like', '%' . $search . '%')
                    ->orWhere('provider_profiles.document_status', 'like', '%' . $search . '%');
            });
        }

        $services = $query
            ->orderBy($sortColumns[$sortBy], $sortDirection)
            ->orderByDesc('services.id')
            ->paginate($perPage)
            ->withQueryString();

        $filters = [
            'status' => $status,
            'search' => $search,
            'per_page' => $perPage,
            'document_status' => $documentStatus,
            'price_type' => $priceType,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];

        $tabs = [
            'all' => 'All Services',
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];

        $hasActiveFilters = $search !== ''
            || $status !== 'all'
            || $documentStatus !== 'all'
            || $priceType !== 'all'
            || $perPage !== 10
            || $sortBy !== 'created_at'
            || $sortDirection !== 'desc';

        return view('provider.pages.services.index', compact(
            'services',
            'search',
            'perPage',
            'filters',
            'summary',
            'tabs',
            'sortBy',
            'sortDirection',
            'hasActiveFilters'
        ));
    }

    public function create(Request $request)
    {
        $entitlement = app(ProviderEntitlementService::class)->checkResourceLimit(Auth::user(), 'services');
        if (!$entitlement['allowed']) {
            return provider_route_redirect('provider.services.index')
                ->with('error', $entitlement['reason']);
        }
        $step = $request->get('step', 'service');

        if (in_array($step, ['branch', 'gallery']) && !session()->has('service_draft')) {
            return provider_route_redirect('provider.services.create')
                ->with('error', 'Isi Service Information dulu, lalu klik Continue.');
        }

        if ($step === 'gallery' && !session()->has('service_branch_draft')) {
            return provider_route_redirect('provider.services.create', ['step' => 'branch'])
                ->with('error', 'Select a branch first, then click Continue.');
        }

        $data = $this->formData();

        return view('provider.pages.services.create', array_merge($data, [
            'mode' => 'create',
            'step' => $step,
            'service' => null,
            'draft' => session('service_draft', []),
            'branchDraft' => session('service_branch_draft', $this->branchId() !== null && $this->branchId() > 0 ? [
                'branch_ids' => [$this->branchId()],
            ] : []),
        ]));
    }

    public function continueInformation(Request $request)
    {
        $entitlement = app(ProviderEntitlementService::class)->checkResourceLimit(Auth::user(), 'services');
        if (!$entitlement['allowed']) {
            return provider_route_redirect('provider.services.index')
                ->with('error', $entitlement['reason']);
        }
        $validated = $this->validateServiceInformation($request);

        session([
            'service_draft' => $validated,
        ]);

        return provider_route_redirect('provider.services.create', ['step' => 'branch'])
            ->with('success', 'Service information has been saved temporarily.');
    }

    public function continueBranch(Request $request)
    {
        $entitlement = app(ProviderEntitlementService::class)->checkResourceLimit(Auth::user(), 'services');
        if (!$entitlement['allowed']) {
            return provider_route_redirect('provider.services.index')
                ->with('error', $entitlement['reason']);
        }
        $validated = $request->validate([
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer'],
        ]);

        $validated['branch_ids'] = $this->validBranchIds($validated['branch_ids'] ?? []);

        session([
            'service_branch_draft' => $validated,
        ]);

        return provider_route_redirect('provider.services.create', ['step' => 'gallery'])
            ->with('success', 'Branch information has been saved temporarily.');
    }

    public function store(Request $request)
    {
        if (!session()->has('service_draft')) {
            return provider_route_redirect('provider.services.create')
                ->with('error', 'Service information has not been completed.');
        }

        if (!session()->has('service_branch_draft')) {
            return provider_route_redirect('provider.services.create', ['step' => 'branch'])
                ->with('error', 'Branch information has not been completed.');
        }

        $galleryData = $this->validateGallery($request);

        $draft = session('service_draft');
        $branchDraft = session('service_branch_draft');

        $imagePath = null;

        if ($request->hasFile('gallery_image')) {
            $imagePath = $request->file('gallery_image')->store('service-gallery', 'public');
        }

        $title = $draft['title'];
        $entitlement = null;
        $duplicateCode = false;

        \Illuminate\Support\Facades\DB::transaction(function () use ($draft, $branchDraft, $title, $imagePath, $galleryData, &$entitlement, &$duplicateCode) {
            $user = \Illuminate\Support\Facades\Auth::user();
            $providerOwnerId = \App\Modules\Provider\Application\Support\ProviderMenuAccess::providerOwnerId($user);

            \App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile::where('user_id', $providerOwnerId)->lockForUpdate()->first();

            $entitlement = app(\App\Modules\Subscription\Application\Services\ProviderEntitlementService::class)->checkResourceLimit($user, 'services');
            if (!$entitlement['allowed']) {
                return;
            }

            $code = $draft['code'] ?? null;
            if ($code !== null && Service::where('provider_id', $providerOwnerId)->where('code', $code)->exists()) {
                $duplicateCode = true;
                return;
            }

            // Generate the slug after locking the provider profile so two
            // simultaneous creates for this provider cannot choose the same one.
            $slug = $this->uniqueSlug($title);

            Service::create([
                'provider_id' => $providerOwnerId,
                'title' => $title,
                'slug' => $slug,
                'category' => $draft['category'],
                'category_id' => $draft['category_id'] ?? null,
                'code' => $draft['code'] ?? null,
                'description' => $draft['description'] ?? null,
                'includes' => $draft['includes'] ?? null,
                'price_type' => $draft['price_type'] ?? null,
                'price' => $draft['price'] ?? 0,
                'minimum_duration' => $draft['minimum_duration'] ?? 0,
                'estimated_duration' => $draft['estimated_duration'] ?? 30,
                'maximum_duration' => $draft['maximum_duration'] ?? 60,
                'is_queue_enabled' => $draft['is_queue_enabled'] ?? true,
                'is_scheduled_enabled' => $draft['is_scheduled_enabled'] ?? true,
                'requires_dp' => $draft['requires_dp'] ?? false,
                'dp_amount' => $draft['dp_amount'] ?? null,
                'payment_policy' => $draft['payment_policy'] ?? null,
                'slots' => $draft['slots'] ?? [],
                'additional_services' => $draft['additional_services'] ?? [],
                'holidays' => $draft['holidays'] ?? [],
                'branch_ids' => $branchDraft['branch_ids'] ?? [],
                'gallery_image' => $imagePath,
                'video_url' => $galleryData['video_url'] ?? null,
                'status' => 'active',
                'verify_status' => 'pending',
            ]);
        });

        if ($entitlement && !$entitlement['allowed']) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            return provider_route_redirect('provider.services.index')
                ->with('error', $entitlement['reason']);
        }

        if ($duplicateCode) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            return provider_route_redirect('provider.services.create')
                ->withErrors(['code' => 'Product Code sudah digunakan oleh service lain di bisnis Anda.'])
                ->with('error', 'Periksa kembali Product Code sebelum menyimpan service.');
        }

        session()->forget([
            'service_draft',
            'service_branch_draft',
        ]);

        return provider_route_redirect('provider.services.index')
            ->with('success', 'Service has been added.');
    }

    public function edit(Request $request, Service $service)
    {
        $this->authorizeService($service);

        $step = $request->get('step', 'service');
        $data = $this->formData();

        return view('provider.pages.services.create', array_merge($data, [
            'mode' => 'edit',
            'step' => $step,
            'service' => $service,
            'draft' => [],
            'branchDraft' => [
                'branch_ids' => $service->branch_ids ?? [],
            ],
        ]));
    }

    public function update(Request $request, Service $service)
    {
        $this->authorizeService($service);

        $validated = $this->validateServiceInformation($request, $service->id);

        $newSlug = $service->title === $validated['title']
            ? $service->slug
            : $this->uniqueSlug($validated['title'], $service->id);

        $service->update([
            'title' => $validated['title'],
            'slug' => $newSlug,
            'category' => $validated['category'],
            'category_id' => $validated['category_id'] ?? null,
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
            'includes' => $validated['includes'] ?? null,
            'price_type' => $validated['price_type'] ?? null,
            'price' => $validated['price'],
            'minimum_duration' => $validated['minimum_duration'] ?? 0,
            'estimated_duration' => $validated['estimated_duration'] ?? 30,
            'maximum_duration' => $validated['maximum_duration'] ?? 60,
            'is_queue_enabled' => $validated['is_queue_enabled'] ?? true,
            'is_scheduled_enabled' => $validated['is_scheduled_enabled'] ?? true,
            'requires_dp' => $validated['requires_dp'] ?? false,
            'dp_amount' => $validated['dp_amount'] ?? null,
            'payment_policy' => $validated['payment_policy'] ?? null,
            'slots' => $validated['slots'] ?? [],
            'additional_services' => $validated['additional_services'] ?? [],
            'holidays' => $validated['holidays'] ?? [],
        ]);

        return provider_route_redirect('provider.services.edit', [
                'service' => $service->id,
                'step' => 'branch',
            ])
            ->with('success', 'Service information has been updated.');
    }

    public function updateBranch(Request $request, Service $service)
    {
        $this->authorizeService($service);

        $validated = $request->validate([
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer'],
        ]);

        $branchIds = $this->validBranchIds($validated['branch_ids'] ?? []);

        if ($this->branchId() !== null) {
            $existingBranchIds = collect($service->branch_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();

            $branchIds = $existingBranchIds->isEmpty()
                ? []
                : $existingBranchIds->merge($branchIds)->unique()->values()->all();
        }

        $service->update([
            'branch_ids' => $branchIds,
        ]);

        return provider_route_redirect('provider.services.edit', [
                'service' => $service->id,
                'step' => 'gallery',
            ])
            ->with('success', 'Branch information has been updated.');
    }

    public function updateGallery(Request $request, Service $service)
    {
        $this->authorizeService($service);

        $validated = $this->validateGallery($request);

        $imagePath = $service->gallery_image;

        if ($request->hasFile('gallery_image')) {
            if ($service->gallery_image) {
                Storage::disk('public')->delete($service->gallery_image);
            }

            $imagePath = $request->file('gallery_image')->store('service-gallery', 'public');
        }

        $service->update([
            'gallery_image' => $imagePath,
            'video_url' => $validated['video_url'] ?? null,
        ]);

        return provider_route_redirect('provider.services.index')
            ->with('success', 'Service has been updated.');
    }

    public function toggleStatus(Service $service)
    {
        $this->authorizeService($service);

        $service->update([
            'status' => $service->status === 'active' ? 'inactive' : 'active',
        ]);

        return provider_route_redirect('provider.services.index')
            ->with('success', 'Service status has been updated.');
    }

    public function destroy(Service $service)
    {
        $this->authorizeService($service);

        $hasBookings = $service->multiServiceBookings()->exists();

        if ($hasBookings) {
            $service->update(['status' => 'inactive']);
            return provider_route_redirect('provider.services.index')
                ->with('success', 'Layanan telah dinonaktifkan karena sudah pernah dipesan.');
        }

        if ($service->gallery_image) {
            Storage::disk('public')->delete($service->gallery_image);
        }

        $service->delete();

        return provider_route_redirect('provider.services.index')
            ->with('success', 'Layanan berhasil dihapus secara permanen.');
    }

    private function validateServiceInformation(Request $request, ?int $serviceId = null): array
    {
        $code = trim((string) $request->input('code', ''));
        $request->merge(['code' => $code === '' ? null : $code]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('services', 'code')
                    ->where(fn ($query) => $query->where('provider_id', $this->providerId()))
                    ->ignore($serviceId),
            ],
            // `category` stays available for old drafts/services. New provider forms
            // submit a leaf category_id and the readable name is derived below.
            'category' => ['required_without:category_id', 'nullable', 'string', 'max:255'],
            'category_group_id' => [
                'nullable',
                'integer',
                Rule::exists('service_categories', 'id')->where(fn ($query) => $query
                    ->whereNull('parent_id')
                    ->where('status', 'active')),
            ],
            'category_id' => [
                'required_with:category_group_id',
                'nullable',
                'integer',
                Rule::exists('service_categories', 'id')->where(fn ($query) => $query
                    ->whereNotNull('parent_id')
                    ->where('status', 'active')),
            ],
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
            'holidays.*.full_day' => ['nullable'],
        ], [
            'code.unique' => 'Product Code sudah digunakan oleh service lain di bisnis Anda.',
        ]);

        $validated['slots'] = $this->cleanSlots($validated['slots'] ?? []);
        $validated['additional_services'] = $this->cleanAdditionalServices($validated['additional_services'] ?? []);
        $validated['holidays'] = $this->cleanHolidays($validated['holidays'] ?? []);

        if (! empty($validated['category_id'])) {
            $selectedCategory = ServiceCategory::query()
                ->with('parent')
                ->where('status', 'active')
                ->whereNotNull('parent_id')
                ->find($validated['category_id']);

            if (! $selectedCategory
                || (! empty($validated['category_group_id'])
                    && (int) $selectedCategory->parent_id !== (int) $validated['category_group_id'])) {
                throw ValidationException::withMessages([
                    'category_id' => 'Select a service type that belongs to the chosen main category.',
                ]);
            }

            $validated['category'] = $selectedCategory->name;
        }

        unset($validated['category_group_id']);

        return $validated;
    }

    private function validateGallery(Request $request): array
    {
        return $request->validate([
            'gallery_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
            'video_url' => ['nullable', 'url', 'max:255'],
        ]);
    }

    private function cleanSlots(array $slots): array
    {
        $cleaned = [];

        foreach ($slots as $day => $rows) {
            if (!is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                $start = $row['start'] ?? null;
                $end = $row['end'] ?? null;

                if ($start && $end) {
                    $cleaned[$day][] = [
                        'start' => $start,
                        'end' => $end,
                    ];
                }
            }
        }

        return $cleaned;
    }

    private function cleanAdditionalServices(array $rows): array
    {
        return collect($rows)
            ->filter(function ($row) {
                $name = trim($row['name'] ?? '');
                $price = trim((string) ($row['price'] ?? ''));
                $description = trim($row['description'] ?? '');

                return $name !== '' || $price !== '' || $description !== '';
            })
            ->map(function ($row) {
                return [
                    'name' => $row['name'] ?? null,
                    'price' => $row['price'] ?? null,
                    'description' => $row['description'] ?? null,
                ];
            })
            ->values()
            ->toArray();
    }

    private function cleanHolidays(array $rows): array
    {
        return collect($rows)
            ->filter(function ($row) {
                return !empty($row['date']);
            })
            ->map(function ($row) {
                return [
                    'date' => $row['date'],
                    'full_day' => !empty($row['full_day']) ? 1 : 0,
                ];
            })
            ->values()
            ->toArray();
    }

    private function validBranchIds(array $branchIds): array
    {
        if ($this->branchId() !== null) {
            abort_if($this->branchId() < 1, 403, 'Branch account is not connected to a branch yet.');

            return [$this->branchId()];
        }

        return ProviderBranch::where('provider_id', $this->providerId())
            ->whereIn('id', $branchIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    private function formData(): array
    {
        $categories = collect();
        $branches = collect();

        if (Schema::hasTable('service_categories')) {
            if (Schema::hasColumn('service_categories', 'parent_id')) {
                $categories = ServiceCategory::query()
                    ->roots()
                    ->where('status', 'active')
                    ->whereHas('children', fn ($query) => $query->where('status', 'active'))
                    ->with(['children' => fn ($query) => $query->where('status', 'active')])
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();
            } else {
                $categories = DB::table('service_categories')
                    ->select('id', 'name')
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->get();
            }
        }

        if (Schema::hasTable('provider_branches')) {
            $branches = ProviderBranch::where('provider_id', $this->providerId())
                ->with('staffs')
                ->latest();
            ProviderAccountScope::applyBranchModelScope($branches, $this->branchId());

            $branches = $branches->get();
        }

        return compact('categories', 'branches');
    }

    private function authorizeService(Service $service): void
    {
        abort_if((int) $service->provider_id !== $this->providerId(), 403);
        abort_unless(ProviderAccountScope::serviceBelongsToBranch($service, $this->branchId()), 403);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($title);

        if (!$baseSlug) {
            $baseSlug = 'service';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (
            Service::where('provider_id', $this->providerId())
                ->where('slug', $slug)
                ->when($ignoreId, function ($query) use ($ignoreId) {
                    $query->where('id', '!=', $ignoreId);
                })
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
