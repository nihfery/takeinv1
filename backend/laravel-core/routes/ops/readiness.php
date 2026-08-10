<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

return static function (): void {
    Route::get('/readiness', static function () {
        try {
            DB::select('SELECT 1');

            $redisConnections = collect();
            $cacheStore = (string) config('cache.default');

            if (config("cache.stores.{$cacheStore}.driver") === 'redis') {
                $redisConnections->push(config("cache.stores.{$cacheStore}.connection"));
                $redisConnections->push(config("cache.stores.{$cacheStore}.lock_connection"));
            }

            if (config('session.driver') === 'redis') {
                $redisConnections->push(config('session.connection', 'sessions'));
            }

            $queueConnection = (string) config('queue.default');
            if (config("queue.connections.{$queueConnection}.driver") === 'redis') {
                $redisConnections->push(config("queue.connections.{$queueConnection}.connection"));
                $redisConnections->push(config('horizon.use', 'horizon_meta'));
            }

            $limiterStore = config('cache.limiter');
            if ($limiterStore && config("cache.stores.{$limiterStore}.driver") === 'redis') {
                $redisConnections->push(config("cache.stores.{$limiterStore}.connection"));
            }

            $redisConnections
                ->filter(static fn ($connection): bool => is_string($connection) && $connection !== '')
                ->unique()
                ->each(static fn (string $connection) => Redis::connection($connection)->command('ping'));
        } catch (Throwable $error) {
            try {
                Log::warning('Runtime readiness dependency check failed.', [
                    'exception' => $error::class,
                ]);
            } catch (Throwable) {
                // A broken logging destination must not replace the sanitized
                // dependency response with an unhandled HTTP 500.
            }

            return response()
                ->json(['status' => 'unavailable'], 503)
                ->header('Cache-Control', 'no-store');
        }

        return response()
            ->json(['status' => 'ready'])
            ->header('Cache-Control', 'no-store');
    })->name('api.readiness');
};
