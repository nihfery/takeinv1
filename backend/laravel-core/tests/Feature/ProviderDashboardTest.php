<?php

namespace Tests\Feature;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProviderDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_provider_can_open_dashboard_when_there_are_no_bookings(): void
    {
        Cache::flush();

        $provider = User::factory()->create([
            'role' => 'provider',
        ]);

        ProviderProfile::create([
            'user_id' => $provider->id,
            'status' => 'active',
            'document_status' => 'verified',
        ]);

        $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.dashboard'))
            ->assertOk()
            ->assertSee('Business overview')
            ->assertSee('Total Bookings')
            ->assertSee('Revenue trend')
            ->assertSee('Payment overview')
            ->assertSee('Top services')
            ->assertSee('Top professionals')
            ->assertSee('Business readiness');
    }

    public function test_branch_dashboard_prioritizes_only_its_operational_appointments(): void
    {
        Cache::flush();

        $provider = User::factory()->create(['role' => 'provider']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'status' => 'active',
            'document_status' => 'verified',
        ]);

        $firstBranch = $this->branch($provider, 'Central Jakarta');
        $secondBranch = $this->branch($provider, 'Central Bandung');
        $branchAccount = User::factory()->create([
            'role' => 'provider',
            'provider_id' => $provider->id,
            'branch_id' => $firstBranch->id,
        ]);

        Booking::create([
            'booking_code' => 'BRANCH-A-001',
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'estimated_end_time' => '11:00',
            'provider_id' => $provider->id,
            'branch_id' => $firstBranch->id,
            'booking_type' => 'scheduled',
            'customer_name' => 'Branch A Customer',
            'total_price' => 150000,
            'total_duration' => 60,
            'participant_count' => 1,
            'status' => 'confirmed',
        ]);

        Booking::create([
            'booking_code' => 'BRANCH-B-001',
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '11:00',
            'estimated_end_time' => '12:00',
            'provider_id' => $provider->id,
            'branch_id' => $secondBranch->id,
            'booking_type' => 'scheduled',
            'customer_name' => 'Branch B Customer',
            'total_price' => 175000,
            'total_duration' => 60,
            'participant_count' => 1,
            'status' => 'confirmed',
        ]);

        $this
            ->actingAs($branchAccount, 'provider_branch')
            ->get(route('provider-branch.dashboard'))
            ->assertOk()
            ->assertSee('Upcoming appointments')
            ->assertSee('Branch A Customer')
            ->assertDontSee('Branch B Customer');
    }

    private function branch(User $provider, string $name): ProviderBranch
    {
        return ProviderBranch::create([
            'provider_id' => $provider->id,
            'branch_name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)) . '@example.test',
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => 'Jl. Example No. 1',
            'country_id' => 'Indonesia',
            'state_id' => 'Jawa Barat',
            'city_id' => 'Bandung',
            'zip_code' => '40111',
            'working_start_hour' => '09:00',
            'working_end_hour' => '21:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'holidays' => [],
            'status' => 'active',
        ]);
    }
}
