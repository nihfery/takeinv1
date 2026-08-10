<?php

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_documented_demo_seed_succeeds_outside_operating_hours_with_public_taxonomy(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16 03:00:00', config('app.timezone')));

        try {
            $this->seed(DatabaseSeeder::class);

            $this->assertDatabaseHas('users', [
                'email' => 'admin@gmail.com',
                'role' => 'admin',
            ]);
            $this->assertDatabaseHas('users', [
                'email' => 'provider-cantika-beauty-salon@directory.test',
                'role' => 'provider',
            ]);

            $activeServiceCount = Service::query()->where('status', 'active')->count();
            $publicServiceCount = Service::query()
                ->where('status', 'active')
                ->publiclyCategorized()
                ->count();

            $this->assertGreaterThan(0, $activeServiceCount);
            $this->assertSame($activeServiceCount, $publicServiceCount);

            $this->getJson('/api/categories?hierarchy=1')
                ->assertOk()
                ->assertJsonPath('data.0.parent_id', null);

            $this->getJson('/api/services?per_page=1')
                ->assertOk()
                ->assertJsonPath('data.0.status', 'active');

            $this->assertGreaterThan(0, User::query()->where('role', 'provider')->count());
        } finally {
            Carbon::setTestNow();
        }
    }
}
