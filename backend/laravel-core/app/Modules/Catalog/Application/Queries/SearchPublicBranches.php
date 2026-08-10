<?php

namespace App\Modules\Catalog\Application\Queries;

use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Application\Queries\Filters\PublicProviderEligibilityFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class SearchPublicBranches
{
    public function __construct(
        private readonly PublicProviderEligibilityFilter $providerEligibility,
    ) {}

    public function handle(PublicBranchSearchCriteria $criteria): Collection|LengthAwarePaginator
    {
        $query = ProviderBranch::query()
            ->with([
                'provider:id,name,email',
                'provider.providerProfile:user_id,status,document_status,image',
            ])
            ->when($criteria->bookingDate, fn (Builder $query) => $query->with('staffs.schedules'))
            ->withCount(['staffs' => fn (Builder $query) => $query->where('status', 'active')])
            ->withCount('branchReviews')
            ->withAvg('branchReviews', 'rating')
            ->where('status', 'active')
            ->whereHas('provider', fn (Builder $providerQuery) => $this->providerEligibility->apply($providerQuery))
            ->when($criteria->country, fn (Builder $query, $country) => $query->where('country_id', $country))
            ->when($criteria->state, fn (Builder $query, $state) => $query->where('state_id', $state))
            ->when($criteria->city, fn (Builder $query, $city) => $query->where('city_id', $city))
            ->when($criteria->search, fn (Builder $query, $search) => $this->applySearch($query, $search))
            ->when($criteria->category, fn (Builder $query, $category) => $this->applyCategory($query, $category))
            ->orderBy('city_id')
            ->orderBy('branch_name');

        return $criteria->requiresInMemoryFiltering
            ? $query->get()
            : $query->paginate($criteria->perPage);
    }

    private function applySearch(Builder $query, string $search): void
    {
        $terms = collect(preg_split('/[,]+/', $search))
            ->map(fn ($term) => trim((string) $term))
            ->filter()
            ->values();

        if ($terms->isEmpty()) {
            $terms = collect([trim($search)])->filter();
        }

        $terms->each(function (string $term) use ($query): void {
            $query->where(function (Builder $nested) use ($term): void {
                $nested->where('branch_name', 'like', "%{$term}%")
                    ->orWhere('address', 'like', "%{$term}%")
                    ->orWhere('city_id', 'like', "%{$term}%")
                    ->orWhere('state_id', 'like', "%{$term}%")
                    ->orWhere('country_id', 'like', "%{$term}%")
                    ->orWhereHas('provider', fn (Builder $providerQuery) => $providerQuery->where('name', 'like', "%{$term}%"));
            });
        });
    }

    private function applyCategory(Builder $query, mixed $category): void
    {
        $query->whereHas('provider.services', function (Builder $serviceQuery) use ($category): void {
            $serviceQuery->publiclyCategorized()
                ->where('status', 'active')
                ->whereHas('serviceCategory', fn (Builder $categoryQuery) => $categoryQuery
                    ->where(fn (Builder $matchQuery) => $matchQuery
                        ->where('name', $category)
                        ->orWhere('slug', $category)
                        ->orWhereHas('parent', fn (Builder $parentQuery) => $parentQuery
                            ->where(fn (Builder $parentMatchQuery) => $parentMatchQuery
                                ->where('name', $category)
                                ->orWhere('slug', $category)))));
        });
    }
}
