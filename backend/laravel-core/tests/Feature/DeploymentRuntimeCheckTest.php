<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class DeploymentRuntimeCheckTest extends TestCase
{
    public function test_production_deployment_check_rejects_an_invalid_application_key(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        $this->mockHealthyTransactionalDatabase();

        config()->set('app.key', 'base64:replace-me');

        $this->artisan('app:deployment-check')
            ->expectsOutputToContain('APP_KEY is missing or invalid')
            ->assertExitCode(1);
    }

    public function test_production_deployment_check_rejects_non_redis_runtime_drivers(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        $this->mockHealthyTransactionalDatabase();

        config()->set([
            'session.driver' => 'file',
            'cache.default' => 'file',
            'cache.limiter' => null,
            'queue.default' => 'sync',
        ]);

        $this->artisan('app:deployment-check')
            ->expectsOutputToContain('Production shared-runtime configuration is invalid:')
            ->assertExitCode(1);
    }

    private function mockHealthyTransactionalDatabase(): void
    {
        $requiredTables = [
            'bookings',
            'provider_staffs',
            'booking_participants',
            'booking_participant_services',
            'payments',
            'customer_activities',
        ];

        DB::shouldReceive('select')->once()->with('SELECT 1')->andReturn([]);
        DB::shouldReceive('getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('getDatabaseName')->once()->andReturn('runtime_test');

        $query = Mockery::mock();
        $query->shouldReceive('selectRaw')->once()->andReturnSelf();
        $query->shouldReceive('where')->once()->with('table_schema', 'runtime_test')->andReturnSelf();
        $query->shouldReceive('whereIn')->once()->with('table_name', $requiredTables)->andReturnSelf();
        $query->shouldReceive('pluck')
            ->once()
            ->with('storage_engine', 'table_key')
            ->andReturn(collect(array_fill_keys($requiredTables, 'InnoDB')));

        DB::shouldReceive('table')
            ->once()
            ->with('information_schema.tables')
            ->andReturn($query);
    }
}
