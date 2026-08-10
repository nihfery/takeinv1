<?php

namespace Database\Seeders;

use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Seeder;

class DemoStaffSkillSeeder extends Seeder
{
    public function run(): void
    {
        $provider = User::where('email', 'provider-pusat@demo.test')->firstOrFail();

        ProviderStaff::query()
            ->where('provider_id', $provider->id)
            ->with('branch')
            ->get()
            ->each(function (ProviderStaff $staff) use ($provider) {
                $serviceIds = Service::query()
                    ->where('provider_id', $provider->id)
                    ->where('status', 'active')
                    ->get()
                    ->filter(function (Service $service) use ($staff) {
                        $branchIds = $service->branch_ids;

                        if (empty($branchIds)) {
                            return true;
                        }

                        return in_array((int) $staff->branch_id, array_map('intval', (array) $branchIds), true);
                    })
                    ->pluck('id')
                    ->all();

                $staff->skills()->sync($serviceIds);
            });
    }
}
