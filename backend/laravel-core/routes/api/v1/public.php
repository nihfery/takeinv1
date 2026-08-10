<?php

use App\Modules\Catalog\Presentation\Api\Public\PublicCatalogController;
use App\Modules\Promotion\Presentation\Api\Public\CouponValidationController;
use Illuminate\Support\Facades\Route;

return [
    'catalog' => static function (): void {
        Route::middleware('throttle:search')->group(function (): void {
            Route::get('/categories', [PublicCatalogController::class, 'categories'])
                ->name('api.categories.index');
            Route::get('/locations', [PublicCatalogController::class, 'locations'])
                ->name('api.locations.index');
            Route::get('/reviews', [PublicCatalogController::class, 'reviews'])
                ->name('api.reviews.index');
            Route::get('/branches', [PublicCatalogController::class, 'branches'])
                ->name('api.branches.index');
            Route::get('/branches/{branch}/services', [PublicCatalogController::class, 'branchServices'])
                ->name('api.branches.services');
            Route::get('/branches/{branch}/reviews', [PublicCatalogController::class, 'branchReviews'])
                ->name('api.branches.reviews');
            Route::get('/branches/{branch}/staff', [PublicCatalogController::class, 'branchStaff'])
                ->name('api.branches.staff');
            Route::get('/branches/{branch}', [PublicCatalogController::class, 'branch'])
                ->name('api.branches.show');
            Route::get('/staff/{staff}', [PublicCatalogController::class, 'staff'])
                ->name('api.staff.show');
            Route::get('/services', [PublicCatalogController::class, 'services'])
                ->name('api.services.index');
            Route::get('/services/{service}', [PublicCatalogController::class, 'service'])
                ->name('api.services.show');
            Route::get('/providers', [PublicCatalogController::class, 'providers'])
                ->name('api.providers.index');
            Route::get('/coupons', [CouponValidationController::class, 'index'])
                ->name('api.coupons.index');
        });
    },
    'coupon-validation' => static function (): void {
        Route::post('/coupons/validate', [CouponValidationController::class, 'validate'])
            ->middleware('throttle:coupon-validation')
            ->name('api.coupons.validate');
    },
];
