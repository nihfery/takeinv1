<?php

use App\Modules\Notification\Presentation\Web\NotificationController;
use Illuminate\Support\Facades\Route;

return static function (): void {
    /*
    |--------------------------------------------------------------------------
    | Default Login Redirect
    |--------------------------------------------------------------------------
    | Karena login customer belum dibuat, route /login sementara diarahkan
    | ke admin login agar middleware auth Laravel tidak error.
    */

    Route::get('/login', function () {
        return redirect()->route('admin.login');
    })->name('login');

    Route::middleware('auth')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('notifications.index');

        Route::post('/notifications/read', [NotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');

        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
            ->name('notifications.read');
    });
};
