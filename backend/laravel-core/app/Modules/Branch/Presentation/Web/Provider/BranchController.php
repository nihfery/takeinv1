<?php

namespace App\Modules\Branch\Presentation\Web\Provider;

use App\Http\Controllers\Controller;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Subscription\Application\Services\ProviderEntitlementService;
use App\Modules\Provider\Application\Support\ProviderAccountScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BranchController extends Controller
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

    private function isBranchAccount(): bool
    {
        return ProviderAccountScope::isBranchAccount(Auth::user());
    }

    public function index()
    {
        $branches = ProviderBranch::withCount('staffs')
            ->where('provider_id', $this->providerId())
            ->latest();
        ProviderAccountScope::applyBranchModelScope($branches, $this->branchId());

        $branches = $branches->get();
        $isBranchAccount = $this->isBranchAccount();

        return view('provider.pages.branches.index', compact('branches', 'isBranchAccount'));
    }

    public function create(Request $request)
    {
        abort_if($this->isBranchAccount(), 403, 'Branch accounts cannot create a new branch.');

        $entitlement = app(ProviderEntitlementService::class)->checkResourceLimit(Auth::user(), 'branches');
        if (!$entitlement['allowed']) {
            return provider_route_redirect('provider.branch.index')
                ->with('error', $entitlement['reason']);
        }

        if ($request->get('step') === 'staff') {
            return provider_route_redirect('provider.branch.create')
                ->with('success', 'Data branch tetap tersimpan. Penempatan staff sekarang dilakukan dari menu Add Staff.');
        }

        $data = $this->dropdownData();

        return view('provider.pages.branches.form', array_merge($data, [
            'mode' => 'create',
            'step' => 'branch',
            'branch' => null,
            'draft' => session('branch_draft', []),
        ]));
    }

    public function continue(Request $request)
    {
        // Kept for backward-compatible links/forms. Branch creation is now a
        // single step; staff choose their work branch from the Add Staff form.
        return $this->store($request);
    }

    public function store(Request $request)
    {
        abort_if($this->isBranchAccount(), 403, 'Branch accounts cannot create a new branch.');

        $entitlement = app(ProviderEntitlementService::class)->checkResourceLimit(Auth::user(), 'branches');
        if (!$entitlement['allowed']) {
            return provider_route_redirect('provider.branch.index')
                ->with('error', $entitlement['reason']);
        }

        $validated = $this->validateBranch($request);
        $validated['holidays'] = array_values(array_filter($validated['holidays'] ?? []));

        // A draft may still exist for a user who was halfway through the former
        // two-step flow. Reuse its uploaded gallery so no work is lost.
        $currentImages = (array) (session('branch_draft')['images'] ?? []);
        $images = $this->resolveBranchImages($request, $currentImages);

        if (empty($images)) {
            return back()
                ->withInput()
                ->withErrors(['images' => 'Upload minimal 1 foto cabang.']);
        }

        $validated['images'] = $images;
        $validated['image'] = $images[0];
        unset($validated['existing_images']);

        $lockedEntitlement = null;

        DB::transaction(function () use ($validated, &$lockedEntitlement) {
            $user = \Illuminate\Support\Facades\Auth::user();
            $providerOwnerId = \App\Modules\Provider\Application\Support\ProviderMenuAccess::providerOwnerId($user);
            
            // Lock the provider profile to prevent concurrent quota bypass
            \App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile::where('user_id', $providerOwnerId)->lockForUpdate()->first();

            $lockedEntitlement = app(\App\Modules\Subscription\Application\Services\ProviderEntitlementService::class)->checkResourceLimit($user, 'branches');
            if (!$lockedEntitlement['allowed']) {
                return;
            }

            \App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch::create(array_merge($validated, [
                'provider_id' => $providerOwnerId,
            ]));
        });

        if ($lockedEntitlement && !$lockedEntitlement['allowed']) {
            return provider_route_redirect('provider.branch.index')
                ->with('error', $lockedEntitlement['reason']);
        }

        session()->forget('branch_draft');

        return provider_route_redirect('provider.branch.index')
            ->with('success', 'Branch has been added.');
    }

    public function edit(Request $request, ProviderBranch $branch)
    {
        $this->authorizeBranch($branch);

        $data = $this->dropdownData();

        return view('provider.pages.branches.form', array_merge($data, [
            'mode' => 'edit',
            'step' => 'branch',
            'branch' => $branch,
            'draft' => [],
        ]));
    }

    public function update(Request $request, ProviderBranch $branch)
    {
        $this->authorizeBranch($branch);

        $validated = $this->validateBranch($request, $branch->id);

        $validated['holidays'] = array_values(array_filter($validated['holidays'] ?? []));

        $currentImages = $branch->images ?: array_values(array_filter([$branch->image]));
        $images = $this->resolveBranchImages($request, $currentImages);

        if (empty($images)) {
            return back()
                ->withInput()
                ->withErrors(['images' => 'Upload minimal 1 foto cabang.']);
        }

        $validated['images'] = $images;
        $validated['image'] = $images[0];
        unset($validated['existing_images']);

        $branch->update($validated);

        return provider_route_redirect('provider.branch.index')
            ->with('success', 'Branch information has been updated. Staff placement can be changed from the Staff menu.');
    }

    public function updateStaff(Request $request, ProviderBranch $branch)
    {
        $this->authorizeBranch($branch);

        $request->validate([
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['integer'],
        ]);

        $staffIds = $this->validStaffIds($request->input('staff_ids', []));

        DB::transaction(function () use ($branch, $staffIds) {
            ProviderStaff::where('provider_id', $this->providerId())
                ->where('branch_id', $branch->id)
                ->update([
                    'branch_id' => null,
                ]);

            if (!empty($staffIds)) {
                ProviderStaff::where('provider_id', $this->providerId())
                    ->whereIn('id', $staffIds)
                    ->update([
                        'branch_id' => $branch->id,
                    ]);
            }
        });

        return provider_route_redirect('provider.branch.index')
            ->with('success', 'Branch staff has been updated.');
    }

    public function toggleStatus(ProviderBranch $branch)
    {
        $this->authorizeBranch($branch);

        $branch->update([
            'status' => $branch->status === 'active' ? 'inactive' : 'active',
        ]);

        return provider_route_redirect('provider.branch.index')
            ->with('success', 'Status cabang berhasil diperbarui.');
    }

    public function destroy(ProviderBranch $branch)
    {
        $this->authorizeBranch($branch);
        abort_if($this->isBranchAccount(), 403, 'Branch accounts cannot delete branches.');

        $hasBookings = $branch->bookings()->exists();
        $hasStaffs = $branch->staffs()->exists();
        $hasServices = $branch->servicesForBranch()->isNotEmpty();

        if ($hasBookings || $hasStaffs || $hasServices) {
            $branch->update(['status' => 'inactive']);
            return provider_route_redirect('provider.branch.index')
                ->with('success', 'Cabang telah dinonaktifkan karena masih terhubung dengan data lain (Pesanan, Staff, atau Layanan).');
        }

        DB::transaction(function () use ($branch) {
            ProviderStaff::where('provider_id', $this->providerId())
                ->where('branch_id', $branch->id)
                ->update([
                    'branch_id' => null,
                ]);

            if ($branch->image) {
                Storage::disk('public')->delete($branch->image);
            }

            foreach ((array) $branch->images as $imagePath) {
                if ($imagePath && ! str_starts_with($imagePath, 'http://') && ! str_starts_with($imagePath, 'https://')) {
                    Storage::disk('public')->delete($imagePath);
                }
            }

            $branch->delete();
        });

        return provider_route_redirect('provider.branch.index')
            ->with('success', 'Cabang berhasil dihapus secara permanen.');
    }

    private function validateBranch(Request $request, ?int $branchId = null): array
    {
        return $request->validate([
            'branch_name' => ['required', 'string', 'max:255'],

            'email' => ['required', 'email', 'max:255'],

            'phone_code' => ['required', 'string', 'max:20'],
            'phone_number' => ['required', 'string', 'max:30'],

            'address' => ['required', 'string'],

            /*
             * Country, State, City sekarang dari API,
             * jadi value-nya adalah string:
             * Indonesia, East Kalimantan, Kota Bontang, dll.
             */
            'country_id' => ['required', 'string', 'max:255'],
            'state_id' => ['required', 'string', 'max:255'],
            'city_id' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'zip_code' => ['required', 'string', 'max:20'],

            'working_start_hour' => ['required', 'date_format:H:i'],
            'working_end_hour' => ['required', 'date_format:H:i'],

            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', 'string', 'max:20'],

            'holidays' => ['nullable', 'array'],
            'holidays.*' => ['nullable', 'date'],

            // Up to 5 gallery photos. `existing_images` carries the paths the user
            // chose to keep when editing; `images` are the newly uploaded files.
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
            'existing_images' => ['nullable', 'array', 'max:5'],
            'existing_images.*' => ['string'],
        ]);
    }

    /**
     * Merge kept + newly uploaded photos (capped at 5), delete removed files and
     * return the final ordered list of stored paths. The first item is the cover.
     *
     * @param  array<int, string>  $currentImages  paths that already exist (draft/branch)
     * @return array<int, string>
     */
    private function resolveBranchImages(Request $request, array $currentImages): array
    {
        $kept = collect($request->input('existing_images', []))
            ->filter(fn ($path) => in_array($path, $currentImages, true))
            ->values();

        $uploaded = collect();
        $remainingSlots = max(0, 5 - $kept->count());

        if ($remainingSlots > 0 && $request->hasFile('images')) {
            foreach (array_slice($request->file('images'), 0, $remainingSlots) as $file) {
                $uploaded->push($file->store('branch-images', 'public'));
            }
        }

        $final = $kept->merge($uploaded)->take(5)->values();

        // Remove files the user dropped (present before, not kept) to avoid orphans.
        collect($currentImages)
            ->reject(fn ($path) => $kept->contains($path))
            ->each(function ($path) {
                if ($path && ! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://')) {
                    Storage::disk('public')->delete($path);
                }
            });

        return $final->all();
    }

    private function dropdownData(): array
    {
        /*
         * Country, State, and City are no longer loaded from the database.
         * The data is loaded from the API through public/provider/js/branch.js.
         */
        $countries = collect();
        $states = collect();
        $cities = collect();

        return compact('countries', 'states', 'cities');
    }

    private function validStaffIds(array $staffIds): array
    {
        return ProviderStaff::where('provider_id', $this->providerId())
            ->when($this->branchId() !== null, fn ($query) => $query->where('branch_id', $this->branchId()))
            ->whereIn('id', $staffIds)
            ->pluck('id')
            ->toArray();
    }

    private function authorizeBranch(ProviderBranch $branch): void
    {
        abort_if($branch->provider_id !== $this->providerId(), 403);
        abort_if($this->branchId() !== null && (int) $branch->id !== $this->branchId(), 403);
    }
}
