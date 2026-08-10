<?php

namespace Tests\Feature;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_loads_when_recent_bookings_exist(): void
    {
        Cache::forget('admin_dashboard:data:' . now()->format('Ymd'));

        $admin = User::factory()->create(['role' => 'admin']);
        $provider = User::factory()->create(['role' => 'provider']);
        $customer = User::factory()->create(['role' => 'customer']);

        Booking::query()->create([
            'booking_code' => 'ADMIN-DASHBOARD-001',
            'booking_date' => now()->addDay()->toDateString(),
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'booking_type' => 'scheduled',
            'total_price' => 150000,
            'status' => 'open',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard', ['tab' => 'order']))
            ->assertOk()
            ->assertSee('ADMIN-DASHBOARD-001');
    }
}
