<?php

use App\Modules\Booking\Presentation\Web\Provider\BookingController as ProviderBookingController;
use App\Modules\Branch\Presentation\Web\Provider\BranchController;
use App\Modules\Catalog\Presentation\Web\Provider\ServiceController as ProviderServiceController;
use App\Modules\Customer\Presentation\Web\Provider\CustomerController as ProviderCustomerController;
use App\Modules\Identity\Presentation\Web\Auth\UnifiedLoginController;
use App\Modules\Notification\Presentation\Web\NotificationController;
use App\Modules\Provider\Presentation\Web\Provider\DashboardController as ProviderDashboardController;
use App\Modules\Provider\Presentation\Web\Provider\ProfileController as ProviderProfileController;
use App\Modules\Provider\Presentation\Web\Provider\RolePermissionController as ProviderRolePermissionController;
use App\Modules\Provider\Presentation\Web\ProviderDocumentController;
use App\Modules\Staff\Presentation\Web\Provider\StaffController as ProviderStaffController;
use App\Modules\Support\Presentation\Web\SupportChatController;
use App\Support\FrontendUrl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

return static function (): void {
    /*
    |--------------------------------------------------------------------------
    | Provider Session Auth
    |--------------------------------------------------------------------------
    | Landing page provider akan ditangani React.
    | Route ini tetap dipakai untuk membuat session Laravel sebelum masuk
    | ke dashboard Blade provider.
    */
    $providerFrontendUrl = function (array $query = []) {
        $url = FrontendUrl::provider(request());

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $url;
    };

    $providerLanding = function () use ($providerFrontendUrl) {
        if (Auth::guard('provider')->check() && Auth::guard('provider')->user()?->role === 'provider') {
            return redirect()->to(route('provider.dashboard', [], false));
        }

        if (Auth::guard('provider_branch')->check() && Auth::guard('provider_branch')->user()?->role === 'provider') {
            return redirect()->to(route('provider-branch.dashboard', [], false));
        }

        return redirect()->away($providerFrontendUrl());
    };

    Route::get('/provider', $providerLanding)
        ->name('provider.landing');
    Route::get('/providers', $providerLanding)
        ->name('provider.landing.alias');
    Route::get('/provider/landing', $providerLanding)
        ->name('provider.landing.page');

    Route::get('/provider/login', function () use ($providerFrontendUrl) {
        if (Auth::guard('provider')->check() && Auth::guard('provider')->user()?->role === 'provider') {
            return redirect()->to(route('provider.dashboard', [], false));
        }

        if (Auth::guard('provider_branch')->check() && Auth::guard('provider_branch')->user()?->role === 'provider') {
            return redirect()->to(route('provider-branch.dashboard', [], false));
        }

        return redirect()->away($providerFrontendUrl(['login' => 'open']));
    })->name('provider.login');

    Route::get('/provider/register', function () use ($providerFrontendUrl) {
        if (Auth::guard('provider')->check() && Auth::guard('provider')->user()?->role === 'provider') {
            return redirect()->to(route('provider.dashboard', [], false));
        }

        if (Auth::guard('provider_branch')->check() && Auth::guard('provider_branch')->user()?->role === 'provider') {
            return redirect()->to(route('provider-branch.dashboard', [], false));
        }

        return redirect()->away($providerFrontendUrl(['register' => 'open']));
    })->name('provider.register');

    Route::post('/provider/signin', [UnifiedLoginController::class, 'providerSignin'])
        ->middleware('throttle:login')
        ->name('provider.signin');

    Route::get('/provider/profile/documents/{document}', [ProviderDocumentController::class, 'provider'])
        ->middleware(['auth:provider', 'signed'])
        ->whereIn('document', ['ktp', 'nib'])
        ->name('provider.documents.show');

    /*
    |--------------------------------------------------------------------------
    | Provider Dashboard Routes
    |--------------------------------------------------------------------------
    */

    $registerProviderDashboardRoutes = function () {
            Route::get('/notifications', [NotificationController::class, 'index'])
                ->name('notifications.index');

            Route::post('/notifications/read', [NotificationController::class, 'markAllRead'])
                ->name('notifications.read-all');

            Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
                ->name('notifications.read');

            /*
            |--------------------------------------------------------------------------
            | Provider Dashboard
            |--------------------------------------------------------------------------
            */

            Route::get('/dashboard', [ProviderDashboardController::class, 'index'])
                ->middleware(['provider.document.verified'])
                ->name('dashboard');

            /*
            |--------------------------------------------------------------------------
            | Provider Profile
            |--------------------------------------------------------------------------
            | Provider yang belum verified tetap bisa membuka profile.
            */

            Route::get('/verification', [ProviderProfileController::class, 'verification'])
                ->name('verification');

            Route::middleware(['provider.menu:profile'])->group(function () {
                Route::get('/profile', [ProviderProfileController::class, 'show'])
                    ->name('profile');

                Route::get('/profile/edit', [ProviderProfileController::class, 'edit'])
                    ->name('profile.edit');

                Route::put('/profile', [ProviderProfileController::class, 'update'])
                    ->name('profile.update');

                Route::post('/profile/documents', [ProviderProfileController::class, 'updateDocuments'])
                    ->name('profile.documents.update');

                Route::put('/profile/password', [ProviderProfileController::class, 'updatePassword'])
                    ->name('profile.password.update');

                Route::post('/profile/onboarding', [ProviderProfileController::class, 'updateOnboarding'])
                    ->name('profile.onboarding.update');
            });

            Route::middleware(['provider.document.verified', 'provider.menu:chat'])->group(function () {
                Route::get('/chat', [SupportChatController::class, 'providerIndex'])
                    ->name('chat.index');

                Route::get('/chat/{thread}/messages', [SupportChatController::class, 'providerMessages'])
                    ->name('chat.messages.index');

                Route::post('/chat/{thread}/messages', [SupportChatController::class, 'providerStore'])
                    ->name('chat.messages.store');

                Route::post('/chat/{thread}/read', [SupportChatController::class, 'providerRead'])
                    ->name('chat.read');

                Route::post('/chat/internal', [SupportChatController::class, 'providerInternalStart'])
                    ->name('chat.internal.start');
            });

            Route::middleware(['provider.document.verified', 'provider.menu:tickets'])->group(function () {
                Route::get('/tickets', [SupportChatController::class, 'providerTicketsIndex'])
                    ->name('tickets.index');

                Route::post('/tickets', [SupportChatController::class, 'providerTicketStore'])
                    ->name('tickets.store');
            });

            /*
            |--------------------------------------------------------------------------
            | Provider Routes yang Butuh Dokumen Verified
            |--------------------------------------------------------------------------
            */

            Route::middleware(['provider.document.verified'])->group(function () {
                /*
                |--------------------------------------------------------------------------
                | Provider Services
                |--------------------------------------------------------------------------
                */

                Route::middleware(['provider.menu:services'])->group(function () {
                    Route::get('/service', [ProviderServiceController::class, 'index'])
                        ->name('services.index');

                    Route::get('/service/create', [ProviderServiceController::class, 'create'])
                        ->name('services.create');

                    Route::post('/service/continue-information', [ProviderServiceController::class, 'continueInformation'])
                        ->name('services.continue.information');

                    Route::post('/service/continue-branch', [ProviderServiceController::class, 'continueBranch'])
                        ->name('services.continue.branch');

                    Route::post('/service/store', [ProviderServiceController::class, 'store'])
                        ->name('services.store');

                    Route::get('/service/{service}/edit', [ProviderServiceController::class, 'edit'])
                        ->name('services.edit');

                    Route::put('/service/{service}', [ProviderServiceController::class, 'update'])
                        ->name('services.update');

                    Route::put('/service/{service}/branch', [ProviderServiceController::class, 'updateBranch'])
                        ->name('services.update.branch');

                    Route::put('/service/{service}/gallery', [ProviderServiceController::class, 'updateGallery'])
                        ->name('services.update.gallery');

                    Route::patch('/service/{service}/toggle-status', [ProviderServiceController::class, 'toggleStatus'])
                        ->name('services.toggle-status');

                    Route::delete('/service/{service}', [ProviderServiceController::class, 'destroy'])
                        ->name('services.destroy');
                });

                /*
                |--------------------------------------------------------------------------
                | Provider Staffs
                |--------------------------------------------------------------------------
                */

                Route::middleware(['provider.menu:staffs'])->group(function () {
                    Route::get('/staff-list', [ProviderStaffController::class, 'index'])
                        ->name('staffs.index');

                    Route::post('/staff-list', [ProviderStaffController::class, 'store'])
                        ->name('staffs.store');

                    Route::put('/staff-list/{staff}', [ProviderStaffController::class, 'update'])
                        ->name('staffs.update');

                    Route::patch('/staff-list/{staff}/toggle-status', [ProviderStaffController::class, 'toggleStatus'])
                        ->name('staffs.toggle-status');

                    Route::delete('/staff-list/{staff}', [ProviderStaffController::class, 'destroy'])
                        ->name('staffs.destroy');
                });

                /*
                |--------------------------------------------------------------------------
                | Provider Roles & Permissions
                |--------------------------------------------------------------------------
                */

                Route::middleware(['provider.menu:roles_permissions'])->group(function () {
                    Route::get('/roles-permissions', [ProviderRolePermissionController::class, 'index'])
                        ->name('roles-permissions.index');

                    Route::post('/roles-permissions', [ProviderRolePermissionController::class, 'store'])
                        ->name('roles-permissions.store');

                    Route::put('/roles-permissions/{role}', [ProviderRolePermissionController::class, 'update'])
                        ->name('roles-permissions.update');

                    Route::patch('/roles-permissions/{role}/toggle-status', [ProviderRolePermissionController::class, 'toggleStatus'])
                        ->name('roles-permissions.toggle-status');

                    Route::delete('/roles-permissions/{role}', [ProviderRolePermissionController::class, 'destroy'])
                        ->name('roles-permissions.destroy');
                });

                /*
                |--------------------------------------------------------------------------
                | Provider Branch
                |--------------------------------------------------------------------------
                */

                Route::middleware(['provider.menu:branch'])->group(function () {
                    Route::get('/branch', [BranchController::class, 'index'])
                        ->name('branch.index');

                    Route::get('/add-branch', [BranchController::class, 'create'])
                        ->name('branch.create');

                    Route::post('/branch/continue', [BranchController::class, 'continue'])
                        ->name('branch.continue');

                    Route::post('/branch', [BranchController::class, 'store'])
                        ->name('branch.store');

                    Route::get('/branch/{branch}/edit', [BranchController::class, 'edit'])
                        ->name('branch.edit');

                    Route::put('/branch/{branch}', [BranchController::class, 'update'])
                        ->name('branch.update');

                    Route::put('/branch/{branch}/staff', [BranchController::class, 'updateStaff'])
                        ->name('branch.staff.update');

                    Route::patch('/branch/{branch}/toggle-status', [BranchController::class, 'toggleStatus'])
                        ->name('branch.toggle-status');

                    Route::delete('/branch/{branch}', [BranchController::class, 'destroy'])
                        ->name('branch.destroy');
                });

                /*
                |--------------------------------------------------------------------------
                | Provider Booking Flow
                |--------------------------------------------------------------------------
                */

                Route::middleware(['provider.menu:bookings'])->group(function () {
                    Route::get('/bookings', [ProviderBookingController::class, 'index'])
                        ->name('bookings.index');

                    Route::post('/bookings/{booking}/check-in', [ProviderBookingController::class, 'checkIn'])
                        ->name('bookings.check-in');
                    Route::post('/bookings/{booking}/start', [ProviderBookingController::class, 'start'])
                        ->name('bookings.start');
                    Route::post('/bookings/{booking}/complete', [ProviderBookingController::class, 'complete'])
                        ->name('bookings.complete');
                    Route::post('/bookings/{booking}/cancel', [ProviderBookingController::class, 'cancel'])
                        ->name('bookings.cancel');
                    Route::post('/bookings/{booking}/no-show', [ProviderBookingController::class, 'noShow'])
                        ->name('bookings.no-show');
                });

                Route::get('/calendar', [ProviderBookingController::class, 'calendar'])
                    ->middleware(['provider.menu:calendar'])
                    ->name('calendar.index');

                Route::middleware(['provider.menu:queue'])->group(function () {
                    Route::get('/queue', [ProviderBookingController::class, 'queue'])
                        ->name('queue.index');
                    Route::post('/queue/{booking}/call', [ProviderBookingController::class, 'call'])
                        ->name('queue.call');
                });

                Route::middleware(['provider.menu:walk_in'])->group(function () {
                    Route::get('/walk-in', [ProviderBookingController::class, 'walkIn'])
                        ->name('walk-in.index');
                    Route::post('/walk-in/availability', [ProviderBookingController::class, 'walkInAvailability'])
                        ->name('walk-in.availability');
                    Route::post('/walk-in', [ProviderBookingController::class, 'storeWalkIn'])
                        ->name('walk-in.store');
                });

                Route::middleware(['provider.menu:staff_skills'])->group(function () {
                    Route::get('/staff/skills', [ProviderBookingController::class, 'skills'])
                        ->name('staff.skills');
                    Route::post('/staff/skills', [ProviderBookingController::class, 'updateSkills'])
                        ->name('staff.skills.update');
                });

                Route::middleware(['provider.menu:staff_schedules'])->group(function () {
                    Route::get('/staff/schedules', [ProviderBookingController::class, 'schedules'])
                        ->name('staff.schedules');
                    Route::post('/staff/schedules', [ProviderBookingController::class, 'updateSchedules'])
                        ->name('staff.schedules.update');
                });

                Route::get('/payments', [ProviderBookingController::class, 'payments'])
                    ->middleware(['provider.menu:payments'])
                    ->name('payments.index');

                Route::get('/customers', [ProviderCustomerController::class, 'index'])
                    ->middleware(['provider.menu:customers'])
                    ->name('customers.index');

                Route::get('/reviews', [ProviderCustomerController::class, 'reviews'])
                    ->middleware(['provider.menu:reviews'])
                    ->name('reviews.index');

            });

            /*
            |--------------------------------------------------------------------------
            | Provider Logout
            |--------------------------------------------------------------------------
            */

            Route::post('/logout', [UnifiedLoginController::class, 'providerLogout'])
                ->name('logout');
    };

    Route::prefix('provider')
        ->name('provider.')
        ->middleware(['auth:provider', 'prevent-back-history', 'provider.account.active'])
        ->group($registerProviderDashboardRoutes);

    Route::prefix('provider-branch')
        ->name('provider-branch.')
        ->middleware(['auth:provider_branch', 'prevent-back-history', 'provider.account.active'])
        ->group($registerProviderDashboardRoutes);
};
