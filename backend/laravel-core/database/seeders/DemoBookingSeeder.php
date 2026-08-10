<?php

namespace Database\Seeders;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Booking\Application\Services\BookingFlowService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoBookingSeeder extends Seeder
{
    public function run(): void
    {
        $provider = User::where('email', 'provider-pusat@demo.test')->firstOrFail();
        $customer = User::where('email', 'customer@gmail.com')->firstOrFail();
        $bookingFlow = app(BookingFlowService::class);

        Payment::whereHas('booking', function ($query) use ($provider) {
            $query->where('provider_id', $provider->id)
                ->where('notes', 'like', 'Demo branch data:%');
        })->delete();

        Booking::where('provider_id', $provider->id)
            ->where('notes', 'like', 'Demo branch data:%')
            ->delete();

        ProviderBranch::query()
            ->where('provider_id', $provider->id)
            ->where('status', 'active')
            ->orderBy('branch_name')
            ->get()
            ->each(function (ProviderBranch $branch, int $index) use ($bookingFlow, $customer) {
                $services = $this->servicesForBranch($branch);
                $staff = ProviderStaff::query()
                    ->where('provider_id', $branch->provider_id)
                    ->where('branch_id', $branch->id)
                    ->where('status', 'active')
                    ->orderBy('first_name')
                    ->first();

                if ($services->isEmpty()) {
                    return;
                }

                $completed = $bookingFlow->createBooking([
                    'branch_id' => $branch->id,
                    'service_ids' => [$services->first()->id],
                    'booking_type' => 'scheduled',
                    'staff_id' => $staff?->id,
                    'booking_date' => $this->nextWorkingDate()->toDateString(),
                    'start_time' => '09:30',
                    'payment_type' => 'pay_at_salon',
                    'notes' => 'Demo branch data: completed ' . $branch->city_id,
                ], $customer);

                $this->markPaidAndCompleted($completed, now()->subDays($index + 1));

                $bookingFlow->createBooking([
                    'branch_id' => $branch->id,
                    'service_ids' => [$services->first()->id],
                    'booking_type' => 'queue',
                    'booking_date' => now()->toDateString(),
                    'payment_type' => 'pay_at_salon',
                    'customer_name' => 'Queue Customer ' . $branch->city_id,
                    'customer_phone' => '0812888' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'notes' => 'Demo branch data: queue ' . $branch->city_id,
                ], null, true);

                $bookingFlow->createBooking([
                    'branch_id' => $branch->id,
                    'service_ids' => $services->take(2)->pluck('id')->all(),
                    'booking_type' => 'scheduled',
                    'staff_id' => $staff?->id,
                    'booking_date' => $this->nextWorkingDate(2 + $index)->toDateString(),
                    'start_time' => '11:00',
                    'payment_type' => $index % 2 === 0 ? 'dp' : 'full_payment',
                    'notes' => 'Demo branch data: upcoming ' . $branch->city_id,
                ], $customer);
            });
    }

    private function servicesForBranch(ProviderBranch $branch)
    {
        return Service::query()
            ->where('provider_id', $branch->provider_id)
            ->where('status', 'active')
            ->get()
            ->filter(function (Service $service) use ($branch) {
                $branchIds = $service->branch_ids;

                if (empty($branchIds)) {
                    return true;
                }

                return in_array((int) $branch->id, array_map('intval', (array) $branchIds), true);
            })
            ->values();
    }

    private function markPaidAndCompleted(Booking $booking, Carbon $completedAt): void
    {
        $booking->payment?->update([
            'amount' => $booking->total_price,
            'status' => 'paid',
            'payment_method' => 'pay_at_salon',
            'paid_at' => $completedAt,
        ]);

        $booking->update([
            'booking_date' => $completedAt->toDateString(),
            'status' => 'completed',
            'actual_start_time' => $completedAt->copy()->subMinutes((int) ($booking->total_duration ?: 45)),
            'actual_end_time' => $completedAt,
            'completed_at' => $completedAt,
        ]);
    }

    private function nextWorkingDate(int $daysFromNow = 1): Carbon
    {
        $date = now()->addDays($daysFromNow);

        while ($date->isSunday()) {
            $date->addDay();
        }

        return $date;
    }
}
