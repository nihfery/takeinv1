<?php

namespace App\Modules\Promotion\Presentation\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Promotion\Infrastructure\Persistence\Models\Coupon;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use App\Modules\Promotion\Application\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponValidationController extends ApiController
{
    public function __construct(private readonly CouponService $coupons)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $perPage = min(max((int) $request->query('per_page', 12), 1), 50);

        $coupons = Coupon::query()
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where(function ($query) {
                $query->whereNull('quantity')
                    ->orWhereColumn('used_count', '<', 'quantity');
            })
            ->orderBy('end_date')
            ->latest()
            ->paginate($perPage);

        $coupons->setCollection(
            $coupons->getCollection()->map(fn (Coupon $coupon) => $this->publicCouponPayload($coupon))
        );

        return response()->json($coupons);
    }

    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'coupon_code' => ['required', 'string', 'max:100'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'integer', Rule::exists('services', 'id')],
        ]);

        $requestedServiceIds = collect($validated['service_ids'])
            ->map(fn ($id) => (int) $id)
            ->values();
        $uniqueServiceIds = $requestedServiceIds->unique()->values();

        $servicesById = Service::query()
            ->with('serviceCategory')
            ->whereIn('id', $uniqueServiceIds->all())
            ->where('status', 'active')
            ->get()
            ->keyBy(fn (Service $service) => (int) $service->id);

        abort_if($servicesById->count() !== $uniqueServiceIds->count(), 422, 'Ada layanan yang tidak valid.');

        // Preserve repeated service IDs. Group bookings can contain the same
        // service for multiple participants and finalization prices each one.
        $services = $requestedServiceIds
            ->map(fn (int $serviceId) => $servicesById->get($serviceId))
            ->values();

        $summary = $this->coupons->priceSummary($services, $validated['coupon_code']);

        return response()->json([
            'message' => 'Voucher berhasil diterapkan.',
            'data' => [
                'coupon' => $summary['coupon_payload'],
                'subtotal' => $summary['subtotal'],
                'eligible_subtotal' => $summary['eligible_subtotal'],
                'discount_amount' => $summary['discount_amount'],
                'after_discount' => $summary['after_discount'],
                'tax_rate' => $summary['tax_rate'],
                'tax_amount' => $summary['tax_amount'],
                'payable_amount' => $summary['payable_amount'],
            ],
        ]);
    }

    private function publicCouponPayload(Coupon $coupon): array
    {
        return [
            ...$this->coupons->couponPayload($coupon),
            'start_date' => $coupon->start_date?->toDateString(),
            'product_label' => $this->productLabel($coupon),
        ];
    }

    private function productLabel(Coupon $coupon): string
    {
        if ($coupon->product_type === 'all') {
            return 'All services';
        }

        $ids = collect($coupon->product_ids ?: [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return $coupon->product_type === 'category' ? 'Specific categories' : 'Specific services';
        }

        if ($coupon->product_type === 'category') {
            $names = ServiceCategory::query()
                ->whereIn('id', $ids)
                ->orderBy('name')
                ->limit(3)
                ->pluck('name')
                ->all();

            return $names ? implode(', ', $names) : 'Kategori tertentu';
        }

        $names = Service::query()
            ->whereIn('id', $ids)
            ->orderBy('title')
            ->limit(3)
            ->pluck('title')
            ->all();

        return $names ? implode(', ', $names) : 'Specific services';
    }
}
