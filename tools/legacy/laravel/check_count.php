<?php
$laravelRoot = dirname(__DIR__, 3) . '/backend/laravel-core';
require $laravelRoot . '/vendor/autoload.php';
$app = require_once $laravelRoot . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$branch = \App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch::latest()->first();
$providerId = $branch->provider->id;

$count = \App\Modules\Booking\Infrastructure\Persistence\Models\Booking::where('provider_id', $providerId)->count();
echo "Provider ID: " . $providerId . "\n";
echo "Bookings Count: " . $count . "\n";
$bookings = \App\Modules\Booking\Infrastructure\Persistence\Models\Booking::where('provider_id', $providerId)->get();
foreach($bookings as $b) {
    echo "ID: " . $b->id . " Type: " . $b->booking_type . "\n";
}
