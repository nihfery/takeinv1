<?php

use App\Modules\Branch\Presentation\Api\Provider\BranchController as ProviderBranchController;
use App\Modules\Catalog\Presentation\Api\Provider\ServiceController as ProviderServiceController;
use App\Modules\Provider\Presentation\Api\Provider\ProfileController as ProviderProfileController;
use App\Modules\Staff\Presentation\Api\Provider\StaffController as ProviderStaffController;
use App\Modules\Subscription\Presentation\Api\Provider\SubscriptionController as ProviderSubscriptionController;
use Illuminate\Support\Facades\Route;

return static function (): void {
    Route::prefix('provider')->name('api.provider.')->middleware('provider.api')->group(function (): void {
        Route::middleware('provider.api:profile')->group(function (): void {
            Route::get('/profile', [ProviderProfileController::class, 'show'])
                ->name('profile.show');
            Route::get('/profile/documents/{document}', [ProviderProfileController::class, 'document'])
                ->middleware(['provider.api:owner', 'signed'])
                ->whereIn('document', ['ktp', 'nib'])
                ->name('profile.documents.show');
            Route::put('/profile', [ProviderProfileController::class, 'update'])
                ->middleware('throttle:provider-write')
                ->name('profile.update');
            Route::post('/profile/documents', [ProviderProfileController::class, 'updateDocuments'])
                ->middleware('throttle:provider-write')
                ->name('profile.documents');
            Route::put('/profile/password', [ProviderProfileController::class, 'updatePassword'])
                ->middleware('throttle:provider-write')
                ->name('profile.password');
        });

        Route::middleware('provider.api:services')->group(function (): void {
            Route::apiResource('services', ProviderServiceController::class)
                ->middlewareFor(['store', 'update', 'destroy'], 'throttle:provider-write');
            Route::put('/services/{service}/branch', [ProviderServiceController::class, 'updateBranch'])
                ->middleware('throttle:provider-write')
                ->name('services.branch');
            Route::put('/services/{service}/gallery', [ProviderServiceController::class, 'updateGallery'])
                ->middleware('throttle:provider-write')
                ->name('services.gallery');
            Route::patch('/services/{service}/toggle-status', [ProviderServiceController::class, 'toggleStatus'])
                ->middleware('throttle:provider-write')
                ->name('services.toggle-status');
        });

        Route::middleware('provider.api:staffs')->group(function (): void {
            Route::apiResource('staff', ProviderStaffController::class)
                ->parameters(['staff' => 'staff'])
                ->middlewareFor(['store', 'update', 'destroy'], 'throttle:provider-write');
        });

        Route::middleware('provider.api:branch')->group(function (): void {
            Route::apiResource('branches', ProviderBranchController::class)
                ->parameters(['branches' => 'branch'])
                ->middlewareFor(['store', 'update', 'destroy'], 'throttle:provider-write');
            Route::put('/branches/{branch}/staff', [ProviderBranchController::class, 'updateStaff'])
                ->middleware('throttle:provider-write')
                ->name('branches.staff');
            Route::get('/branches/{branch}/preview', [ProviderBranchController::class, 'preview'])
                ->name('branches.preview');
        });

        Route::middleware('provider.api:owner')->group(function (): void {
            Route::get('/subscriptions', [ProviderSubscriptionController::class, 'index'])
                ->name('subscriptions.index');
            Route::post('/subscriptions/plans/{plan}/purchase', [ProviderSubscriptionController::class, 'purchase'])
                ->middleware('throttle:provider-write')
                ->name('subscriptions.purchase');
        });
    });
};
