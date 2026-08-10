<?php

namespace Tests\Unit\Runtime;

use Illuminate\Support\Env;
use Tests\TestCase;

class RedisHorizonConfigurationTest extends TestCase
{
    public function test_redis_runtime_connections_are_named_and_isolated(): void
    {
        $redis = config('database.redis');
        $sourceRedis = (require config_path('database.php'))['redis'];

        $this->assertSame('phpredis', $redis['client']);
        // Horizon injects its own reserved runtime connection during package
        // boot; application config must never define or repurpose that name.
        $this->assertArrayNotHasKey('horizon', $sourceRedis);

        foreach ([
            'default' => 0,
            'cache' => 1,
            'sessions' => 2,
            'queue' => 3,
            'rate_limits' => 4,
            'horizon_meta' => 5,
        ] as $connection => $database) {
            $this->assertArrayHasKey($connection, $redis);
            $this->assertSame($database, (int) $redis[$connection]['database']);
            $this->assertNotSame('', (string) $redis[$connection]['prefix']);
        }

        $this->assertSame('cache', config('cache.stores.redis.connection'));
        $this->assertSame('rate_limits', config('cache.stores.rate_limits.connection'));
        $this->assertSame('queue', config('queue.connections.redis.connection'));
        $this->assertTrue(config('queue.connections.redis.after_commit'));
        $this->assertSame(5, config('queue.connections.redis.block_for'));
        $this->assertSame(120, config('queue.connections.redis.retry_after'));
        $this->assertSame(6, config('reverb.servers.reverb.scaling.server.database'));

        $broadcast = config('broadcasting.connections.reverb');
        $this->assertArrayHasKey('public_options', $broadcast);
        $this->assertSame(config('reverb.apps.apps.0.options.host'), $broadcast['public_options']['host']);
        $this->assertSame((int) config('reverb.apps.apps.0.options.port'), $broadcast['public_options']['port']);
        $this->assertSame(config('reverb.apps.apps.0.options.scheme'), $broadcast['public_options']['scheme']);
    }

    public function test_horizon_uses_separate_priority_pools_and_admin_middleware(): void
    {
        $this->assertSame('horizon_meta', config('horizon.use'));
        $this->assertSame(['web', 'auth:admin'], config('horizon.middleware'));
        $this->assertContains('auth:admin', app('router')->getMiddlewareGroups()['horizon']);

        $supervisors = config('horizon.defaults');
        $this->assertSame(['critical'], $supervisors['supervisor-critical']['queue']);
        $this->assertSame(
            ['payments', 'bookings', 'default'],
            $supervisors['supervisor-business']['queue']
        );
        $this->assertSame(
            ['notifications', 'emails', 'media', 'analytics'],
            $supervisors['supervisor-background']['queue']
        );

        $queueNames = collect($supervisors)
            ->flatMap(static fn (array $supervisor): array => $supervisor['queue'])
            ->all();

        $this->assertCount(count(array_unique($queueNames)), $queueNames);
        $this->assertLessThan(
            config('queue.connections.redis.retry_after'),
            $supervisors['supervisor-business']['timeout']
        );
    }

    public function test_browser_reverb_consumers_use_public_connection_options(): void
    {
        foreach ([
            app_path('Modules/Support/Presentation/Web/SupportChatController.php'),
            resource_path('views/provider/partials/dashboard/topbar.blade.php'),
            resource_path('views/admin/partials/topbar.blade.php'),
        ] as $consumer) {
            $source = file_get_contents($consumer);

            $this->assertIsString($source);
            $this->assertStringContainsString("['public_options']", $source, $consumer);
        }
    }

    public function test_reverb_workers_can_publish_internally_without_exposing_that_host_to_browsers(): void
    {
        $environment = Env::getRepository();
        $overrides = [
            'REVERB_HOST' => 'ws.public.example.test',
            'REVERB_PORT' => '443',
            'REVERB_SCHEME' => 'https',
            'REVERB_BROADCAST_HOST' => 'reverb',
            'REVERB_BROADCAST_PORT' => '8080',
            'REVERB_BROADCAST_SCHEME' => 'http',
        ];
        $previous = [];

        foreach ($overrides as $key => $value) {
            $previous[$key] = [
                'exists' => $environment->has($key),
                'value' => $environment->get($key),
            ];
            $environment->set($key, $value);
        }

        try {
            $connection = (require config_path('broadcasting.php'))['connections']['reverb'];

            $this->assertSame('reverb', $connection['options']['host']);
            $this->assertSame(8080, $connection['options']['port']);
            $this->assertSame('http', $connection['options']['scheme']);
            $this->assertFalse($connection['options']['useTLS']);

            $this->assertSame('ws.public.example.test', $connection['public_options']['host']);
            $this->assertSame(443, $connection['public_options']['port']);
            $this->assertSame('https', $connection['public_options']['scheme']);
            $this->assertTrue($connection['public_options']['useTLS']);
        } finally {
            foreach ($previous as $key => $state) {
                if ($state['exists']) {
                    $environment->set($key, $state['value']);
                } else {
                    $environment->clear($key);
                }
            }
        }
    }
}
