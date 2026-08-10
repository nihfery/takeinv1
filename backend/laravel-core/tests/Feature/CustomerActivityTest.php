<?php

namespace Tests\Feature;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerActivity;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalized_booking_is_automatically_added_to_customer_activity(): void
    {
        [$customer, $branch, $service] = $this->bookingFixture();
        $booking = $this->createBooking($customer, $branch, 'confirmed');
        $booking->services()->attach($service->id, [
            'price' => 100000,
            'estimated_duration' => 60,
        ]);
        Payment::create([
            'booking_id' => $booking->id,
            'payment_type' => 'pay_at_salon',
            'amount' => 0,
            'status' => 'unpaid',
            'payment_method' => 'pay_at_salon',
        ]);

        $this->assertDatabaseHas('customer_activities', [
            'customer_id' => $customer->id,
            'booking_id' => $booking->id,
        ]);

        $this
            ->actingAs($customer, 'sanctum')
            ->getJson(route('api.customer.activity.show'))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.booking_id', $booking->id)
            ->assertJsonPath('data.0.code', $booking->booking_code)
            ->assertJsonPath('data.0.salon_name', $branch->branch_name)
            ->assertJsonPath('data.0.services.0.id', $service->id)
            ->assertJsonPath('data.0.payment_status', 'unpaid');
    }

    public function test_temporary_hold_is_not_added_to_customer_activity(): void
    {
        [$customer, $branch] = $this->bookingFixture();
        $booking = $this->createBooking($customer, $branch, 'pending_hold');

        $this->assertDatabaseMissing('customer_activities', [
            'booking_id' => $booking->id,
        ]);

        $booking->update(['status' => 'pending_payment']);

        $this->assertDatabaseHas('customer_activities', [
            'customer_id' => $customer->id,
            'booking_id' => $booking->id,
        ]);
    }

    public function test_activity_summary_is_scoped_to_authenticated_customer(): void
    {
        [$customer, $branch] = $this->bookingFixture();
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $this->createBooking($customer, $branch, 'confirmed');
        $this->createBooking($otherCustomer, $branch, 'confirmed');

        $this
            ->actingAs($customer, 'sanctum')
            ->getJson(route('api.customer.activity.summary'))
            ->assertOk()
            ->assertJsonPath('has_activity', true)
            ->assertJsonPath('count', 1);

        $this->assertSame(2, CustomerActivity::query()->count());
    }

    private function createBooking(User $customer, ProviderBranch $branch, string $status): Booking
    {
        return Booking::create([
            'booking_code' => 'BK-TEST-' . $customer->id . '-' . fake()->unique()->numberBetween(1000, 9999),
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '13:00',
            'estimated_end_time' => '14:00',
            'provider_id' => $branch->provider_id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'booking_type' => 'scheduled',
            'total_price' => 100000,
            'total_duration' => 60,
            'participant_count' => 1,
            'status' => $status,
        ]);
    }

    private function bookingFixture(): array
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $provider = User::factory()->create(['role' => 'provider']);
        $branch = ProviderBranch::create([
            'provider_id' => $provider->id,
            'branch_name' => 'Glow Salon ' . $provider->id,
            'email' => 'glow-' . $provider->id . '@example.test',
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => 'Jl. Mawar',
            'country_id' => 'Indonesia',
            'state_id' => 'DKI Jakarta',
            'city_id' => 'Jakarta',
            'zip_code' => '12345',
            'working_start_hour' => '09:00',
            'working_end_hour' => '21:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'holidays' => [],
            'status' => 'active',
        ]);
        $service = Service::create([
            'provider_id' => $provider->id,
            'title' => 'Hair Spa',
            'slug' => 'hair-spa-' . $provider->id,
            'category' => 'Hair',
            'code' => 'HAIRSPA' . $provider->id,
            'price_type' => 'fixed',
            'price' => 100000,
            'minimum_duration' => 50,
            'estimated_duration' => 60,
            'maximum_duration' => 80,
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

        return [$customer, $branch, $service];
    }
}
