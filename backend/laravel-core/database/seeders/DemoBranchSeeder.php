<?php

namespace Database\Seeders;

use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Seeder;

class DemoBranchSeeder extends Seeder
{
    public function run(): void
    {
        $provider = User::where('email', 'provider-pusat@demo.test')->firstOrFail();

        $branches = [
            [
                'branch_name' => 'Provider Pusat Jakarta',
                'email' => 'jakarta@providerpusat.test',
                'phone_number' => '81230001001',
                'address' => 'Jl. Sudirman No. 10, Jakarta Pusat',
                'state_id' => 'DKI Jakarta',
                'city_id' => 'Jakarta',
                'zip_code' => '10220',
                'latitude' => -6.200000,
                'longitude' => 106.816666,
                'image' => 'https://images.unsplash.com/photo-1555899434-94d1368aa7af?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'branch_name' => 'Provider Pusat Bandung',
                'email' => 'bandung@providerpusat.test',
                'phone_number' => '81230001002',
                'address' => 'Jl. Riau No. 18, Bandung',
                'state_id' => 'Jawa Barat',
                'city_id' => 'Bandung',
                'zip_code' => '40115',
                'latitude' => -6.917464,
                'longitude' => 107.619125,
                'image' => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'branch_name' => 'Provider Pusat Surabaya',
                'email' => 'surabaya@providerpusat.test',
                'phone_number' => '81230001003',
                'address' => 'Jl. Tunjungan No. 21, Surabaya',
                'state_id' => 'Jawa Timur',
                'city_id' => 'Surabaya',
                'zip_code' => '60275',
                'latitude' => -7.257472,
                'longitude' => 112.752090,
                'image' => 'https://images.unsplash.com/photo-1518005020951-eccb494ad742?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'branch_name' => 'Provider Pusat Yogyakarta',
                'email' => 'yogyakarta@providerpusat.test',
                'phone_number' => '81230001004',
                'address' => 'Jl. Malioboro No. 45, Yogyakarta',
                'state_id' => 'DI Yogyakarta',
                'city_id' => 'Yogyakarta',
                'zip_code' => '55271',
                'latitude' => -7.795580,
                'longitude' => 110.369492,
                'image' => 'https://images.unsplash.com/photo-1566552881560-0be862a7c445?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'branch_name' => 'Provider Pusat Denpasar',
                'email' => 'denpasar@providerpusat.test',
                'phone_number' => '81230001005',
                'address' => 'Jl. Teuku Umar No. 8, Denpasar',
                'state_id' => 'Bali',
                'city_id' => 'Denpasar',
                'zip_code' => '80114',
                'latitude' => -8.670458,
                'longitude' => 115.212631,
                'image' => 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=1200&q=80',
            ],
        ];

        foreach ($branches as $branch) {
            ProviderBranch::updateOrCreate(
                [
                    'provider_id' => $provider->id,
                    'branch_name' => $branch['branch_name'],
                ],
                array_merge($branch, [
                    'provider_id' => $provider->id,
                    'phone_code' => '+62',
                    'country_id' => 'Indonesia',
                    'working_start_hour' => '09:00',
                    'working_end_hour' => '18:00',
                    'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
                    'holidays' => [],
                    'status' => 'active',
                ])
            );
        }
    }
}
