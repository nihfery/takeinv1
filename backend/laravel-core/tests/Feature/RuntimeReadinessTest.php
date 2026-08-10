<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RuntimeReadinessTest extends TestCase
{
    public function test_readiness_checks_every_shared_redis_runtime_connection(): void
    {
        $this->configureSharedRedisRuntime();

        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andReturn([]);

        $connection = Mockery::mock();
        $connection->shouldReceive('command')
            ->times(6)
            ->with('ping')
            ->andReturn(true);

        foreach (['cache', 'default', 'sessions', 'queue', 'horizon_meta', 'rate_limits'] as $name) {
            Redis::shouldReceive('connection')
                ->once()
                ->with($name)
                ->andReturn($connection);
        }

        $response = $this->getJson('/api/readiness')
            ->assertOk()
            ->assertExactJson(['status' => 'ready']);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_readiness_returns_generic_unavailable_response_when_redis_is_down(): void
    {
        $this->configureSharedRedisRuntime();

        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andReturn([]);

        Redis::shouldReceive('connection')
            ->once()
            ->with('cache')
            ->andThrow(new RuntimeException('sensitive redis endpoint details'));

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Runtime readiness dependency check failed.',
                Mockery::on(static fn (array $context): bool => $context === [
                    'exception' => RuntimeException::class,
                ])
            );

        $response = $this->getJson('/api/readiness')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'unavailable'])
            ->assertDontSee('sensitive redis endpoint details');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_readiness_remains_sanitized_when_dependency_and_logging_both_fail(): void
    {
        $this->configureSharedRedisRuntime();

        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andReturn([]);

        Redis::shouldReceive('connection')
            ->once()
            ->with('cache')
            ->andThrow(new RuntimeException('sensitive redis endpoint details'));

        Log::shouldReceive('warning')
            ->once()
            ->andThrow(new RuntimeException('sensitive logger destination details'));

        $this->getJson('/api/readiness')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'unavailable'])
            ->assertDontSee('sensitive redis endpoint details')
            ->assertDontSee('sensitive logger destination details');
    }

    private function configureSharedRedisRuntime(): void
    {
        config()->set([
            'cache.default' => 'redis',
            'cache.limiter' => 'rate_limits',
            'session.driver' => 'redis',
            'session.connection' => 'sessions',
            'queue.default' => 'redis',
            'queue.connections.redis.connection' => 'queue',
            'horizon.use' => 'horizon_meta',
        ]);
    }
}
