<?php

namespace Tests\Feature;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderGroupBookingDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_booking_is_displayed_as_separate_participant_appointments(): void
    {
        [$provider, $branch, $firstStaff, $secondStaff, $firstService, $secondService] = $this->fixture();
        $bookingDate = now()->addDays(3)->toDateString();

        $booking = Booking::create([
            'booking_code' => 'GROUP-1001',
            'booking_date' => $bookingDate,
            'start_time' => '10:00',
            'estimated_end_time' => '11:00',
            'provider_id' => $provider->id,
            'customer_id' => null,
            'branch_id' => $branch->id,
            'staff_id' => $firstStaff->id,
            'booking_type' => 'scheduled',
            'total_price' => 150000,
            'total_duration' => 90,
            'customer_name' => 'Nadia Pemesan',
            'customer_phone' => '081200000001',
            'participant_count' => 2,
            'status' => 'confirmed',
        ]);

        $booking->services()->attach([
            $firstService->id => ['price' => 100000, 'estimated_duration' => 60],
            $secondService->id => ['price' => 50000, 'estimated_duration' => 30],
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'payment_type' => 'pay_at_salon',
            'amount' => 0,
            'status' => 'unpaid',
            'payment_method' => 'pay_at_salon',
        ]);

        $firstParticipant = $booking->participants()->create([
            'position' => 1,
            'is_primary' => true,
            'name' => 'Nadia Pemesan',
            'phone' => '081200000001',
            'provider_staff_id' => $firstStaff->id,
            'booking_date' => $bookingDate,
            'start_time' => '10:00',
            'estimated_end_time' => '11:00',
            'total_duration' => 60,
            'total_price' => 100000,
        ]);
        $firstParticipant->services()->attach($firstService->id, [
            'price' => 100000,
            'estimated_duration' => 60,
        ]);

        $secondParticipant = $booking->participants()->create([
            'position' => 2,
            'is_primary' => false,
            'name' => 'Rina Tamu',
            'phone' => '081200000002',
            'provider_staff_id' => $secondStaff->id,
            'booking_date' => $bookingDate,
            'start_time' => '13:00',
            'estimated_end_time' => '13:30',
            'total_duration' => 30,
            'total_price' => 50000,
        ]);
        $secondParticipant->services()->attach($secondService->id, [
            'price' => 50000,
            'estimated_duration' => 30,
        ]);

        $entries = $booking->refresh()->operationalEntries();

        $this->assertCount(2, $entries);
        $this->assertSame('Nadia Pemesan', $entries[0]->customer_name);
        $this->assertSame('10:00:00', (string) $entries[0]->start_time);
        $this->assertSame($firstStaff->id, $entries[0]->staff->id);
        $this->assertSame('Rina Tamu', $entries[1]->customer_name);
        $this->assertSame('13:00:00', (string) $entries[1]->start_time);
        $this->assertSame($secondStaff->id, $entries[1]->staff->id);

        $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.bookings.index'))
            ->assertOk()
            ->assertSee('GROUP-1001-P1')
            ->assertSee('GROUP-1001-P2')
            ->assertSee('Nadia Pemesan')
            ->assertSee('Rina Tamu')
            ->assertSee('10:00 - 11:00')
            ->assertSee('13:00 - 13:30')
            ->assertSee($firstStaff->full_name)
            ->assertSee($secondStaff->full_name);

        $calendarResponse = $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.calendar.index', ['date' => $bookingDate]));

        $calendarResponse
            ->assertOk()
            ->assertSee('provider-resource-scheduler is-day is-single-day', false)
            ->assertSee('class="active">Day</a>', false)
            ->assertSee('GROUP-1001-P1')
            ->assertSee('GROUP-1001-P2')
            ->assertSee('Nadia Pemesan')
            ->assertSee('Rina Tamu')
            ->assertSee('10:00')
            ->assertSee('13:00');

        $calendarResponse->assertSeeInOrder([
            $secondStaff->full_name,
            'Rina Tamu',
            $firstStaff->full_name,
            'Nadia Pemesan',
        ]);
    }

    public function test_provider_calendar_filters_today_seven_days_month_and_year_ranges(): void
    {
        [$provider, $branch, $staff, , $service] = $this->fixture();

        $createBooking = function (string $code, string $bookingDate) use ($provider, $branch, $staff, $service): Booking {
            $booking = Booking::create([
                'booking_code' => $code,
                'booking_date' => $bookingDate,
                'start_time' => '10:00',
                'estimated_end_time' => '11:00',
                'provider_id' => $provider->id,
                'customer_id' => null,
                'branch_id' => $branch->id,
                'staff_id' => $staff->id,
                'booking_type' => 'scheduled',
                'total_price' => 100000,
                'total_duration' => 60,
                'customer_name' => $code.' Customer',
                'customer_phone' => '081200000000',
                'participant_count' => 1,
                'status' => 'confirmed',
            ]);

            $booking->services()->attach($service->id, [
                'price' => 100000,
                'estimated_duration' => 60,
            ]);

            return $booking;
        };

        $selectedDay = $createBooking('RANGE-DAY', '2026-05-10');
        $seventhDay = $createBooking('RANGE-SEVENTH', '2026-05-16');
        $eighthDay = $createBooking('RANGE-EIGHTH', '2026-05-17');
        $nextMonth = $createBooking('RANGE-NEXT-MONTH', '2026-06-01');
        $sameYear = $createBooking('RANGE-SAME-YEAR', '2026-12-10');
        $nextYear = $createBooking('RANGE-NEXT-YEAR', '2027-01-05');

        $bookingIds = fn ($response) => $response->viewData('calendarEntries')
            ->map(fn ($entry) => $entry->booking->id)
            ->unique()
            ->values()
            ->all();

        $todayResponse = $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.calendar.index', ['view' => 'today', 'date' => '2026-05-10']))
            ->assertOk()
            ->assertSee('provider-resource-scheduler is-day', false)
            ->assertSee('provider-resource-single-day-head', false)
            ->assertSee('data-appointment-column="all"', false)
            ->assertSee('data-calendar-zoom-out', false)
            ->assertSee('data-calendar-zoom-in', false)
            ->assertSee('class="active">Day</a>', false);
        $this->assertSame([$selectedDay->id], $bookingIds($todayResponse));

        $weekResponse = $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.calendar.index', ['view' => 'week', 'date' => '2026-05-10']))
            ->assertOk()
            ->assertSee('provider-resource-scheduler is-week', false)
            ->assertSee('data-calendar-zoom-out', false)
            ->assertSee('data-calendar-zoom-in', false)
            ->assertSee('class="active">7 Days</a>', false);
        $this->assertSame([$selectedDay->id, $seventhDay->id], $bookingIds($weekResponse));

        $monthResponse = $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.calendar.index', ['view' => 'month', 'date' => '2026-05-10']))
            ->assertOk()
            ->assertSee('provider-month-calendar-grid', false)
            ->assertSee('data-calendar-zoom-out', false)
            ->assertSee('data-calendar-zoom-in', false)
            ->assertSee('class="active">Month</a>', false);
        $this->assertSame([$selectedDay->id, $seventhDay->id, $eighthDay->id], $bookingIds($monthResponse));

        $yearResponse = $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.calendar.index', ['view' => 'year', 'date' => '2026-05-10']))
            ->assertOk()
            ->assertSee('provider-calendar-year-grid', false)
            ->assertSee('data-calendar-zoom-out', false)
            ->assertSee('data-calendar-zoom-in', false)
            ->assertSee('class="active">Year</a>', false);
        $this->assertSame(
            [$selectedDay->id, $seventhDay->id, $eighthDay->id, $nextMonth->id, $sameYear->id],
            $bookingIds($yearResponse)
        );
        $this->assertNotContains($nextYear->id, $bookingIds($yearResponse));
    }

    public function test_day_calendar_uses_all_appointments_timeline_combined_service_duration_and_customer_profile_details(): void
    {
        [$provider, $branch, $staff, $otherStaff, $firstService, $secondService] = $this->fixture();
        $customer = User::factory()->create([
            'role' => 'customer',
            'name' => 'Alya Detail',
            'email' => 'alya-detail@example.test',
        ]);
        CustomerProfile::create([
            'user_id' => $customer->id,
            'phone_number' => '081298765432',
            'gender' => 'female',
            'date_of_birth' => '1997-04-12',
            'religion' => 'Islam',
            'allergies' => 'Alergi pewarna rambut',
            'address_line_1' => 'Jl. Melati 10',
            'city' => 'Bandung',
            'status' => 'active',
        ]);

        $booking = Booking::create([
            'booking_code' => 'DETAIL-DAY-001',
            'booking_date' => '2026-05-10',
            'start_time' => '10:00',
            'estimated_end_time' => '10:30',
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'booking_type' => 'scheduled',
            'total_price' => 150000,
            'total_duration' => 90,
            'customer_name' => 'Alya Detail',
            'customer_phone' => '081298765432',
            'participant_count' => 1,
            'notes' => 'Gunakan produk untuk kulit sensitif.',
            'status' => 'confirmed',
        ]);
        $booking->services()->attach([
            $firstService->id => ['price' => 100000, 'estimated_duration' => 60],
            $secondService->id => ['price' => 50000, 'estimated_duration' => 30],
        ]);
        Payment::create([
            'booking_id' => $booking->id,
            'payment_type' => 'pay_at_salon',
            'amount' => 0,
            'status' => 'unpaid',
            'payment_method' => 'pay_at_salon',
        ]);

        $nextBooking = Booking::create([
            'booking_code' => 'DETAIL-DAY-002',
            'booking_date' => '2026-05-10',
            'start_time' => '11:30',
            'estimated_end_time' => '12:30',
            'provider_id' => $provider->id,
            'customer_id' => null,
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'booking_type' => 'scheduled',
            'total_price' => 100000,
            'total_duration' => 60,
            'customer_name' => 'Booking Berikutnya',
            'customer_phone' => '081200001111',
            'participant_count' => 1,
            'status' => 'confirmed',
        ]);
        $nextBooking->services()->attach($firstService->id, [
            'price' => 100000,
            'estimated_duration' => 60,
        ]);

        $response = $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.calendar.index', ['view' => 'today', 'date' => '2026-05-10']))
            ->assertOk();

        $response
            ->assertSee('data-appointment-column="all"', false)
            ->assertSee('All Appointments')
            ->assertSee('data-calendar-entry="booking-'.$booking->id.'-participant-1"', false)
            ->assertSee('--scheduler-top-padding: 20px', false)
            ->assertSee('--scheduler-height-hour: 2560px', false)
            ->assertSee('is-full-hour" style="--time-top-hour: 20px; --time-top-half-hour: 20px;">07:00', false)
            ->assertSee('is-half-hour" style="--time-top-hour: 104px; --time-top-half-hour: 140px;">07:30', false)
            ->assertSee('is-full-hour" style="--time-top-hour: 2540px; --time-top-half-hour: 3620px;">22:00', false)
            ->assertSee('provider-resource-grid-line is-half-hour', false)
            ->assertSee('provider-resource-grid-line is-full-hour', false)
            ->assertSee('--event-top-hour: 526px; --event-top-half-hour: 742px; --event-height-hour: 248px; --event-height-half-hour: 356px', false)
            ->assertSee('--event-top-hour: 778px; --event-top-half-hour: 1102px; --event-height-hour: 164px; --event-height-half-hour: 236px', false)
            ->assertSee('10:00 - 11:30')
            ->assertSee('11:30 - 12:30')
            ->assertDontSee('provider-resource-now-line', false)
            ->assertSee('data-calendar-entry-card="booking-'.$booking->id.'-participant-1"', false)
            ->assertSee('filterModalAppointments', false)
            ->assertSee('is-single-appointment', false)
            ->assertSee('Hair Spa Group')
            ->assertSee('Express Facial Group')
            ->assertSee('Alergi pewarna rambut')
            ->assertSee('provider-calendar-gender-icon is-female', false)
            ->assertSee('aria-label="Gender: Female"', false)
            ->assertSee('12 Apr 1997')
            ->assertSee('Gunakan produk untuk kulit sensitif.');
    }

    private function fixture(): array
    {
        $provider = User::factory()->create([
            'role' => 'provider',
        ]);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'status' => 'active',
            'document_status' => 'verified',
        ]);

        $branch = ProviderBranch::create([
            'provider_id' => $provider->id,
            'branch_name' => 'Glow Group Salon',
            'email' => 'group-salon@example.test',
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => 'Jl. Mawar',
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

        $firstStaff = ProviderStaff::create([
            'provider_id' => $provider->id,
            'first_name' => 'Sari',
            'last_name' => 'Wijaya',
            'email' => 'sari-group@example.test',
            'gender' => 'female',
            'branch_id' => $branch->id,
            'role' => 'Stylist',
            'current_status' => 'available',
            'status' => 'active',
        ]);
        $secondStaff = ProviderStaff::create([
            'provider_id' => $provider->id,
            'first_name' => 'Maya',
            'last_name' => 'Putri',
            'email' => 'maya-group@example.test',
            'gender' => 'female',
            'branch_id' => $branch->id,
            'role' => 'Therapist',
            'current_status' => 'available',
            'status' => 'active',
        ]);

        $serviceDefaults = [
            'provider_id' => $provider->id,
            'category' => 'Beauty',
            'price_type' => 'fixed',
            'minimum_duration' => 20,
            'maximum_duration' => 90,
            'is_queue_enabled' => true,
            'is_scheduled_enabled' => true,
            'requires_dp' => false,
            'slots' => [],
            'additional_services' => [],
            'holidays' => [],
            'branch_ids' => [$branch->id],
            'status' => 'active',
            'verify_status' => 'verified',
        ];

        $firstService = Service::create(array_merge($serviceDefaults, [
            'title' => 'Hair Spa Group',
            'slug' => 'hair-spa-group',
            'code' => 'GROUPHAIR',
            'price' => 100000,
            'estimated_duration' => 60,
        ]));
        $secondService = Service::create(array_merge($serviceDefaults, [
            'title' => 'Express Facial Group',
            'slug' => 'express-facial-group',
            'code' => 'GROUPFACE',
            'price' => 50000,
            'estimated_duration' => 30,
        ]));

        return [$provider, $branch, $firstStaff, $secondStaff, $firstService, $secondService];
    }
}
