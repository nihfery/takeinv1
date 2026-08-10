<?php

use App\Modules\Booking\Presentation\Api\Admin\BookingController as AdminBookingController;
use App\Modules\Catalog\Presentation\Api\Admin\ServiceCategoryController as AdminServiceCategoryController;
use App\Modules\Catalog\Presentation\Api\Admin\ServiceController as AdminServiceController;
use App\Modules\Customer\Presentation\Api\Admin\CustomerController as AdminCustomerController;
use App\Modules\Promotion\Presentation\Api\Admin\CouponController as AdminCouponController;
use App\Modules\Provider\Presentation\Api\Admin\ProviderController as AdminProviderController;
use Illuminate\Support\Facades\Route;

return static function (): void {
    Route::prefix('admin')->name('api.admin.')->group(function (): void {
        Route::get('/bookings', [AdminBookingController::class, 'index'])
            ->name('bookings.index');
        Route::get('/bookings/{booking}', [AdminBookingController::class, 'show'])
            ->name('bookings.show');
        Route::patch('/bookings/{booking}/status', [AdminBookingController::class, 'updateStatus'])
            ->name('bookings.status');

        Route::apiResource('service-categories', AdminServiceCategoryController::class)
            ->parameters(['service-categories' => 'serviceCategory']);
        Route::patch('/service-categories/{serviceCategory}/toggle-featured', [AdminServiceCategoryController::class, 'toggleFeatured'])
            ->name('service-categories.toggle-featured');
        Route::patch('/service-categories/{serviceCategory}/toggle-status', [AdminServiceCategoryController::class, 'toggleStatus'])
            ->name('service-categories.toggle-status');

        Route::get('/services', [AdminServiceController::class, 'index'])
            ->name('services.index');
        Route::get('/services/{service}', [AdminServiceController::class, 'show'])
            ->name('services.show');
        Route::patch('/services/{service}/toggle-status', [AdminServiceController::class, 'toggleStatus'])
            ->name('services.toggle-status');

        Route::apiResource('coupons', AdminCouponController::class);

        Route::get('/providers', [AdminProviderController::class, 'index'])
            ->name('providers.index');
        Route::get('/providers/{provider}', [AdminProviderController::class, 'show'])
            ->name('providers.show');
        Route::get('/providers/{provider}/documents/{document}', [AdminProviderController::class, 'document'])
            ->middleware('signed')
            ->whereIn('document', ['ktp', 'nib'])
            ->name('providers.documents.show');
        Route::patch('/providers/{provider}/toggle-status', [AdminProviderController::class, 'toggleStatus'])
            ->name('providers.toggle-status');
        Route::patch('/providers/{provider}/document-status', [AdminProviderController::class, 'updateDocumentStatus'])
            ->name('providers.document-status');
        Route::delete('/providers/{provider}', [AdminProviderController::class, 'destroy'])
            ->name('providers.destroy');

        Route::get('/customers', [AdminCustomerController::class, 'index'])
            ->name('customers.index');
        Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])
            ->name('customers.show');
        Route::patch('/customers/{customer}/toggle-status', [AdminCustomerController::class, 'toggleStatus'])
            ->name('customers.toggle-status');
        Route::delete('/customers/{customer}', [AdminCustomerController::class, 'destroy'])
            ->name('customers.destroy');
    });
};
