<?php

namespace App\Modules\Catalog\Application\Queries;

use Carbon\Carbon;

final readonly class PublicBranchSearchCriteria
{
    public function __construct(
        public ?Carbon $bookingDate,
        public mixed $country,
        public mixed $state,
        public mixed $city,
        public mixed $search,
        public mixed $category,
        public int $perPage,
        public bool $requiresInMemoryFiltering,
    ) {
    }
}
