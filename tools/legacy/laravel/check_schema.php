<?php
$laravelRoot = dirname(__DIR__, 3) . '/backend/laravel-core';
require $laravelRoot . '/vendor/autoload.php';
$app = require_once $laravelRoot . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = ['provider_branches', 'services', 'provider_staffs', 'provider_roles'];
foreach ($tables as $table) {
    try {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
        if (in_array('status', $columns)) {
            echo "Table $table has 'status' column.\n";
        } else {
            echo "Table $table DOES NOT have 'status' column.\n";
        }
    } catch (Exception $e) {
        echo "Error checking $table: " . $e->getMessage() . "\n";
    }
}
