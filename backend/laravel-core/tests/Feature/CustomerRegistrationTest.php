<?php

namespace Tests\Feature;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_is_logged_in(): void
    {
        $response = $this
            ->withHeader('Referer', config('app.url'))
            ->withSession([])
            ->postJson(route('api.auth.register-customer'), [
            'name' => 'Customer Baru',
            'email' => 'customer-baru@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.name', 'Customer Baru')
            ->assertJsonPath('user.email', 'customer-baru@example.test')
            ->assertJsonPath('user.role', 'customer');

        $user = User::query()->where('email', 'customer-baru@example.test')->firstOrFail();

        $this->assertDatabaseHas('customer_profiles', [
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $this->assertAuthenticatedAs($user);
    }
}
