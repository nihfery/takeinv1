<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\UserController;
use App\Modules\Booking\Presentation\Web\Admin\BookingController;
use App\Modules\Booking\Presentation\Web\Admin\CalendarController;
use App\Modules\Catalog\Presentation\Web\Admin\ServiceCategoryController;
use App\Modules\Catalog\Presentation\Web\Admin\ServiceController;
use App\Modules\Identity\Presentation\Web\Auth\UnifiedLoginController;
use App\Modules\Notification\Presentation\Web\NotificationController;
use App\Modules\Promotion\Presentation\Web\Admin\CouponController;
use App\Modules\Provider\Presentation\Web\Admin\ProviderController;
use App\Modules\Provider\Presentation\Web\ProviderDocumentController;
use App\Modules\Support\Presentation\Web\SupportChatController;
use Illuminate\Support\Facades\Route;

return static function (): void {
    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')
        ->name('admin.')
        ->group(function () {
            /*
            |--------------------------------------------------------------------------
            | Admin Auth
            |--------------------------------------------------------------------------
            */

            Route::middleware('guest:admin')->group(function () {
                Route::get('/login', [UnifiedLoginController::class, 'showLoginForm'])
                    ->name('login');

                Route::post('/login', [UnifiedLoginController::class, 'login'])
                    ->middleware('throttle:login')
                    ->name('login.post');
            });

            /*
            |--------------------------------------------------------------------------
            | Admin Protected Routes
            |--------------------------------------------------------------------------
            */

            Route::middleware(['auth:admin', 'prevent-back-history'])->group(function () {
                Route::get('/notifications', [NotificationController::class, 'index'])
                    ->name('notifications.index');

                Route::post('/notifications/read', [NotificationController::class, 'markAllRead'])
                    ->name('notifications.read-all');

                Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
                    ->name('notifications.read');

                Route::post('/logout', [UnifiedLoginController::class, 'logout'])
                    ->name('logout');

                Route::get('/dashboard', [DashboardController::class, 'index'])
                    ->name('dashboard');

                Route::get('/dashboard/export/{format}', [DashboardController::class, 'export'])
                    ->whereIn('format', ['pdf', 'csv', 'excel'])
                    ->name('dashboard.export');

                /*
                |--------------------------------------------------------------------------
                | Admin Bookings & Calendar
                |--------------------------------------------------------------------------
                */

                Route::get('/bookings', [BookingController::class, 'index'])
                    ->name('bookings.index');

                Route::get('/calendar', [CalendarController::class, 'index'])
                    ->name('calendar.index');

                Route::get('/chat', [SupportChatController::class, 'adminIndex'])
                    ->name('chat.index');

                Route::get('/chat/{thread}/messages', [SupportChatController::class, 'adminMessages'])
                    ->name('chat.messages.index');

                Route::post('/chat/{thread}/messages', [SupportChatController::class, 'adminStore'])
                    ->name('chat.messages.store');

                Route::post('/chat/{thread}/read', [SupportChatController::class, 'adminRead'])
                    ->name('chat.read');

                Route::post('/chat/{thread}/ticket/end', [SupportChatController::class, 'adminTicketEnd'])
                    ->name('chat.ticket.end');

                Route::get('/tickets', [SupportChatController::class, 'adminTicketsIndex'])
                    ->name('tickets.index');

                Route::post('/tickets/{thread}/approve', [SupportChatController::class, 'adminTicketApprove'])
                    ->name('tickets.approve');

                Route::post('/tickets/{thread}/reject', [SupportChatController::class, 'adminTicketReject'])
                    ->name('tickets.reject');

                /*
                |--------------------------------------------------------------------------
                | Admin Services
                |--------------------------------------------------------------------------
                */

                Route::get('/services', [ServiceController::class, 'index'])
                    ->name('services.index');

                Route::patch('/services/{service}/toggle-status', [ServiceController::class, 'toggleStatus'])
                    ->name('services.toggle-status');

                /*
                |--------------------------------------------------------------------------
                | Admin Service Categories
                |--------------------------------------------------------------------------
                */

                Route::get('/service/categories', [ServiceCategoryController::class, 'index'])
                    ->name('service-categories.index');

                Route::post('/service/categories', [ServiceCategoryController::class, 'store'])
                    ->name('service-categories.store');

                Route::put('/service/categories/{category}', [ServiceCategoryController::class, 'update'])
                    ->name('service-categories.update');

                Route::patch('/service/categories/{category}/toggle-featured', [ServiceCategoryController::class, 'toggleFeatured'])
                    ->name('service-categories.toggle-featured');

                Route::patch('/service/categories/{category}/toggle-status', [ServiceCategoryController::class, 'toggleStatus'])
                    ->name('service-categories.toggle-status');

                Route::delete('/service/categories/{category}', [ServiceCategoryController::class, 'destroy'])
                    ->name('service-categories.destroy');

                /*
                |--------------------------------------------------------------------------
                | Admin Coupons
                |--------------------------------------------------------------------------
                */

                Route::get('/coupons', [CouponController::class, 'index'])
                    ->name('coupons.index');

                Route::get('/create-coupon', [CouponController::class, 'create'])
                    ->name('coupons.create');

                Route::post('/coupons', [CouponController::class, 'store'])
                    ->name('coupons.store');

                Route::get('/coupons/{coupon}/edit', [CouponController::class, 'edit'])
                    ->name('coupons.edit');

                Route::put('/coupons/{coupon}', [CouponController::class, 'update'])
                    ->name('coupons.update');

                Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy'])
                    ->name('coupons.destroy');

                /*
                |--------------------------------------------------------------------------
                | Admin Providers
                |--------------------------------------------------------------------------
                */

                Route::get('/providers', [ProviderController::class, 'index'])
                    ->name('providers.index');

                Route::get('/provider/view/{user}', [ProviderController::class, 'show'])
                    ->name('providers.show');

                Route::get('/providers/{user}', [ProviderController::class, 'show'])
                    ->name('providers.view');

                Route::get('/providers/{user}/documents/{document}', [ProviderDocumentController::class, 'admin'])
                    ->middleware('signed')
                    ->whereIn('document', ['ktp', 'nib'])
                    ->name('providers.documents.show');

                Route::patch('/providers/{user}/toggle-status', [ProviderController::class, 'toggleStatus'])
                    ->name('providers.toggle-status');

                Route::patch('/providers/{user}/document-status', [ProviderController::class, 'updateDocumentStatus'])
                    ->name('providers.document-status');

                Route::delete('/providers/{user}', [ProviderController::class, 'destroy'])
                    ->name('providers.destroy');

                /*
                |--------------------------------------------------------------------------
                | Admin Customers / Users
                |--------------------------------------------------------------------------
                | Controller tetap UserController karena tabel database masih users.
                | Ini hanya route admin, bukan route public /user.
                */

                Route::get('/users', [UserController::class, 'index'])
                    ->name('users.index');

                Route::get('/users/{user}', [UserController::class, 'show'])
                    ->name('users.show');

                Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
                    ->name('users.toggle-status');

                Route::delete('/users/{user}', [UserController::class, 'destroy'])
                    ->name('users.destroy');

                /*
                |--------------------------------------------------------------------------
                | Admin Profile
                |--------------------------------------------------------------------------
                */

                Route::get('/profile', [AdminProfileController::class, 'show'])
                    ->name('profile');

                Route::patch('/profile', [AdminProfileController::class, 'update'])
                    ->name('profile.update');

                Route::put('/profile/password', [AdminProfileController::class, 'updatePassword'])
                    ->name('profile.password.update');
            });
        });
};
