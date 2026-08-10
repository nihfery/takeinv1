<?php

$laravelRoot = dirname(__DIR__, 3) . '/backend/laravel-core';

require $laravelRoot . '/vendor/autoload.php';
$app = require_once $laravelRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = $app->make(App\Modules\Provider\Presentation\Web\Provider\DashboardController::class);
Auth::loginUsingId(103);
$response = $controller->index(request()->merge(['period' => 'year']));

file_put_contents(dirname(__DIR__) . '/artifacts/test_output_year.html', $response->render());

echo 'DONE';
