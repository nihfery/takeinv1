<?php

namespace App\Modules\Catalog\Application\Queries\Filters;

use Illuminate\Database\Eloquent\Builder;

final class PublicProviderEligibilityFilter
{
    public function apply(Builder $query): Builder
    {
        return $query->where('role', 'provider')
            ->whereHas('providerProfile', fn (Builder $profileQuery) => $profileQuery
                ->where('status', 'active')
                ->where('document_status', 'verified'));
    }
}
