<?php
$laravelRoot = dirname(__DIR__, 3) . '/backend/laravel-core';
$file = $laravelRoot . '/routes/web.php';
$content = file_get_contents($file);

$route = <<<'PHP'
// Demo route for React Landing Page Simulator
Route::get('/demo/provider-dashboard', function () {
    return view('demo.provider.staff');
})->name('demo.provider-dashboard');
PHP;

if (strpos($content, '/demo/provider-dashboard') === false) {
    $content .= "\n" . $route . "\n";
    file_put_contents($file, $content);
    echo "Added demo route.";
} else {
    echo "Demo route already exists.";
}
