<?php

use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:deployment-check', function () {
    DB::select('SELECT 1');

    if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->error('Production booking requires a MySQL or MariaDB connection.');

        return 1;
    }

    $requiredTables = [
        'bookings',
        'provider_staffs',
        'booking_participants',
        'booking_participant_services',
        'payments',
        'customer_activities',
    ];
    $tableEngines = DB::table('information_schema.tables')
        ->selectRaw('TABLE_NAME AS table_key, ENGINE AS storage_engine')
        ->where('table_schema', DB::getDatabaseName())
        ->whereIn('table_name', $requiredTables)
        ->pluck('storage_engine', 'table_key');
    $missingTables = array_values(array_diff($requiredTables, $tableEngines->keys()->all()));

    if ($missingTables !== []) {
        $this->error('Required tables are missing: '.implode(', ', $missingTables));

        return 1;
    }

    $nonTransactionalTables = $tableEngines
        ->filter(fn (?string $engine) => strtoupper((string) $engine) !== 'INNODB')
        ->keys()
        ->all();

    if ($nonTransactionalTables !== []) {
        $this->error('These tables must use InnoDB: '.implode(', ', $nonTransactionalTables));

        return 1;
    }

    if (app()->environment('production')) {
        $configuredKey = (string) config('app.key', '');
        $encryptionKey = str_starts_with($configuredKey, 'base64:')
            ? base64_decode(substr($configuredKey, 7), true)
            : $configuredKey;

        if (! is_string($encryptionKey)
            || ! Encrypter::supported($encryptionKey, (string) config('app.cipher'))) {
            $this->error('APP_KEY is missing or invalid for the configured application cipher.');

            return 1;
        }

        $expectedRuntimeConfig = [
            'session.driver' => 'redis',
            'session.connection' => 'sessions',
            'cache.default' => 'redis',
            'cache.limiter' => 'rate_limits',
            'queue.default' => 'redis',
            'queue.connections.redis.connection' => 'queue',
            'queue.connections.redis.after_commit' => true,
            'database.redis.client' => 'phpredis',
            'horizon.use' => 'horizon_meta',
        ];

        $invalidRuntimeConfig = collect($expectedRuntimeConfig)
            ->reject(static fn ($expected, string $key): bool => config($key) === $expected)
            ->map(static fn ($expected, string $key): string => sprintf(
                '%s must be %s',
                $key,
                is_bool($expected) ? ($expected ? 'true' : 'false') : (string) $expected
            ))
            ->values()
            ->all();

        if ($invalidRuntimeConfig !== []) {
            $this->error('Production shared-runtime configuration is invalid: '.implode('; ', $invalidRuntimeConfig));

            return 1;
        }

        if (! extension_loaded('redis')) {
            $this->error('The PhpRedis extension must be installed in production.');

            return 1;
        }

        try {
            foreach (['default', 'cache', 'sessions', 'queue', 'rate_limits', 'horizon_meta'] as $connection) {
                Redis::connection($connection)->command('ping');
            }
        } catch (Throwable $error) {
            $this->error('Redis runtime connectivity check failed: '.$error::class);

            return 1;
        }
    }

    $this->info('Deployment check passed: transactional database and shared runtime requirements are satisfied.');

    return 0;
})->purpose('Verify production database and shared runtime requirements after migration');

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->withoutOverlapping();
