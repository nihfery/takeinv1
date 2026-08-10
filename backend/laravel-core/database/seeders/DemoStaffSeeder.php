<?php

namespace Database\Seeders;

use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoStaffSeeder extends Seeder
{
    public function run(): void
    {
        $provider = User::where('email', 'provider-pusat@demo.test')->firstOrFail();
        $hair = ServiceCategory::where('name', 'Hair')->first();
        $spa = ServiceCategory::where('name', 'Face & Spa')->first();

        $names = [
            ['Ayu', 'Prameswari', 'female', 4.9],
            ['Dimas', 'Saputra', 'male', 4.7],
            ['Rani', 'Lestari', 'female', 4.8],
            ['Bima', 'Nugraha', 'male', 4.6],
            ['Sinta', 'Maharani', 'female', 4.9],
            ['Yoga', 'Firmansyah', 'male', 4.5],
            ['Nadia', 'Kusuma', 'female', 4.8],
            ['Raka', 'Aditya', 'male', 4.7],
            ['Maya', 'Putri', 'female', 4.9],
            ['Ardi', 'Pratama', 'male', 4.6],
        ];

        ProviderBranch::query()
            ->where('provider_id', $provider->id)
            ->orderBy('branch_name')
            ->get()
            ->each(function (ProviderBranch $branch, int $branchIndex) use ($provider, $hair, $spa, $names) {
                $branchNames = array_slice($names, $branchIndex * 2, 2);

                foreach ($branchNames as $index => [$firstName, $lastName, $gender, $rating]) {
                    $emailCity = Str::slug((string) $branch->city_id);
                    $email = Str::slug($firstName . '-' . $emailCity) . '@staff.demo.test';

                    ProviderStaff::updateOrCreate(
                        [
                            'provider_id' => $provider->id,
                            'email' => $email,
                        ],
                        [
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'username' => Str::slug($firstName . '-' . $lastName . '-' . $branch->city_id),
                            'country_code' => '+62',
                            'phone_number' => '81240' . str_pad((string) (($branchIndex + 1) * 100 + $index), 5, '0', STR_PAD_LEFT),
                            'gender' => $gender,
                            'address' => 'Alamat dummy staff ' . $branch->city_id,
                            'country_id' => 'Indonesia',
                            'state_id' => $branch->state_id,
                            'city_id' => $branch->city_id,
                            'postal_code' => $branch->zip_code,
                            'bio' => 'Staff dummy cabang ' . $branch->city_id,
                            'category_id' => $index === 0 ? $hair?->id : ($spa?->id ?? $hair?->id),
                            'branch_id' => $branch->id,
                            'role' => 'staff',
                            'rating' => $rating,
                            'current_status' => 'available',
                            'status' => 'active',
                        ]
                    );
                }
            });
    }
}
