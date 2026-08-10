<?php
$laravelRoot = dirname(__DIR__, 3) . '/backend/laravel-core';
require $laravelRoot . '/vendor/autoload.php';
$app = require_once $laravelRoot . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$branch = \App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch::latest()->first();
$branch->loadMissing('provider.providerProfile');
$profile = optional($branch->provider?->providerProfile);

echo "Branch Status: " . $branch->status . "\n";
echo "Provider Role: " . $branch->provider?->role . "\n";
echo "Profile Status: " . $profile->status . "\n";
echo "Document Status: " . $profile->document_status . "\n";
echo "Has Active Subscription: " . ($profile->hasActiveSubscription() ? "Yes" : "No") . "\n";
