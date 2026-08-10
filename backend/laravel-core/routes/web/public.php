<?php

use App\Modules\Media\Presentation\Web\ChatAttachmentController;
use App\Support\FrontendUrl;
use Illuminate\Support\Facades\Route;

return [
    'home' => static function (): void {
        /*
        |--------------------------------------------------------------------------
        | Frontend Entry
        |--------------------------------------------------------------------------
        | Landing page customer/provider akan ditangani React.
        | Laravel tetap memberi respons ringan sampai frontend React dipasang.
        */

        Route::get('/', function () {
            return redirect()->route('admin.login');
        })->name('home');

        Route::get('/private/chat-attachments/{message}', ChatAttachmentController::class)
            ->middleware('signed:relative')
            ->whereNumber('message')
            ->name('chat.attachments.show');
    },

    'customer' => static function (): void {
        /*
        |--------------------------------------------------------------------------
        | Customer Landing
        |--------------------------------------------------------------------------
        | Landing page customer ditangani React dan bisa di-host terpisah.
        */
        $customerLanding = fn () => redirect()->away(FrontendUrl::customer(request()));

        Route::get('/customer', $customerLanding)
            ->name('customer.landing');
        Route::get('/customers', $customerLanding)
            ->name('customer.landing.alias');
        Route::get('/customer/landing', $customerLanding)
            ->name('customer.landing.page');
    },

    'demo' => static function (): void {
        // Demo route for React Landing Page Simulator
        Route::get('/demo/provider-dashboard', function () {
            return view('demo.provider.dashboard');
        })->name('demo.provider-dashboard');
    },
];
