<?php

namespace App\Modules\Promotion\Presentation\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Promotion\Infrastructure\Persistence\Models\Coupon;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $tab = $request->get('tab', 'all');
        $search = trim((string) $request->get('search', ''));
        $productType = $request->get('product_type', 'any');
        $couponType = $request->get('coupon_type', 'all');
        $date = $request->get('date');
        $perPage = (int) $request->get('per_page', 10);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = strtolower((string) $request->get('sort_direction', 'desc'));

        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        if (! in_array($tab, ['all', 'valid', 'inactive', 'expired'], true)) {
            $tab = 'all';
        }

        if (! in_array($productType, ['any', 'all', 'service', 'category'], true)) {
            $productType = 'any';
        }

        if (! in_array($couponType, ['all', 'percentage', 'fixed'], true)) {
            $couponType = 'all';
        }

        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $sortMap = [
            'code' => 'code',
            'product_type' => 'product_type',
            'coupon_type' => 'coupon_type',
            'coupon_value' => 'coupon_value',
            'used_count' => 'used_count',
            'end_date' => 'end_date',
            'status' => 'status',
            'created_at' => 'created_at',
        ];

        if (! array_key_exists($sortBy, $sortMap)) {
            $sortBy = 'created_at';
        }

        $today = now()->toDateString();
        $query = Coupon::query();

        if ($tab === 'expired') {
            $query->whereDate('end_date', '<', $today);
        } elseif ($tab === 'inactive') {
            $query->where('status', 'inactive');
        } elseif ($tab === 'valid') {
            $query->where('status', 'active')
                ->whereDate('end_date', '>=', $today);
        }

        if ($productType !== 'any') {
            $query->where('product_type', $productType);
        }

        if ($couponType !== 'all') {
            $query->where('coupon_type', $couponType);
        }

        if (! empty($date)) {
            $query->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%' . $search . '%')
                    ->orWhere('product_type', 'like', '%' . $search . '%')
                    ->orWhere('coupon_type', 'like', '%' . $search . '%')
                    ->orWhere('coupon_value', 'like', '%' . $search . '%');
            });
        }

        $query->orderBy($sortMap[$sortBy], $sortDirection);

        if ($sortBy !== 'code') {
            $query->orderBy('code');
        }

        $coupons = $query->paginate($perPage)->withQueryString();
        $summary = [
            'total' => Coupon::query()->count(),
            'valid' => Coupon::query()
                ->where('status', 'active')
                ->whereDate('end_date', '>=', $today)
                ->count(),
            'expired' => Coupon::query()
                ->whereDate('end_date', '<', $today)
                ->count(),
            'redeemed' => (int) Coupon::query()->sum('used_count'),
        ];
        $filters = [
            'tab' => $tab,
            'product_type' => $productType,
            'coupon_type' => $couponType,
            'date' => $date,
            'search' => $search,
            'per_page' => $perPage,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];
        $tabs = [
            'all' => 'All Coupon',
            'valid' => 'Valid',
            'inactive' => 'Inactive',
            'expired' => 'Expired',
        ];
        $hasActiveFilters = $tab !== 'all'
            || $search !== ''
            || $productType !== 'any'
            || $couponType !== 'all'
            || ! empty($date)
            || $perPage !== 10
            || $sortBy !== 'created_at'
            || $sortDirection !== 'desc';

        return view('admin.coupons.index', compact(
            'coupons',
            'filters',
            'hasActiveFilters',
            'summary',
            'tabs',
            'tab',
            'search',
            'perPage',
            'sortBy',
            'sortDirection'
        ));
    }

    public function create()
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        return view('admin.coupons.create', array_merge([
            'coupon' => new Coupon(),
            'mode' => 'create',
        ], $this->masterData()));
    }

    public function store(Request $request)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $validated = $this->validateCoupon($request);

        Coupon::create($validated);

        return redirect()
            ->route('admin.coupons.index')
            ->with('success', 'Coupon has been added.');
    }

    public function edit(Coupon $coupon)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        return view('admin.coupons.edit', array_merge([
            'coupon' => $coupon,
            'mode' => 'edit',
        ], $this->masterData()));
    }

    public function update(Request $request, Coupon $coupon)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $validated = $this->validateCoupon($request, $coupon->id);

        $coupon->update($validated);

        return redirect()
            ->route('admin.coupons.index')
            ->with('success', 'Coupon has been updated.');
    }

    public function destroy(Coupon $coupon)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $coupon->delete();

        return back()->with('success', 'Coupon has been deleted.');
    }

    private function validateCoupon(Request $request, ?int $couponId = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('coupons', 'code')->ignore($couponId),
            ],
            'product_type' => ['required', Rule::in(['all', 'service', 'category'])],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
            'coupon_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'coupon_value' => ['required', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ], [
            'code.required' => 'Code is required.',
            'code.unique' => 'Coupon code is already in use.',
            'product_type.required' => 'Product type is required.',
            'coupon_type.required' => 'Coupon type is required.',
            'coupon_value.required' => 'Coupon value is required.',
            'start_date.required' => 'Start date is required.',
            'end_date.required' => 'End date is required.',
            'end_date.after_or_equal' => 'End date must be the same as or after the start date.',
        ]);

        $productType = $validated['product_type'];
        $productIds = $validated['product_ids'] ?? [];

        if ($productType === 'all') {
            $validated['product_ids'] = null;
        } else {
            if (empty($productIds)) {
                throw ValidationException::withMessages([
                    'product_ids' => ucfirst($productType) . ' is required.',
                ]);
            }

            $this->validateProductIds($productType, $productIds);

            $validated['product_ids'] = array_values(array_unique($productIds));
        }

        $validated['status'] = $request->get('status', 'active');

        return $validated;
    }

    private function validateProductIds(string $productType, array $productIds): void
    {
        $count = 0;

        if ($productType === 'service') {
            $count = Service::whereIn('id', $productIds)->count();
        }

        if ($productType === 'category') {
            $count = ServiceCategory::whereIn('id', $productIds)->count();
        }

        if ($count !== count(array_unique($productIds))) {
            throw ValidationException::withMessages([
                'product_ids' => 'Selected master data is invalid.',
            ]);
        }
    }

    private function masterData(): array
    {
        return [
            'services' => Service::query()
                ->orderBy('title')
                ->get(['id', 'title']),

            'categories' => ServiceCategory::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}
