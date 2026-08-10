<?php

namespace App\Modules\Provider\Application\Support;

use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Auth;

class ProviderAccountScope
{
    public static function providerId(?User $user = null): int
    {
        $user ??= Auth::user();

        if (! $user || $user->role !== 'provider') {
            abort(403, 'Akses provider ditolak.');
        }

        return ProviderMenuAccess::providerOwnerId($user);
    }

    public static function branchId(?User $user = null): ?int
    {
        $user ??= Auth::user();

        if (! $user || $user->role !== 'provider' || ProviderMenuAccess::isProviderOwner($user)) {
            return null;
        }

        return filled($user->branch_id) ? (int) $user->branch_id : -1;
    }

    public static function isBranchAccount(?User $user = null): bool
    {
        return self::branchId($user) !== null;
    }

    public static function applyBranchScope($query, ?int $branchId, string $column = 'branch_id')
    {
        return $branchId !== null ? $query->where($column, $branchId) : $query;
    }

    public static function applyBranchModelScope($query, ?int $branchId)
    {
        return $branchId !== null ? $query->whereKey($branchId) : $query;
    }

    public static function applyServiceBranchScope($query, ?int $branchId)
    {
        if ($branchId === null) {
            return $query;
        }

        if ($branchId < 1) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($serviceQuery) use ($branchId) {
            $serviceQuery
                ->whereNull('branch_ids')
                ->orWhereJsonLength('branch_ids', 0)
                ->orWhereJsonContains('branch_ids', $branchId)
                ->orWhereJsonContains('branch_ids', (string) $branchId);
        });
    }

    public static function serviceBelongsToBranch(Service $service, ?int $branchId): bool
    {
        if ($branchId === null) {
            return true;
        }

        if ($branchId < 1) {
            return false;
        }

        $branchIds = $service->branch_ids;

        if (empty($branchIds)) {
            return true;
        }

        return in_array($branchId, array_map('intval', (array) $branchIds), true);
    }
}
