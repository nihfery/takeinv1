<?php

namespace Database\Seeders;

use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRole;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoBranchAccountSeeder extends Seeder
{
    public function run(): void
    {
        $provider = User::where('email', 'provider-pusat@demo.test')->firstOrFail();

        $permissions = collect(ProviderMenuAccess::keys())
            ->reject(fn (string $key) => in_array($key, ['roles_permissions'], true))
            ->values()
            ->all();

        ProviderBranch::query()
            ->where('provider_id', $provider->id)
            ->orderBy('branch_name')
            ->get()
            ->each(function (ProviderBranch $branch) use ($provider, $permissions) {
                $city = Str::slug((string) $branch->city_id);
                $roleName = 'Admin Cabang ' . $branch->city_id;

                $role = ProviderRole::updateOrCreate(
                    [
                        'provider_id' => $provider->id,
                        'slug' => 'admin-cabang-' . $city,
                    ],
                    [
                        'branch_id' => $branch->id,
                        'role_name' => $roleName,
                        'description' => 'Akun demo untuk mengelola data cabang ' . $branch->city_id . '.',
                        'status' => 'active',
                    ]
                );

                $role->menuPermissions()->delete();
                $role->menuPermissions()->createMany(
                    collect($permissions)
                        ->map(fn (string $menuKey) => ['menu_key' => $menuKey])
                        ->all()
                );

                User::updateOrCreate(
                    ['email' => 'branch-' . $city . '@demo.test'],
                    [
                        'name' => 'Admin Cabang ' . $branch->city_id,
                        'username' => 'branch-' . $city,
                        'password' => Hash::make('branch12345'),
                        'role' => 'provider',
                        'provider_id' => $provider->id,
                        'branch_id' => $branch->id,
                        'provider_role_id' => $role->id,
                        'email_verified_at' => now(),
                    ]
                );
            });
    }
}
