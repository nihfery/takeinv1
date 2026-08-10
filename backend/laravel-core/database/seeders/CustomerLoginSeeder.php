<?php

namespace Database\Seeders;

use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CustomerLoginSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'name' => 'Demo Customer',
                'username' => 'customer',
                'email' => 'customer@gmail.com',
                'password' => 'customer12345',
                'phone' => '081234567890',
                'gender' => 'female',
                'religion' => 'Islam',
                'allergies' => 'Tidak ada',
            ],
            [
                'name' => 'Demo Customer 2',
                'username' => 'customer2',
                'email' => 'customer2@gmail.com',
                'password' => 'customer12345',
                'phone' => '081234567891',
                'gender' => 'male',
                'religion' => 'Kristen',
                'allergies' => 'Alergi parfum menyengat',
            ],
            [
                'name' => 'Demo Customer 3',
                'username' => 'customer3',
                'email' => 'customer3@gmail.com',
                'password' => 'customer12345',
                'phone' => '081234567892',
                'gender' => 'female',
                'religion' => 'Katolik',
                'allergies' => null,
            ],
            [
                'name' => 'Demo Customer 4',
                'username' => 'customer4',
                'email' => 'customer4@gmail.com',
                'password' => 'customer12345',
                'phone' => '081234567893',
                'gender' => 'male',
                'religion' => 'Hindu',
                'allergies' => 'Alergi lateks',
            ],
            [
                'name' => 'Demo Customer 5',
                'username' => 'customer5',
                'email' => 'customer5@gmail.com',
                'password' => 'customer12345',
                'phone' => '081234567894',
                'gender' => 'female',
                'religion' => 'Buddha',
                'allergies' => null,
            ],
        ];

        foreach ($customers as $data) {
            $customer = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'password' => Hash::make($data['password']),
                    'role' => 'customer',
                    'email_verified_at' => now(),
                ]
            );

            CustomerProfile::updateOrCreate(
                ['user_id' => $customer->id],
                [
                    'phone_number' => $data['phone'],
                    'gender' => $data['gender'],
                    'religion' => $data['religion'],
                    'allergies' => $data['allergies'],
                    'city' => 'Bandung',
                    'country' => 'Indonesia',
                    'status' => 'active',
                ]
            );
        }
    }
}
