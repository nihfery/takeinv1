<?php

namespace App\Modules\Promotion\Presentation\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Promotion\Infrastructure\Persistence\Models\Coupon;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $coupons = Coupon::query()
            ->when($request->query('tab', 'valid') === 'expired', fn ($query) => $query->whereDate('end_date', '<', now()->toDateString()))
            ->when($request->query('tab', 'valid') !== 'expired', fn ($query) => $query->where('status', 'active')->whereDate('end_date', '>=', now()->toDateString()))
            ->when($request->query('search'), function ($query, $search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('product_type', 'like', "%{$search}%")
                    ->orWhere('coupon_type', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json($coupons);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $coupon = Coupon::create($this->validateCoupon($request));

        return response()->json(['message' => 'Coupon berhasil ditambahkan.', 'data' => $coupon], 201);
    }

    public function show(Request $request, Coupon $coupon): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        return response()->json(['data' => $coupon]);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $coupon->update($this->validateCoupon($request, $coupon->id));

        return response()->json(['message' => 'Coupon berhasil diperbarui.', 'data' => $coupon->refresh()]);
    }

    public function destroy(Request $request, Coupon $coupon): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $coupon->delete();

        return response()->json(['message' => 'Coupon berhasil dihapus.']);
    }

    private function validateCoupon(Request $request, ?int $couponId = null): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', Rule::unique('coupons', 'code')->ignore($couponId)],
            'product_type' => ['required', Rule::in(['all', 'service', 'category'])],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
            'coupon_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'coupon_value' => ['required', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if ($validated['product_type'] === 'all') {
            $validated['product_ids'] = null;
        } else {
            $productIds = array_values(array_unique($validated['product_ids'] ?? []));

            if ($productIds === []) {
                throw ValidationException::withMessages(['product_ids' => ucfirst($validated['product_type']) . ' wajib dipilih.']);
            }

            $this->validateProductIds($validated['product_type'], $productIds);
            $validated['product_ids'] = $productIds;
        }

        $validated['status'] = $validated['status'] ?? 'active';

        return $validated;
    }

    private function validateProductIds(string $productType, array $productIds): void
    {
        $count = match ($productType) {
            'service' => Service::whereIn('id', $productIds)->count(),
            'category' => ServiceCategory::whereIn('id', $productIds)->count(),
            default => 0,
        };

        if ($count !== count($productIds)) {
            throw ValidationException::withMessages(['product_ids' => 'Data master yang dipilih tidak valid.']);
        }
    }
}
