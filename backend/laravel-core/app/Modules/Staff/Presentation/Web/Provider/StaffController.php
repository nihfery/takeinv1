<?php

namespace App\Modules\Staff\Presentation\Web\Provider;

use App\Http\Controllers\Controller;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use App\Modules\Provider\Application\Support\ProviderAccountScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Modules\Subscription\Application\Services\ProviderEntitlementService;

class StaffController extends Controller
{
    private function providerId(): int
    {
        return ProviderAccountScope::providerId(Auth::user());
    }

    private function branchId(): ?int
    {
        return ProviderAccountScope::branchId(Auth::user());
    }

    public function index(Request $request)
    {
        $providerId = $this->providerId();

        $search = trim((string) $request->get('search', ''));
        $perPage = (int) $request->get('per_page', 10);

        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $status = $request->get('status', 'all');

        if (! in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        $branchFilter = $request->get('branch_id', 'all');
        $branchFilter = $branchFilter === null || $branchFilter === '' ? 'all' : $branchFilter;

        if ($branchFilter !== 'all' && ! ctype_digit((string) $branchFilter)) {
            $branchFilter = 'all';
        }

        $categoryFilter = $request->get('category_id', 'all');
        $categoryFilter = $categoryFilter === null || $categoryFilter === '' ? 'all' : $categoryFilter;

        if ($categoryFilter !== 'all' && ! ctype_digit((string) $categoryFilter)) {
            $categoryFilter = 'all';
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortableColumns = ['full_name', 'email', 'phone_number', 'status', 'created_at'];

        if (! in_array($sortBy, $sortableColumns, true)) {
            $sortBy = 'created_at';
        }

        /*
        |--------------------------------------------------------------------------
        | Ambil Category dari table service_categories
        |--------------------------------------------------------------------------
        */

        $categories = ServiceCategory::query()
            ->where('status', 'active')
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Ambil Branch dari table provider_branches berdasarkan provider login
        |--------------------------------------------------------------------------
        */

        $branches = ProviderBranch::query()
            ->where('provider_id', $providerId)
            ->where('status', 'active')
            ->orderBy('branch_name');
        ProviderAccountScope::applyBranchModelScope($branches, $this->branchId());

        $branches = $branches->get();

        /*
        |--------------------------------------------------------------------------
        | Ambil Staff
        |--------------------------------------------------------------------------
        */

        $staffQuery = ProviderStaff::query()
            ->where('provider_id', $providerId)
            ->with([
                'branch:id,provider_id,branch_name,status',
                'category:id,name,status',
                'providerRole:id,role_name,status',
            ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('username', 'like', '%' . $search . '%')
                        ->orWhere('phone_number', 'like', '%' . $search . '%')
                        ->orWhereHas('branch', function ($branchQuery) use ($search) {
                            $branchQuery->where('branch_name', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($branchFilter !== 'all', function ($query) use ($branchFilter) {
                $query->where('branch_id', (int) $branchFilter);
            })
            ->when($categoryFilter !== 'all', function ($query) use ($categoryFilter) {
                $query->where('category_id', (int) $categoryFilter);
            });

        ProviderAccountScope::applyBranchScope($staffQuery, $this->branchId());

        $statusSummaryQuery = clone $staffQuery;

        if ($status !== 'all') {
            $staffQuery->where('status', $status);
        }

        $summaryQuery = clone $staffQuery;

        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'active' => (clone $summaryQuery)->where('status', 'active')->count(),
            'inactive' => (clone $summaryQuery)->where('status', 'inactive')->count(),
            'branches' => (clone $summaryQuery)->whereNotNull('branch_id')->distinct()->count('branch_id'),
        ];

        $statusCounts = [
            'all' => (clone $statusSummaryQuery)->count(),
            'active' => (clone $statusSummaryQuery)->where('status', 'active')->count(),
            'inactive' => (clone $statusSummaryQuery)->where('status', 'inactive')->count(),
        ];

        if ($sortBy === 'full_name') {
            $staffQuery
                ->orderBy('first_name', $sortDirection)
                ->orderBy('last_name', $sortDirection);
        } else {
            $staffQuery->orderBy($sortBy, $sortDirection);
        }

        $staffs = $staffQuery
            ->paginate($perPage)
            ->withQueryString();

        $filters = [
            'status' => $status,
            'search' => $search,
            'per_page' => $perPage,
            'branch_id' => $branchFilter,
            'category_id' => $categoryFilter,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];

        $hasActiveFilters = $search !== ''
            || $status !== 'all'
            || $branchFilter !== 'all'
            || $categoryFilter !== 'all'
            || $perPage !== 10
            || $sortBy !== 'created_at'
            || $sortDirection !== 'desc';

        return view('provider.pages.staff.index', compact(
            'staffs',
            'search',
            'perPage',
            'filters',
            'summary',
            'statusCounts',
            'hasActiveFilters',
            'sortBy',
            'sortDirection',
            'categories',
            'branches'
        ));
    }

    public function store(Request $request)
    {
        $validated = $this->validatedStaffData($request);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('provider/staffs', 'public');
        }

        $validated['role'] = 'staff';

        $entitlement = null;

        \Illuminate\Support\Facades\DB::transaction(function () use (&$validated, &$entitlement) {
            $user = \Illuminate\Support\Facades\Auth::user();
            $providerOwnerId = \App\Modules\Provider\Application\Support\ProviderMenuAccess::providerOwnerId($user);

            \App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile::where('user_id', $providerOwnerId)->lockForUpdate()->first();

            $entitlement = app(\App\Modules\Subscription\Application\Services\ProviderEntitlementService::class)->checkResourceLimit($user, 'staff');
            if (!$entitlement['allowed']) {
                return;
            }

            $validated['provider_id'] = $providerOwnerId;
            ProviderStaff::create($validated);
        });

        if ($entitlement && !$entitlement['allowed']) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $entitlement['reason']
                ], 403);
            }
            return provider_route_redirect('provider.staffs.index')
                ->with('error', $entitlement['reason']);
        }

        return provider_route_redirect('provider.staffs.index')
            ->with('success', 'Staff member has been added.');
    }

    public function update(Request $request, ProviderStaff $staff)
    {
        $this->authorizeProviderStaff($staff);

        $validated = $this->validatedStaffData($request, $staff);

        if ($request->hasFile('image')) {
            if ($staff->image && Storage::disk('public')->exists($staff->image)) {
                Storage::disk('public')->delete($staff->image);
            }

            $validated['image'] = $request->file('image')->store('provider/staffs', 'public');
        }

        if (! $staff->provider_role_id) {
            $validated['role'] = 'staff';
        }

        $staff->update($validated);

        return provider_route_redirect('provider.staffs.index')
            ->with('success', 'Staff member has been updated.');
    }

    public function toggleStatus(ProviderStaff $staff)
    {
        $this->authorizeProviderStaff($staff);

        $staff->update([
            'status' => $staff->status === 'active' ? 'inactive' : 'active',
        ]);

        return provider_route_redirect('provider.staffs.index')
            ->with('success', 'Status staff berhasil diperbarui.');
    }

    public function destroy(ProviderStaff $staff)
    {
        $this->authorizeProviderStaff($staff);

        $hasBookings = $staff->bookings()->exists();
        if ($hasBookings) {
            $staff->update(['status' => 'inactive']);
            return provider_route_redirect('provider.staffs.index')
                ->with('success', 'Staff telah dinonaktifkan karena terkait dengan pesanan.');
        }

        if ($staff->image && Storage::disk('public')->exists($staff->image)) {
            Storage::disk('public')->delete($staff->image);
        }

        $staff->delete();

        return provider_route_redirect('provider.staffs.index')
            ->with('success', 'Staff berhasil dihapus secara permanen.');
    }

    private function validatedStaffData(Request $request, ?ProviderStaff $staff = null): array
    {
        $providerId = $this->providerId();

        $validated = $request->validate([
            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'extensions:jpg,jpeg,png,webp',
                'max:4096',
            ],

            'first_name' => [
                'required',
                'string',
                'max:255',
            ],

            'last_name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('provider_staffs', 'email')
                    ->where(fn ($query) => $query->where('provider_id', $providerId))
                    ->ignore($staff?->id),
            ],

            'username' => [
                'nullable',
                'string',
                'max:255',
            ],

            'country_code' => [
                'nullable',
                'string',
                'max:20',
            ],

            'phone_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'gender' => [
                'nullable',
                Rule::in(['male', 'female', 'other']),
            ],

            'date_of_birth' => [
                'nullable',
                'date',
            ],

            'address' => [
                'nullable',
                'string',
            ],

            'country_id' => [
                'nullable',
                'string',
                'max:255',
            ],

            'state_id' => [
                'nullable',
                'string',
                'max:255',
            ],

            'city_id' => [
                'nullable',
                'string',
                'max:255',
            ],

            'postal_code' => [
                'nullable',
                'string',
                'max:255',
            ],

            'bio' => [
                'nullable',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Category dari table service_categories
            |--------------------------------------------------------------------------
            */

            'category_id' => [
                'required',
                Rule::exists('service_categories', 'id')
                    ->where(fn ($query) => $query->where('status', 'active')),
            ],

            /*
            |--------------------------------------------------------------------------
            | Branch dari table provider_branches
            | Hanya branch milik provider yang sedang login
            |--------------------------------------------------------------------------
            */

            'branch_id' => [
                'required',
                Rule::exists('provider_branches', 'id')
                    ->where(function ($query) use ($providerId) {
                        $query->where('provider_id', $providerId)
                            ->where('status', 'active');

                        ProviderAccountScope::applyBranchScope($query, $this->branchId(), 'id');
                    }),
            ],

            'status' => [
                'nullable',
                Rule::in(['active', 'inactive']),
            ],
        ], [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email format is invalid.',
            'email.unique' => 'Staff email is already in use.',
            'category_id.required' => 'Category is required.',
            'category_id.exists' => 'Category is invalid.',
            'branch_id.required' => 'Branch is required.',
            'branch_id.exists' => 'Branch is invalid or does not belong to this provider.',
        ]);

        if ($this->branchId() !== null) {
            $validated['branch_id'] = $this->branchId();
        }

        return $validated;
    }

    private function authorizeProviderStaff(ProviderStaff $staff): void
    {
        if ((int) $staff->provider_id !== $this->providerId()) {
            abort(403, 'Access denied.');
        }

        if ($this->branchId() !== null && (int) $staff->branch_id !== $this->branchId()) {
            abort(403, 'Access denied.');
        }
    }
}
