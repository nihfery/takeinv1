<?php

namespace Database\Seeders;

use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Staff\Infrastructure\Persistence\Models\StaffSchedule;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Seeder;

class DemoStaffScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $provider = User::where('email', 'provider-pusat@demo.test')->firstOrFail();
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        ProviderStaff::query()
            ->where('provider_id', $provider->id)
            ->get()
            ->each(function (ProviderStaff $staff) use ($days) {
                foreach ($days as $day) {
                    StaffSchedule::updateOrCreate(
                        [
                            'staff_id' => $staff->id,
                            'day_of_week' => $day,
                        ],
                        [
                            'start_time' => '09:00',
                            'end_time' => '18:00',
                            'is_available' => true,
                        ]
                    );
                }
            });
    }
}
