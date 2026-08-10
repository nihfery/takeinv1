<?php

namespace Database\Seeders;

use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerProfile;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRole;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoProviderSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->deleteOldProviderDemoData();

            $customer = User::updateOrCreate(
                ['email' => 'customer@gmail.com'],
                [
                    'name' => 'Demo Customer',
                    'username' => 'customer',
                    'password' => Hash::make('customer12345'),
                    'role' => 'customer',
                    'email_verified_at' => now(),
                ]
            );

            CustomerProfile::updateOrCreate(
                ['user_id' => $customer->id],
                ['phone_number' => '081234567890', 'status' => 'active']
            );

            $provider = User::updateOrCreate(
                ['email' => 'provider-pusat@demo.test'],
                [
                    'name' => 'Provider Pusat Demo',
                    'username' => 'provider-pusat',
                    'password' => Hash::make('provider12345'),
                    'role' => 'provider',
                    'provider_id' => null,
                    'branch_id' => null,
                    'provider_role_id' => null,
                    'email_verified_at' => now(),
                ]
            );

            Payment::whereHas('booking', fn ($query) => $query->where('provider_id', $provider->id))->delete();
            Booking::where('provider_id', $provider->id)->delete();
            User::where('provider_id', $provider->id)->delete();
            ProviderRole::where('provider_id', $provider->id)->delete();
            ProviderStaff::where('provider_id', $provider->id)->delete();
            Service::where('provider_id', $provider->id)->delete();
            ProviderBranch::where('provider_id', $provider->id)->delete();

            ProviderProfile::updateOrCreate(
                ['user_id' => $provider->id],
                [
                    'phone_number' => '081234567890',
                    'category' => 'General Service',
                    'status' => 'active',
                    'document_status' => 'verified',
                    'document_note' => null,
                ]
            );
        });
    }

    private function deleteOldProviderDemoData(): void
    {
        $legacyProviderEmails = [
            'beauty-glow-salon@demo.test',
            'queen-hair-studio@demo.test',
            'fresh-cut-barbershop@demo.test',
        ];

        $legacyProviderIds = User::whereIn('email', $legacyProviderEmails)
            ->pluck('id')
            ->all();

        if ($legacyProviderIds === []) {
            return;
        }

        User::whereIn('provider_id', $legacyProviderIds)->delete();
        ProviderRole::whereIn('provider_id', $legacyProviderIds)->delete();
        ProviderStaff::whereIn('provider_id', $legacyProviderIds)->delete();
        Service::whereIn('provider_id', $legacyProviderIds)->delete();
        ProviderBranch::whereIn('provider_id', $legacyProviderIds)->delete();
        ProviderProfile::whereIn('user_id', $legacyProviderIds)->delete();
        User::whereIn('id', $legacyProviderIds)->delete();
    }
}
