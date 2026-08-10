<?php

namespace Tests\Feature;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use App\Modules\Staff\Infrastructure\Persistence\Models\StaffSchedule;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderWalkInSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_walk_in_form_exposes_live_schedule_controls(): void
    {
        [$provider] = $this->scheduledWalkInFixture();

        $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.walk-in.index'))
            ->assertOk()
            ->assertSee('data-walkin-availability-url', false)
            ->assertSee('name="booking_date"', false)
            ->assertSee('name="start_time"', false)
            ->assertSee('name="staff_id"', false)
            ->assertSee('Live availability')
            ->assertSee('Schedule Offline Booking')
            ->assertSeeInOrder([
                'provider-walkin-form',
                'provider/js/walk-in.js',
                '</main>',
            ], false);
    }

    public function test_provider_can_schedule_an_offline_booking_that_blocks_the_online_slot(): void
    {
        [$provider, $branch, $service, $staff, $bookingDate] = $this->scheduledWalkInFixture();

        $availabilityPayload = [
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'booking_date' => $bookingDate,
            'staff_id' => $staff->id,
        ];

        $this
            ->actingAs($provider, 'provider')
            ->postJson(route('provider.walk-in.availability'), $availabilityPayload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertContains(
            '13:00',
            collect($this
                ->actingAs($provider, 'provider')
                ->postJson(route('provider.walk-in.availability'), $availabilityPayload)
                ->json('data.available_slots'))
                ->pluck('time')
                ->all()
        );

        $this
            ->actingAs($provider, 'provider')
            ->post(route('provider.walk-in.store'), [
                'customer_name' => 'Offline Customer',
                'customer_phone' => '081234567890',
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'staff_id' => $staff->id,
                'payment_type' => 'pay_at_salon',
                'notes' => 'Booked from provider dashboard.',
            ])
            ->assertRedirect(route('provider.calendar.index', ['date' => $bookingDate]));

        $booking = Booking::query()->firstOrFail();
        $this->assertSame('walk_in', $booking->booking_type);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame($staff->id, $booking->staff_id);
        $this->assertSame('13:00:00', (string) $booking->start_time);
        $this->assertSame('14:00:00', (string) $booking->estimated_end_time);
        $this->assertNull($booking->queue_number);

        $afterBooking = $this
            ->actingAs($provider, 'provider')
            ->postJson(route('provider.walk-in.availability'), $availabilityPayload)
            ->assertOk();

        $this->assertNotContains(
            '13:00',
            collect($afterBooking->json('data.available_slots'))->pluck('time')->all()
        );
    }

    public function test_server_rejects_a_second_offline_booking_for_the_same_staff_and_time(): void
    {
        [$provider, $branch, $service, $staff, $bookingDate] = $this->scheduledWalkInFixture();
        $payload = [
            'customer_name' => 'First Offline Customer',
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'booking_date' => $bookingDate,
            'start_time' => '13:00',
            'staff_id' => $staff->id,
            'payment_type' => 'pay_at_salon',
        ];

        $this->actingAs($provider, 'provider')->post(route('provider.walk-in.store'), $payload);

        $this
            ->actingAs($provider, 'provider')
            ->from(route('provider.walk-in.index'))
            ->post(route('provider.walk-in.store'), [
                ...$payload,
                'customer_name' => 'Second Offline Customer',
            ])
            ->assertRedirect(route('provider.walk-in.index'))
            ->assertSessionHasErrors('staff_id');

        $this->assertSame(1, Booking::query()->count());
    }

    private function scheduledWalkInFixture(): array
    {
        $provider = User::factory()->create(['role' => 'provider']);

        ProviderProfile::create([
            'user_id' => $provider->id,
            'phone_number' => '08123456789',
            'status' => 'active',
            'document_status' => 'verified',
        ]);

        $bookingDate = now()->addDays(3);
        $dayName = strtolower($bookingDate->englishDayOfWeek);

        $branch = ProviderBranch::create([
            'provider_id' => $provider->id,
            'branch_name' => 'Offline Scheduling Branch ' . $provider->id,
            'email' => 'offline-branch-' . $provider->id . '@example.test',
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => 'Jl. Appointment No. 1',
            'country_id' => 'Indonesia',
            'state_id' => 'DKI Jakarta',
            'city_id' => 'Jakarta',
            'zip_code' => '12345',
            'working_start_hour' => '09:00',
            'working_end_hour' => '21:00',
            'working_days' => [$dayName],
            'holidays' => [],
            'status' => 'active',
        ]);

        $category = ServiceCategory::create([
            'name' => 'Offline Hair ' . $provider->id,
            'slug' => 'offline-hair-' . $provider->id,
            'description' => 'Offline appointment service',
            'status' => 'active',
            'is_featured' => false,
        ]);

        $service = Service::create([
            'provider_id' => $provider->id,
            'title' => 'Offline Hair Spa ' . $provider->id,
            'slug' => 'offline-hair-spa-' . $provider->id,
            'category' => $category->name,
            'category_id' => $category->id,
            'code' => 'OFFLINE' . $provider->id,
            'description' => 'Timed offline appointment',
            'includes' => 'Consultation and treatment',
            'price_type' => 'fixed',
            'price' => 100000,
            'minimum_duration' => 60,
            'estimated_duration' => 60,
            'maximum_duration' => 60,
            'is_queue_enabled' => true,
            'is_scheduled_enabled' => true,
            'requires_dp' => false,
            'slots' => [],
            'additional_services' => [],
            'holidays' => [],
            'branch_ids' => [$branch->id],
            'status' => 'active',
            'verify_status' => 'verified',
        ]);

        $staff = ProviderStaff::create([
            'provider_id' => $provider->id,
            'first_name' => 'Sari',
            'last_name' => 'Offline',
            'email' => 'offline-staff-' . $provider->id . '@example.test',
            'gender' => 'female',
            'branch_id' => $branch->id,
            'role' => 'Stylist',
            'current_status' => 'available',
            'status' => 'active',
        ]);
        $staff->skills()->attach($service->id);

        StaffSchedule::create([
            'staff_id' => $staff->id,
            'day_of_week' => $dayName,
            'start_time' => '09:00',
            'end_time' => '21:00',
            'is_available' => true,
        ]);

        return [$provider, $branch, $service, $staff, $bookingDate->toDateString()];
    }
}
