<?php

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_documented_snapshot_seed_restores_the_public_directory(): void
    {
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

        $this->assertSame(750, $activeServiceCount);
        $this->assertSame($activeServiceCount, $publicServiceCount);

        $this->getJson('/api/categories?hierarchy=1')
            ->assertOk()
            ->assertJsonPath('data.0.parent_id', null);

        $this->getJson('/api/services?per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'active');

        $this->assertSame(120, User::query()->where('role', 'provider')->count());
    }
}
