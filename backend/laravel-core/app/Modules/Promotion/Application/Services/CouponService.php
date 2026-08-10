<?php

namespace App\Modules\Promotion\Application\Services;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Promotion\Infrastructure\Persistence\Models\Coupon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public const TAX_RATE = 0.05;

    public function priceSummary(
        Collection $services,
        ?string $couponCode = null,
        int $quantity = 1,
        bool $lockForUpdate = false
    ): array {
        $quantity = max(1, $quantity);
        $subtotal = round((float) $services->sum(fn (Service $service) => (float) ($service->price ?? 0)) * $quantity, 2);
        $coupon = null;
        $eligibleSubtotal = 0.0;
        $discountAmount = 0.0;

        if (filled($couponCode)) {
            $coupon = $this->validCouponForServices($couponCode, $services, $lockForUpdate);
            $eligibleSubtotal = $this->eligibleSubtotal($coupon, $services) * $quantity;
            $discountAmount = $this->discountAmount($coupon, $eligibleSubtotal);
        }

        $afterDiscount = max(0, round($subtotal - $discountAmount, 2));
        $taxAmount = round($afterDiscount * self::TAX_RATE, 2);
        $payableAmount = round($afterDiscount + $taxAmount, 2);

        return [
            'coupon' => $coupon,
            'coupon_payload' => $coupon ? $this->couponPayload($coupon) : null,
            'subtotal' => $subtotal,
            'eligible_subtotal' => round($eligibleSubtotal, 2),
            'discount_amount' => $discountAmount,
            'after_discount' => $afterDiscount,
            'tax_rate' => self::TAX_RATE,
            'tax_amount' => $taxAmount,
            'payable_amount' => $payableAmount,
        ];
    }

    public function validCouponForServices(
        string $couponCode,
        Collection $services,
        bool $lockForUpdate = false
    ): Coupon {
        $code = trim($couponCode);

        if ($code === '') {
            throw ValidationException::withMessages([
                'coupon_code' => 'Kode voucher wajib diisi.',
            ]);
        }

        $query = Coupon::query()
            ->whereRaw('LOWER(code) = ?', [strtolower($code)]);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $coupon = $query->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Voucher tidak ditemukan.',
            ]);
        }

        if ($coupon->status !== 'active') {
            throw ValidationException::withMessages([
                'coupon_code' => 'Voucher sedang tidak aktif.',
            ]);
        }

        $today = now()->toDateString();

        if ($coupon->start_date && $coupon->start_date->toDateString() > $today) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Voucher belum bisa digunakan.',
            ]);
        }

        if ($coupon->end_date && $coupon->end_date->toDateString() < $today) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Voucher sudah kedaluwarsa.',
            ]);
        }

        if ($coupon->quantity !== null && (int) $coupon->used_count >= (int) $coupon->quantity) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Kuota voucher sudah habis.',
            ]);
        }

        if ($this->eligibleSubtotal($coupon, $services) <= 0) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Voucher tidak berlaku untuk layanan yang dipilih.',
            ]);
        }

        return $coupon;
    }

    public function couponPayload(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'product_type' => $coupon->product_type,
            'coupon_type' => $coupon->coupon_type,
            'coupon_value' => (float) $coupon->coupon_value,
            'remaining_quantity' => $coupon->quantity === null
                ? null
                : max(0, (int) $coupon->quantity - (int) $coupon->used_count),
            'end_date' => $coupon->end_date?->toDateString(),
        ];
    }

    private function eligibleSubtotal(Coupon $coupon, Collection $services): float
    {
        if ($coupon->product_type === 'all') {
            return (float) $services->sum(fn (Service $service) => (float) ($service->price ?? 0));
        }

        $productIds = collect($coupon->product_ids ?: [])->map(fn ($id) => (int) $id)->all();

        if ($coupon->product_type === 'service') {
            return (float) $services
                ->filter(fn (Service $service) => in_array((int) $service->id, $productIds, true))
                ->sum(fn (Service $service) => (float) ($service->price ?? 0));
        }

        if ($coupon->product_type === 'category') {
            return (float) $services
                ->filter(fn (Service $service) => in_array((int) $service->category_id, $productIds, true))
                ->sum(fn (Service $service) => (float) ($service->price ?? 0));
        }

        return 0.0;
    }

    private function discountAmount(Coupon $coupon, float $eligibleSubtotal): float
    {
        $value = max(0, (float) $coupon->coupon_value);
        $amount = $coupon->coupon_type === 'percentage'
            ? $eligibleSubtotal * min($value, 100) / 100
            : $value;

        return round(min($eligibleSubtotal, $amount), 2);
    }
}
