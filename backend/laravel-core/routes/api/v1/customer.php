<?php

use App\Modules\Booking\Presentation\Api\Customer\BookingController as CustomerBookingController;
use App\Modules\Booking\Presentation\Api\Customer\GraphqlController as CustomerGraphqlController;
use App\Modules\Customer\Presentation\Api\Customer\ActivityController as CustomerActivityController;
use App\Modules\Customer\Presentation\Api\Customer\ProfileController as CustomerProfileController;
use App\Modules\Payment\Presentation\Api\Customer\PaymentController as CustomerPaymentController;
use App\Modules\Review\Presentation\Api\Customer\ReviewController as CustomerReviewController;
use Illuminate\Support\Facades\Route;

return [
    'public' => static function (): void {
        Route::post('/customer/graphql', CustomerGraphqlController::class)
            ->middleware('throttle:availability')
            ->name('api.customer.graphql');
        Route::post('/customer/booking/eligible-staff', [CustomerBookingController::class, 'eligibleStaff'])
            ->middleware('throttle:availability')
            ->name('api.customer.booking.eligible-staff');
        Route::post('/customer/booking/check-availability', [CustomerBookingController::class, 'checkAvailability'])
            ->middleware('throttle:availability')
            ->name('api.customer.booking.check-availability');
        Route::post('/customer/booking/interaction', [CustomerBookingController::class, 'interaction'])
            ->middleware('throttle:booking-write')
            ->name('api.customer.booking.interaction');
    },
    'authenticated' => static function (): void {
        Route::prefix('customer')->name('api.customer.')->group(function (): void {
            Route::get('/profile', [CustomerProfileController::class, 'show'])
                ->name('profile.show');
            Route::put('/profile', [CustomerProfileController::class, 'update'])
                ->name('profile.update');

            Route::get('/activity/summary', [CustomerActivityController::class, 'summary'])
                ->name('activity.summary');
            Route::get('/activity', [CustomerActivityController::class, 'show'])
                ->name('activity.show');

            Route::get('/bookings', [CustomerBookingController::class, 'index'])
                ->name('bookings.index');
            Route::post('/bookings', [CustomerBookingController::class, 'store'])
                ->middleware('throttle:booking-write')
                ->name('bookings.store');
            Route::post('/bookings/{booking}/finalize', [CustomerBookingController::class, 'finalize'])
                ->middleware('throttle:booking-write')
                ->name('bookings.finalize');
            Route::post('/bookings/{booking}/hold/extend', [CustomerBookingController::class, 'extendHold'])
                ->middleware('throttle:booking-write')
                ->name('bookings.hold.extend');
            Route::get('/bookings/code/{bookingCode}', [CustomerBookingController::class, 'showByCode'])
                ->name('bookings.show-by-code');
            Route::post('/bookings/code/{bookingCode}/review', [CustomerReviewController::class, 'store'])
                ->name('bookings.review.store');
            Route::post('/bookings/code/{bookingCode}/payment/confirm', [CustomerPaymentController::class, 'confirmByCode'])
                ->middleware('throttle:payment-write')
                ->name('bookings.payment.confirm-by-code');
            Route::get('/bookings/{booking}', [CustomerBookingController::class, 'show'])
                ->name('bookings.show');
            Route::post('/bookings/{booking}/payment/charge', [CustomerPaymentController::class, 'charge'])
                ->middleware('throttle:payment-write')
                ->name('bookings.payment.charge');
            Route::get('/bookings/{booking}/payment/status', [CustomerPaymentController::class, 'status'])
                ->middleware('throttle:payment-write')
                ->name('bookings.payment.status');
            Route::patch('/bookings/{booking}/reschedule', [CustomerBookingController::class, 'reschedule'])
                ->middleware('throttle:booking-write')
                ->name('bookings.reschedule');
            Route::patch('/bookings/{booking}/cancel', [CustomerBookingController::class, 'cancel'])
                ->middleware('throttle:booking-write')
                ->name('bookings.cancel');
        });
    },
];
