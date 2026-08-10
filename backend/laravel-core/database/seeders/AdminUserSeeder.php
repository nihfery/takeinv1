<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Identity\Infrastructure\Persistence\Models\AdminProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'password' => Hash::make('admin12345'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        AdminProfile::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'phone_number' => '081100000001',
                'position' => 'Platform Administrator',
                'avatar' => null,
                'bio' => 'Akun admin demo untuk mengelola provider, layanan, booking, kupon, dan notifikasi.',
            ]
        );
    }
}
