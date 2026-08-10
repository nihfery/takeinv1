<?php

use App\Modules\Payment\Presentation\Webhook\MidtransNotificationController;
use Illuminate\Support\Facades\Route;

return static function (): void {
    Route::post('/midtrans/notification', MidtransNotificationController::class)
        ->middleware('throttle:webhook')
        ->name('api.midtrans.notification');
};
