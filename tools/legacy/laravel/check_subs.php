<?php
$laravelRoot = dirname(__DIR__, 3) . '/backend/laravel-core';
require $laravelRoot . '/vendor/autoload.php';
$app = require_once $laravelRoot . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$branch = \App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch::latest()->first();
$subs = \App\Modules\Subscription\Infrastructure\Persistence\Models\ProviderSubscription::where('provider_id', $branch->provider->id)->get();
foreach ($subs as $sub) {
    echo 'Status: ' . $sub->subscription_status . ' Ends: ' . $sub->ends_at . ' Plan: ' . $sub->plan_type . "\n";
}
if ($subs->isEmpty()) { echo 'No subscriptions found.'; }
