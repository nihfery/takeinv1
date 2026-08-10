<?php
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRole;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;

$providerId = 129;

// Clear existing to avoid duplicates
ProviderBranch::where('provider_id', $providerId)->delete();
Service::where('provider_id', $providerId)->delete();
ProviderStaff::where('provider_id', $providerId)->delete();
ProviderRole::where('provider_id', $providerId)->delete();
Booking::where('provider_id', $providerId)->delete();

echo "Cleared existing data for provider $providerId.\n";

// 1. Branches (5)
for ($i = 1; $i <= 5; $i++) {
    ProviderBranch::create([
        'provider_id' => $providerId,
        'branch_name' => "Cabang Dummy $i",
        'phone_code' => "62",
        'phone_number' => "812345678$i",
        'address' => "Jalan Dummy $i",
        'is_primary' => $i === 1,
        'is_active' => true,
    ]);
}
$branchId = ProviderBranch::where('provider_id', $providerId)->first()->id;
echo "Created 5 branches.\n";

// 2. Services (5)
for ($i = 1; $i <= 5; $i++) {
    Service::create([
        'provider_id' => $providerId,
        'title' => "Layanan Dummy $i",
        'slug' => "layanan-dummy-$i",
        'duration_minutes' => 30,
        'price_type' => 'fixed',
        'price' => 50000 * $i,
        'is_active' => true,
    ]);
}
$serviceId = Service::where('provider_id', $providerId)->first()->id;
echo "Created 5 services.\n";

// 3. Roles (5)
for ($i = 1; $i <= 5; $i++) {
    ProviderRole::create([
        'provider_id' => $providerId,
        'role_name' => "Role Dummy $i",
        'slug' => "role-dummy-$i",
    ]);
}
$roleId = ProviderRole::where('provider_id', $providerId)->first()->id;
echo "Created 5 roles.\n";

// 4. Staffs (4) - This one has 4!
for ($i = 1; $i <= 4; $i++) {
    ProviderStaff::create([
        'provider_id' => $providerId,
        'branch_id' => $branchId,
        'role_id' => $roleId,
        'first_name' => "Staff",
        'last_name' => "Dummy $i",
        'username' => "staffdummy$i",
        'email' => "staff$i@dummy.com",
        'password' => bcrypt('password'),
        'phone_code' => '62',
        'phone_number' => "898765432$i",
        'is_active' => true,
    ]);
}
$staffId = ProviderStaff::where('provider_id', $providerId)->first()->id;
echo "Created 4 staffs.\n";

// Create a dummy customer
$customer = User::firstOrCreate(
    ['email' => 'customerdummy@test.com'],
    [
        'name' => 'Customer Dummy',
        'username' => 'customerdummy',
        'password' => bcrypt('password'),
        'role' => 'customer'
    ]
);

// 5. Bookings (Walk-in) (5)
for ($i = 1; $i <= 5; $i++) {
    Booking::create([
        'provider_id' => $providerId,
        'booking_code' => 'DUMMY' . $i . time(),
        'branch_id' => $branchId,
        'service_id' => $serviceId,
        'staff_id' => $staffId,
        'customer_id' => $customer->id,
        'booking_date' => now()->addDays($i)->format('Y-m-d'),
        'start_time' => '10:00:00',
        'end_time' => '10:30:00',
        'status' => 'pending',
        'is_walk_in' => true,
        'total_price' => 50000,
    ]);
}
echo "Created 5 walk-in bookings.\n";
echo "Done!\n";
