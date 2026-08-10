<?php

namespace Tests\Unit\Runtime;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class HorizonAuthorizationTest extends TestCase
{
    public function test_horizon_dashboard_requires_the_admin_session_guard(): void
    {
        $this->get('/horizon')->assertRedirect(route('admin.login'));
    }

    public function test_horizon_gate_allows_an_admin_from_the_admin_guard(): void
    {
        $admin = $this->userWithRole('admin');
        $guard = Mockery::mock();
        $guard->shouldReceive('user')->once()->andReturn($admin);
        Auth::shouldReceive('guard')->once()->with('admin')->andReturn($guard);

        $this->assertTrue(
            app(GateContract::class)->forUser($admin)->allows('viewHorizon')
        );
    }

    public function test_horizon_gate_rejects_a_provider_even_if_authenticated(): void
    {
        $provider = $this->userWithRole('provider');
        $guard = Mockery::mock();
        $guard->shouldReceive('user')->once()->andReturn($provider);
        Auth::shouldReceive('guard')->once()->with('admin')->andReturn($guard);

        $this->assertFalse(
            app(GateContract::class)->forUser($provider)->allows('viewHorizon')
        );
    }

    private function userWithRole(string $role): User
    {
        return (new User)->forceFill([
            'id' => 123,
            'name' => 'Runtime Test User',
            'email' => "{$role}@example.test",
            'role' => $role,
        ]);
    }
}
