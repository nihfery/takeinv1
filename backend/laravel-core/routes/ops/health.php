<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

return static function (): void {
    Route::get('/health', function () {
        DB::select('SELECT 1');

        return response()->json(['status' => 'ok']);
    })->name('api.health');
};
