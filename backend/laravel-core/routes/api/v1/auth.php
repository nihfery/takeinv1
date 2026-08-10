<?php

use App\Modules\Identity\Presentation\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

return [
    'public' => static function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::post('/register/customer', [AuthController::class, 'registerCustomer'])
                ->middleware('throttle:registration')
                ->name('api.auth.register-customer');
            Route::post('/register/provider', [AuthController::class, 'registerProvider'])
                ->middleware('throttle:registration')
                ->name('api.auth.register-provider');
            Route::post('/login', [AuthController::class, 'login'])
                ->middleware('throttle:login')
                ->name('api.auth.login');
        });
    },
    'authenticated' => static function (): void {
        Route::get('/auth/me', [AuthController::class, 'me'])
            ->name('api.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])
            ->name('api.auth.logout');
    },
];
