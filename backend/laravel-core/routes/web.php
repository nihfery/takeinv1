<?php

$publicRoutes = require __DIR__.'/web/public.php';
$authRoutes = require __DIR__.'/web/auth.php';
$providerRoutes = require __DIR__.'/web/provider.php';
$adminRoutes = require __DIR__.'/web/admin.php';

// Keep registration order identical to the original monolithic route file.
$publicRoutes['home']();
$authRoutes();
$publicRoutes['customer']();
$providerRoutes();
$adminRoutes();
$publicRoutes['demo']();

unset($publicRoutes, $authRoutes, $providerRoutes, $adminRoutes);
