<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Route Registrars
|--------------------------------------------------------------------------
|
| The v1 directory establishes ownership and versioning intent only. These
| routes intentionally keep their existing /api URLs until a separately
| tested compatibility rollout introduces an external /api/v1 surface.
|
*/

$healthRoutes = require __DIR__.'/ops/health.php';
$readinessRoutes = require __DIR__.'/ops/readiness.php';
$publicRoutes = require __DIR__.'/api/v1/public.php';
$authRoutes = require __DIR__.'/api/v1/auth.php';
$customerRoutes = require __DIR__.'/api/v1/customer.php';
$providerRoutes = require __DIR__.'/api/v1/provider.php';
$adminRoutes = require __DIR__.'/api/v1/admin.php';
$partnerRoutes = require __DIR__.'/api/v1/partner.php';
$webhookRoutes = require __DIR__.'/api/v1/webhooks.php';

// Keep legacy registration order identical; readiness is an additive
// operations endpoint registered beside the existing health route.
$healthRoutes();
$readinessRoutes();
$authRoutes['public']();
$publicRoutes['catalog']();
$customerRoutes['public']();
$publicRoutes['coupon-validation']();
$webhookRoutes();

Route::middleware('auth:sanctum')->group(function () use (
    $authRoutes,
    $customerRoutes,
    $adminRoutes,
    $providerRoutes,
): void {
    $authRoutes['authenticated']();
    $customerRoutes['authenticated']();
    $adminRoutes();
    $providerRoutes();
});

// Reserved for future authenticated partner endpoints; currently registers none.
$partnerRoutes();

unset(
    $healthRoutes,
    $readinessRoutes,
    $publicRoutes,
    $authRoutes,
    $customerRoutes,
    $providerRoutes,
    $adminRoutes,
    $partnerRoutes,
    $webhookRoutes,
);
