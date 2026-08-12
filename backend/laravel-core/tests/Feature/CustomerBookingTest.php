<?php

namespace Tests\Feature;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Payment\Infrastructure\Gateways\Midtrans\MidtransService;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Promotion\Infrastructure\Persistence\Models\Coupon;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Review\Infrastructure\Persistence\Models\StaffReview;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Staff\Infrastructure\Persistence\Models\StaffSchedule;
use App\Modules\Subscription\Infrastructure\Persistence\Models\ProviderSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_validation_prices_repeated_services_for_group_bookings(): void
    {
        [, $service] = $this->bookableBranchServiceAndStaff();
        Coupon::query()->create([
            'code' => 'GROUP20',
            'product_type' => 'all',
            'coupon_type' => 'percentage',
            'coupon_value' => 20,
            'quantity' => 20,
            'used_count' => 0,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'active',
        ]);

        $this->postJson(route('api.coupons.validate'), [
            'coupon_code' => 'GROUP20',
            'service_ids' => [$service->id, $service->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 200000)
            ->assertJsonPath('data.eligible_subtotal', 200000)
            ->assertJsonPath('data.discount_amount', 40000)
            ->assertJsonPath('data.payable_amount', 168000);
    }

    public function test_customer_booking_persists_booking_services_and_payment_rows(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'full_payment',
                'payment_channel' => 'qris',
                'notes' => 'Datang 10 menit lebih awal.',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $branch->id)
            ->assertJsonPath('data.staff_id', $staff->id)
            ->assertJsonPath('data.payment.payment_channel', 'qris')
            ->assertJsonPath('data.services.0.id', $service->id)
            ->assertJsonCount(0, 'data.participants');

        $booking = Booking::query()->firstOrFail();
        $payment = Payment::query()->firstOrFail();

        $this->assertSame($customer->id, $booking->customer_id);
        $this->assertSame($branch->provider_id, $booking->provider_id);
        $this->assertSame($branch->id, $booking->branch_id);
        $this->assertSame($staff->id, $booking->staff_id);
        $this->assertSame($service->id, $booking->services()->firstOrFail()->id);
        $this->assertSame('pending_payment', $booking->status);
        $this->assertSame('pending', $booking->payment_status);
        $this->assertSame('13:00:00', (string) $booking->start_time);
        $this->assertSame('14:00:00', (string) $booking->estimated_end_time);

        $this->assertDatabaseHas('booking_services', [
            'booking_id' => $booking->id,
            'service_id' => $service->id,
            'estimated_duration' => 60,
        ]);
        $this->assertDatabaseMissing('booking_participants', [
            'booking_id' => $booking->id,
        ]);
        $this->assertSame($booking->id, $payment->booking_id);
        $this->assertSame('full_payment', $payment->payment_type);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('manual', $payment->payment_method);
        $this->assertSame('qris', $payment->payment_channel);
        $this->assertSame(105000.0, (float) $payment->amount);
    }

    public function test_customer_can_finalize_one_group_booking_with_guest_details(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'name' => 'Nadia Pemesan',
            'email' => 'nadia@example.test',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $holdResponse = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '12:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
                'participant_count' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.participant_count', 3)
            ->assertJsonPath('data.total_duration', 180)
            ->assertJsonPath('data.estimated_end_time', '15:00:00');

        $bookingId = $holdResponse->json('data.id');

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.finalize', $bookingId), [
                'payment_type' => 'full_payment',
                'payment_channel' => 'qris',
                'participant_count' => 3,
                'guests' => [
                    [
                        'name' => 'Alya Putri',
                        'phone' => '081234567801',
                        'email' => 'alya@example.test',
                        'gender' => 'female',
                        'age_group' => 'teen',
                        'description' => 'Sensitive scalp; use gentle products.',
                    ],
                    [
                        'name' => 'Rani Sari',
                        'phone' => '081234567802',
                        'gender' => 'female',
                        'age_group' => 'adult',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.participant_count', 3)
            ->assertJsonPath('data.participants.1.gender', 'female')
            ->assertJsonPath('data.participants.1.age_group', 'teen')
            ->assertJsonPath('data.participants.1.description', 'Sensitive scalp; use gentle products.')
            ->assertJsonCount(3, 'data.participants');

        $booking = Booking::query()->findOrFail($bookingId);

        $this->assertSame(3, $booking->participant_count);
        $this->assertSame(180, $booking->total_duration);
        $this->assertSame(315000.0, (float) $booking->total_price);
        $this->assertDatabaseHas('booking_participants', [
            'booking_id' => $booking->id,
            'position' => 1,
            'is_primary' => true,
            'name' => 'Nadia Pemesan',
        ]);
        $this->assertDatabaseHas('booking_participants', [
            'booking_id' => $booking->id,
            'position' => 2,
            'is_primary' => false,
            'name' => 'Alya Putri',
            'phone' => '081234567801',
            'gender' => 'female',
            'age_group' => 'teen',
            'description' => 'Sensitive scalp; use gentle products.',
        ]);
        $this->assertDatabaseHas('booking_participants', [
            'booking_id' => $booking->id,
            'position' => 3,
            'is_primary' => false,
            'name' => 'Rani Sari',
            'phone' => '081234567802',
            'gender' => 'female',
            'age_group' => 'adult',
        ]);
    }

    public function test_group_booking_requires_identity_for_every_additional_person(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => now()->addDays(3)->toDateString(),
                'start_time' => '13:00',
                'payment_type' => 'pay_at_salon',
                'participant_count' => 2,
                'guests' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guests');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_group_booking_can_store_independent_service_staff_and_time_for_each_participant(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'name' => 'Nadia Pemesan',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $secondService = $service->replicate();
        $secondService->fill([
            'title' => 'Express Styling '.$branch->provider_id,
            'slug' => 'express-styling-'.$branch->provider_id,
            'code' => 'EXPRESS'.$branch->provider_id,
            'price' => 50000,
            'minimum_duration' => 20,
            'estimated_duration' => 30,
            'maximum_duration' => 40,
        ]);
        $secondService->save();

        $secondStaff = ProviderStaff::create([
            'provider_id' => $branch->provider_id,
            'first_name' => 'Maya',
            'last_name' => 'Putri',
            'email' => 'maya-'.$branch->provider_id.'@example.test',
            'gender' => 'female',
            'branch_id' => $branch->id,
            'role' => 'Stylist',
            'current_status' => 'available',
            'status' => 'active',
        ]);
        $secondStaff->skills()->attach($secondService->id);
        StaffSchedule::create([
            'staff_id' => $secondStaff->id,
            'day_of_week' => strtolower(now()->addDays(3)->englishDayOfWeek),
            'start_time' => '09:00',
            'end_time' => '21:00',
            'is_available' => true,
        ]);

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '12:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
                'participant_count' => 2,
                'participant_selections' => [
                    [
                        'position' => 1,
                        'is_primary' => true,
                        'service_ids' => [$service->id],
                        'staff_id' => $staff->id,
                        'booking_date' => $bookingDate,
                        'start_time' => '12:00',
                    ],
                    [
                        'position' => 2,
                        'service_ids' => [$secondService->id],
                        'staff_id' => $secondStaff->id,
                        'booking_date' => $bookingDate,
                        'start_time' => '13:00',
                    ],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.participant_count', 2)
            ->assertJsonPath('data.total_duration', 90)
            ->assertJsonPath('data.participants.0.staff.id', $staff->id)
            ->assertJsonPath('data.participants.0.services.0.id', $service->id)
            ->assertJsonPath('data.participants.1.staff.id', $secondStaff->id)
            ->assertJsonPath('data.participants.1.services.0.id', $secondService->id)
            ->assertJsonPath('data.participants.1.start_time', '13:00:00');

        $bookingId = $response->json('data.id');
        $this->assertDatabaseHas('booking_participants', [
            'booking_id' => $bookingId,
            'position' => 1,
            'provider_staff_id' => $staff->id,
            'start_time' => '12:00:00',
            'total_duration' => 60,
        ]);
        $this->assertDatabaseHas('booking_participants', [
            'booking_id' => $bookingId,
            'position' => 2,
            'provider_staff_id' => $secondStaff->id,
            'start_time' => '13:00:00',
            'total_duration' => 30,
        ]);
        $this->assertDatabaseHas('booking_participant_services', [
            'service_id' => $secondService->id,
            'estimated_duration' => 30,
        ]);
    }

    public function test_group_booking_availability_uses_total_duration_for_all_participants(): void
    {
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();

        $response = $this->postJson(route('api.customer.graphql'), [
            'operationName' => 'CustomerBookingAvailability',
            'query' => 'query CustomerBookingAvailability { customerBookingAvailability { available_slots estimated_duration total_price participant_count } }',
            'variables' => [
                'branchId' => $branch->id,
                'serviceIds' => [$service->id],
                'bookingDate' => now()->addDays(3)->toDateString(),
                'staffId' => $staff->id,
                'participantCount' => 3,
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.customerBookingAvailability.participant_count', 3)
            ->assertJsonPath('data.customerBookingAvailability.estimated_duration', 180)
            ->assertJsonPath('data.customerBookingAvailability.total_price', 300000);

        $this->assertContains(
            '12:00',
            collect($response->json('data.customerBookingAvailability.available_slots'))->pluck('time')->all()
        );
    }

    public function test_group_booking_rejects_overlapping_time_for_the_same_staff(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '09:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
                'participant_count' => 2,
                'participant_selections' => [
                    [
                        'position' => 1,
                        'service_ids' => [$service->id],
                        'staff_id' => $staff->id,
                        'booking_date' => $bookingDate,
                        'start_time' => '09:00',
                    ],
                    [
                        'position' => 2,
                        'service_ids' => [$service->id],
                        'staff_id' => $staff->id,
                        'booking_date' => $bookingDate,
                        'start_time' => '09:00',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('participant_selections.1.staff_id');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_customer_can_confirm_payment_by_booking_code(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'full_payment',
                'payment_channel' => 'qris',
            ])
            ->assertCreated();

        $booking = Booking::query()->firstOrFail();

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.payment.confirm-by-code', $booking->booking_code))
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.payment.status', 'paid');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'status' => 'paid',
        ]);
    }

    public function test_production_mode_rejects_browser_only_payment_confirmation(): void
    {
        config(['payments.allow_customer_manual_confirmation' => false]);

        $customer = User::factory()->create(['role' => 'customer']);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => now()->addDays(3)->toDateString(),
                'start_time' => '13:00',
                'payment_type' => 'full_payment',
                'payment_channel' => 'qris',
            ])
            ->assertCreated();

        $booking = Booking::query()->firstOrFail();

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.payment.confirm-by-code', $booking->booking_code))
            ->assertStatus(409);

        $this->assertNotSame('confirmed', $booking->fresh()->status);
        $this->assertNotSame('paid', $booking->payment->fresh()->status);
    }

    public function test_customer_can_hold_slot_for_three_minutes_before_finalizing_booking(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        $otherCustomer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();
        $clientHoldExpiresAt = now()->addSeconds(150)->utc()->toIso8601String();

        $holdResponse = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
                'booking_hold_expires_at' => $clientHoldExpiresAt,
            ]);

        $holdResponse
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_hold')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.hold_expires_at', fn ($value) => is_string($value) && $value !== '');

        $booking = Booking::query()->firstOrFail();

        $this->assertNotNull($booking->hold_expires_at);
        $this->assertTrue($booking->hold_expires_at->greaterThan(now()->addMinutes(2)));
        $this->assertTrue($booking->hold_expires_at->lessThan(now()->addMinutes(3)));

        $this
            ->actingAs(User::query()->findOrFail($branch->provider_id), 'provider')
            ->get(route('provider.bookings.index'))
            ->assertOk()
            ->assertDontSee($booking->booking_code);

        $availabilityResponse = $this
            ->actingAs($otherCustomer, 'sanctum')
            ->postJson(route('api.customer.booking.check-availability'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
            ])
            ->assertOk();

        $this->assertNotContains('13:00', collect($availabilityResponse->json('data.available_slots'))->pluck('time')->all());

        $finalizeResponse = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.finalize', $booking), [
                'payment_type' => 'full_payment',
                'payment_channel' => 'qris',
            ]);

        $finalizeResponse
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.hold_expires_at', null)
            ->assertJsonPath('data.payment.payment_channel', 'qris');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending_payment',
            'hold_expires_at' => null,
        ]);
    }

    public function test_customer_can_extend_active_hold_while_reviewing_booking(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        $otherCustomer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ])
            ->assertCreated();

        $booking = Booking::query()->firstOrFail();
        $oldHoldExpiresAt = $booking->hold_expires_at->copy();

        $this->travel(100)->seconds();

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.hold.extend', $booking))
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_hold')
            ->assertJsonPath('data.hold_expires_at', fn ($value) => is_string($value) && $value !== '');

        $booking->refresh();

        $this->assertTrue($booking->hold_expires_at->greaterThan($oldHoldExpiresAt));
        $this->assertTrue($booking->hold_expires_at->greaterThan(now()->addMinutes(2)));

        $availabilityResponse = $this
            ->actingAs($otherCustomer, 'sanctum')
            ->postJson(route('api.customer.booking.check-availability'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
            ])
            ->assertOk();

        $this->assertNotContains('13:00', collect($availabilityResponse->json('data.available_slots'))->pluck('time')->all());
    }

    public function test_expired_booking_hold_releases_slot_for_other_customers(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        $otherCustomer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ])
            ->assertCreated();

        $heldBooking = Booking::query()->firstOrFail();

        $this
            ->actingAs($otherCustomer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'pay_at_salon',
            ])
            ->assertUnprocessable();

        $this->travel(6)->minutes();

        $this
            ->postJson(route('api.customer.booking.check-availability'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
            ])
            ->assertOk();

        $this->assertDatabaseMissing('bookings', [
            'id' => $heldBooking->id,
        ]);
        $this->assertDatabaseMissing('payments', ['booking_id' => $heldBooking->id]);

        $this
            ->actingAs($otherCustomer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'pay_at_salon',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_new_booking_hold_replaces_customer_previous_active_hold(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $firstHold = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $secondHold = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_hold')
            ->json('data.id');

        $this->assertDatabaseMissing('bookings', ['id' => $firstHold]);
        $this->assertDatabaseHas('bookings', [
            'id' => $secondHold,
            'status' => 'pending_hold',
        ]);
        $this->assertSame(1, Booking::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'pending_hold')
            ->whereNotNull('hold_expires_at')
            ->count());
    }

    public function test_customer_availability_keeps_own_active_hold_available_but_hides_it_from_others(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        $otherCustomer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $holdId = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $ownAvailabilityWithoutHoldId = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.booking.check-availability'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
            ]);

        $ownAvailabilityWithHoldId = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.booking.check-availability'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'held_booking_id' => $holdId,
            ]);

        $otherAvailability = $this
            ->actingAs($otherCustomer, 'sanctum')
            ->postJson(route('api.customer.booking.check-availability'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
            ]);

        $ownAvailabilityWithoutHoldId->assertOk();
        $ownAvailabilityWithHoldId->assertOk();
        $otherAvailability->assertOk();

        $this->assertContains('13:00', collect($ownAvailabilityWithoutHoldId->json('data.available_slots'))->pluck('time')->all());
        $this->assertContains('13:00', collect($ownAvailabilityWithHoldId->json('data.available_slots'))->pluck('time')->all());
        $this->assertNotContains('13:00', collect($otherAvailability->json('data.available_slots'))->pluck('time')->all());
    }

    public function test_customer_booking_hold_is_idempotent_for_same_request_key(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();
        $payload = [
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'booking_type' => 'scheduled',
            'staff_id' => $staff->id,
            'booking_date' => $bookingDate,
            'start_time' => '13:00',
            'payment_type' => 'pay_at_salon',
            'hold_only' => true,
            'idempotency_key' => 'hold-test-'.$customer->id,
        ];

        $firstId = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_hold')
            ->json('data.id');

        $secondId = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_hold')
            ->json('data.id');

        $this->assertSame($firstId, $secondId);
        $this->assertSame(1, Booking::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'pending_hold')
            ->whereNotNull('hold_expires_at')
            ->count());
    }

    public function test_customer_can_release_active_hold_immediately_when_changing_selection(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        $otherCustomer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $holdId = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '09:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_hold')
            ->json('data.id');

        $this
            ->actingAs($customer, 'sanctum')
            ->patchJson(route('api.customer.bookings.cancel', $holdId))
            ->assertOk()
            ->assertJsonPath('data.id', $holdId)
            ->assertJsonPath('data.released', true);

        $this->assertDatabaseMissing('bookings', ['id' => $holdId]);
        $this->assertDatabaseMissing('payments', ['booking_id' => $holdId]);

        $this
            ->actingAs($otherCustomer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '09:00',
                'payment_type' => 'pay_at_salon',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_customer_can_reuse_same_hold_key_after_releasing_hold(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();
        $payload = [
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'booking_type' => 'scheduled',
            'staff_id' => $staff->id,
            'booking_date' => $bookingDate,
            'start_time' => '10:00',
            'payment_type' => 'pay_at_salon',
            'hold_only' => true,
            'idempotency_key' => 'same-selection-'.$customer->id,
        ];

        $firstHoldId = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_hold')
            ->json('data.id');

        $this
            ->actingAs($customer, 'sanctum')
            ->patchJson(route('api.customer.bookings.cancel', $firstHoldId))
            ->assertOk()
            ->assertJsonPath('data.id', $firstHoldId)
            ->assertJsonPath('data.released', true);

        $this->assertDatabaseMissing('bookings', ['id' => $firstHoldId]);

        $secondHoldId = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_hold')
            ->json('data.id');

        $this->assertNotSame($firstHoldId, $secondHoldId);
    }

    public function test_customer_can_replace_own_hold_on_same_start_time_with_different_service(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();
        $secondService = Service::create([
            'provider_id' => $branch->provider_id,
            'title' => 'Cuci Blow Express '.$branch->id,
            'slug' => 'cuci-blow-express-'.$branch->id,
            'category' => $service->category,
            'category_id' => $service->category_id,
            'code' => 'BLOW'.$branch->id,
            'description' => 'Treatment express',
            'includes' => 'Cuci dan blow',
            'price_type' => 'fixed',
            'price' => 55000,
            'minimum_duration' => 35,
            'estimated_duration' => 35,
            'maximum_duration' => 35,
            'is_queue_enabled' => true,
            'is_scheduled_enabled' => true,
            'requires_dp' => false,
            'dp_amount' => null,
            'payment_policy' => 'Bayar setelah layanan',
            'slots' => [],
            'additional_services' => [],
            'holidays' => [],
            'branch_ids' => [$branch->id],
            'status' => 'active',
            'verify_status' => 'verified',
        ]);
        $staff->skills()->attach($secondService->id);

        $firstHoldId = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '09:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_hold')
            ->json('data.id');

        $secondHoldId = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$secondService->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '09:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_hold')
            ->assertJsonPath('data.services.0.id', $secondService->id)
            ->json('data.id');

        $this->assertNotSame($firstHoldId, $secondHoldId);
        $this->assertDatabaseMissing('bookings', ['id' => $firstHoldId]);
        $this->assertDatabaseHas('bookings', [
            'id' => $secondHoldId,
            'status' => 'pending_hold',
            'start_time' => '09:00',
        ]);
    }

    public function test_booking_resolves_duplicate_service_title_to_selected_branch_service(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();
        $otherBranch = ProviderBranch::create([
            'provider_id' => $branch->provider_id,
            'branch_name' => 'Glow Other Branch '.$branch->provider_id,
            'email' => 'other-'.$branch->provider_id.'@example.test',
            'phone_code' => '+62',
            'phone_number' => '8123000000',
            'address' => 'Jl. Melati '.$branch->provider_id,
            'country_id' => 'Indonesia',
            'state_id' => 'DKI Jakarta',
            'city_id' => 'Jakarta',
            'zip_code' => '12345',
            'working_start_hour' => '09:00',
            'working_end_hour' => '21:00',
            'working_days' => [strtolower(now()->addDays(3)->englishDayOfWeek)],
            'holidays' => [],
            'status' => 'active',
        ]);
        $duplicateService = Service::create([
            'provider_id' => $branch->provider_id,
            'title' => $service->title,
            'slug' => $service->slug.'-other',
            'category' => $service->category,
            'category_id' => $service->category_id,
            'code' => $service->code.'OTHER',
            'description' => $service->description,
            'includes' => $service->includes,
            'price_type' => 'fixed',
            'price' => $service->price,
            'minimum_duration' => $service->minimum_duration,
            'estimated_duration' => $service->estimated_duration,
            'maximum_duration' => $service->maximum_duration,
            'is_queue_enabled' => true,
            'is_scheduled_enabled' => true,
            'requires_dp' => false,
            'dp_amount' => null,
            'payment_policy' => 'Bayar setelah layanan',
            'slots' => [],
            'additional_services' => [],
            'holidays' => [],
            'branch_ids' => [$otherBranch->id],
            'status' => 'active',
            'verify_status' => 'verified',
        ]);

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$duplicateService->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '09:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.services.0.id', $service->id)
            ->assertJsonPath('data.status', 'pending_hold');
    }

    public function test_finalize_hold_is_safe_to_retry_after_success(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $bookingId = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $payload = [
            'payment_type' => 'full_payment',
            'payment_channel' => 'qris',
            'idempotency_key' => 'finalize-test-'.$bookingId,
        ];

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.finalize', $bookingId), $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_payment');

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.finalize', $bookingId), $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_payment');

        $this->assertSame(1, Payment::query()->where('booking_id', $bookingId)->count());
    }

    public function test_customer_hold_blocks_same_time_for_other_staff_in_same_branch(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        $otherCustomer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();
        $secondStaff = ProviderStaff::create([
            'provider_id' => $branch->provider_id,
            'first_name' => 'Maya',
            'last_name' => 'Putri',
            'email' => 'maya-'.$branch->provider_id.'@example.test',
            'gender' => 'female',
            'branch_id' => $branch->id,
            'role' => 'Stylist',
            'current_status' => 'available',
            'status' => 'active',
        ]);
        $secondStaff->skills()->attach($service->id);

        StaffSchedule::create([
            'staff_id' => $secondStaff->id,
            'day_of_week' => strtolower(now()->addDays(3)->englishDayOfWeek),
            'start_time' => '09:00',
            'end_time' => '21:00',
            'is_available' => true,
        ]);

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ])
            ->assertCreated();

        $otherAvailability = $this
            ->actingAs($otherCustomer, 'sanctum')
            ->postJson(route('api.customer.booking.check-availability'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $secondStaff->id,
                'booking_date' => $bookingDate,
            ]);

        $otherAvailability->assertOk();
        $this->assertNotContains('13:00', collect($otherAvailability->json('data.available_slots'))->pluck('time')->all());
    }

    public function test_customer_hold_expires_with_booking_timer_and_releases_slot_for_other_customers(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        $otherCustomer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '09:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
                'booking_hold_expires_at' => now()->addSeconds(30)->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_hold');

        $availabilityResponse = $this
            ->actingAs($otherCustomer, 'sanctum')
            ->postJson(route('api.customer.booking.check-availability'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
            ])
            ->assertOk();

        $this->assertNotContains('09:00', collect($availabilityResponse->json('data.available_slots'))->pluck('time')->all());

        $this->travel(31)->seconds();

        $this
            ->actingAs($otherCustomer, 'sanctum')
            ->postJson(route('api.customer.booking.check-availability'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
            ])
            ->assertOk()
            ->assertJsonFragment(['time' => '09:00']);
    }

    public function test_expired_payment_releases_pending_payment_booking_slot(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        $otherCustomer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'full_payment',
                'payment_channel' => 'qris',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment');

        $payment = Payment::query()->firstOrFail();
        app(MidtransService::class)->expirePayment($payment);

        $this->assertDatabaseHas('bookings', [
            'id' => $payment->booking_id,
            'status' => 'payment_expired',
        ]);
        $this->assertDatabaseHas('payments', [
            'booking_id' => $payment->booking_id,
            'status' => 'expired',
        ]);

        $this
            ->actingAs($otherCustomer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'pay_at_salon',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_availability_uses_staff_skills_and_excludes_booked_slots(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();
        $unskilledStaff = ProviderStaff::create([
            'provider_id' => $branch->provider_id,
            'first_name' => 'No',
            'last_name' => 'Skill',
            'email' => 'no-skill-'.$branch->id.'@example.test',
            'gender' => 'female',
            'branch_id' => $branch->id,
            'role' => 'Stylist',
            'current_status' => 'available',
            'status' => 'active',
        ]);
        StaffSchedule::create([
            'staff_id' => $unskilledStaff->id,
            'day_of_week' => strtolower(now()->addDays(3)->englishDayOfWeek),
            'start_time' => '09:00',
            'end_time' => '21:00',
            'is_available' => true,
        ]);

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'pay_at_salon',
            ])
            ->assertCreated();

        $response = $this->postJson(route('api.customer.booking.check-availability'), [
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'booking_type' => 'scheduled',
            'staff_id' => $staff->id,
            'booking_date' => $bookingDate,
        ]);

        $response->assertOk();

        $staffIds = collect($response->json('data.eligible_staff'))->pluck('id')->all();
        $slotTimes = collect($response->json('data.available_slots'))->pluck('time')->all();

        $this->assertSame([$staff->id], $staffIds);
        $this->assertNotContains($unskilledStaff->id, $staffIds);
        $this->assertNotContains('13:00', $slotTimes);
    }

    public function test_availability_slot_interval_follows_total_selected_service_duration(): void
    {
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $service->update([
            'minimum_duration' => 35,
            'estimated_duration' => 35,
            'maximum_duration' => 35,
        ]);

        $singleServiceResponse = $this->postJson(route('api.customer.booking.check-availability'), [
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'booking_type' => 'scheduled',
            'staff_id' => $staff->id,
            'booking_date' => $bookingDate,
        ]);

        $singleServiceResponse->assertOk();

        $singleServiceTimes = collect($singleServiceResponse->json('data.available_slots'))
            ->pluck('time')
            ->take(3)
            ->values()
            ->all();

        $this->assertSame(['09:00', '09:35', '10:10'], $singleServiceTimes);

        $secondService = Service::create([
            'provider_id' => $branch->provider_id,
            'title' => 'Express Mask '.$branch->id,
            'slug' => 'express-mask-'.$branch->id,
            'category' => $service->category,
            'category_id' => $service->category_id,
            'code' => 'MASK'.$branch->id,
            'description' => 'Treatment tambahan',
            'includes' => 'Masker rambut',
            'price_type' => 'fixed',
            'price' => 50000,
            'minimum_duration' => 20,
            'estimated_duration' => 20,
            'maximum_duration' => 20,
            'is_queue_enabled' => true,
            'is_scheduled_enabled' => true,
            'requires_dp' => false,
            'dp_amount' => null,
            'payment_policy' => 'Bayar setelah layanan',
            'slots' => [],
            'additional_services' => [],
            'holidays' => [],
            'branch_ids' => [$branch->id],
            'status' => 'active',
            'verify_status' => 'verified',
        ]);
        $staff->skills()->attach($secondService->id);

        $multiServiceResponse = $this->postJson(route('api.customer.booking.check-availability'), [
            'branch_id' => $branch->id,
            'service_ids' => [$service->id, $secondService->id],
            'booking_type' => 'scheduled',
            'staff_id' => $staff->id,
            'booking_date' => $bookingDate,
        ]);

        $multiServiceResponse->assertOk();

        $multiServiceTimes = collect($multiServiceResponse->json('data.available_slots'))
            ->pluck('time')
            ->take(3)
            ->values()
            ->all();

        $this->assertSame(['09:00', '09:55', '10:50'], $multiServiceTimes);
        $this->assertSame(55, $multiServiceResponse->json('data.estimated_duration'));
    }

    public function test_customer_booking_start_time_must_follow_service_duration_slot_grid(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $service->update([
            'minimum_duration' => 35,
            'estimated_duration' => 35,
            'maximum_duration' => 35,
        ]);

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '09:30',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('staff_id');

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '09:35',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.start_time', '09:35:00')
            ->assertJsonPath('data.estimated_end_time', '10:10:00');
    }

    public function test_availability_time_slots_keep_opening_anchor_when_first_slot_is_unavailable(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        $otherCustomer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $service->update([
            'minimum_duration' => 35,
            'estimated_duration' => 35,
            'maximum_duration' => 35,
        ]);

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '09:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ])
            ->assertCreated();

        $response = $this
            ->actingAs($otherCustomer, 'sanctum')
            ->postJson(route('api.customer.booking.check-availability'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
            ]);

        $response->assertOk();

        $this->assertSame('09:00', $response->json('data.time_slots.0.time'));
        $this->assertFalse($response->json('data.time_slots.0.is_available'));
        $this->assertSame('09:35', $response->json('data.available_slots.0.time'));
    }

    public function test_active_hold_hides_same_time_for_branch_even_when_another_staff_is_free(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        $otherCustomer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $secondStaff = ProviderStaff::create([
            'provider_id' => $branch->provider_id,
            'first_name' => 'Free',
            'last_name' => 'Staff',
            'email' => 'free-staff-'.$branch->id.'@example.test',
            'gender' => 'female',
            'branch_id' => $branch->id,
            'role' => 'Stylist',
            'current_status' => 'available',
            'status' => 'active',
        ]);
        $secondStaff->skills()->attach($service->id);
        StaffSchedule::create([
            'staff_id' => $secondStaff->id,
            'day_of_week' => strtolower(now()->addDays(3)->englishDayOfWeek),
            'start_time' => '09:00',
            'end_time' => '21:00',
            'is_available' => true,
        ]);

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '09:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ])
            ->assertCreated();

        $response = $this
            ->actingAs($otherCustomer, 'sanctum')
            ->postJson(route('api.customer.booking.check-availability'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'booking_date' => $bookingDate,
            ]);

        $response->assertOk();

        $slotTimes = collect($response->json('data.available_slots'))->pluck('time')->all();

        $this->assertNotContains('09:00', $slotTimes);
    }

    public function test_availability_without_date_returns_service_eligible_staff_without_slots(): void
    {
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();

        $response = $this->postJson(route('api.customer.booking.check-availability'), [
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'booking_type' => 'scheduled',
        ]);

        $response->assertOk();

        $staffIds = collect($response->json('data.eligible_staff'))->pluck('id')->all();

        $this->assertContains($staff->id, $staffIds);
        $this->assertSame([], $response->json('data.available_slots'));
    }

    public function test_customer_graphql_booking_page_and_availability_queries(): void
    {
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $pageResponse = $this->postJson(route('api.customer.graphql'), [
            'operationName' => 'CustomerBookingPage',
            'query' => 'query CustomerBookingPage { customerBookingPage { branch booking_preview } }',
            'variables' => [
                'branchId' => $branch->id,
                'serviceIds' => [$service->id],
                'bookingDate' => $bookingDate,
                'staffId' => $staff->id,
            ],
        ]);

        $pageResponse
            ->assertOk()
            ->assertJsonPath('data.customerBookingPage.branch.id', $branch->id)
            ->assertJsonPath('data.customerBookingPage.booking_preview.eligible_staff.0.id', $staff->id);

        $availabilityResponse = $this->postJson(route('api.customer.graphql'), [
            'operationName' => 'CustomerBookingAvailability',
            'query' => 'query CustomerBookingAvailability { customerBookingAvailability { available_slots eligible_staff } }',
            'variables' => [
                'branchId' => $branch->id,
                'serviceIds' => [$service->id],
                'bookingDate' => $bookingDate,
                'staffId' => $staff->id,
            ],
        ]);

        $availabilityResponse
            ->assertOk()
            ->assertJsonPath('data.customerBookingAvailability.eligible_staff.0.id', $staff->id);

        $this->assertContains(
            '13:00',
            collect($availabilityResponse->json('data.customerBookingAvailability.available_slots'))->pluck('time')->all()
        );
    }

    public function test_customer_team_endpoint_only_returns_staff_owned_by_the_requested_branch(): void
    {
        [$branch, , $staff] = $this->bookableBranchServiceAndStaff();
        $otherBranch = ProviderBranch::create([
            'provider_id' => $branch->provider_id,
            'branch_name' => 'Cabang Lain '.$branch->provider_id,
            'email' => 'cabang-lain-'.$branch->provider_id.'@example.test',
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => 'Jl. Melati',
            'country_id' => 'Indonesia',
            'state_id' => 'DKI Jakarta',
            'city_id' => 'Jakarta',
            'zip_code' => '12345',
            'working_start_hour' => '09:00',
            'working_end_hour' => '21:00',
            'working_days' => ['monday'],
            'holidays' => [],
            'status' => 'active',
        ]);
        $otherStaff = ProviderStaff::create([
            'provider_id' => $branch->provider_id,
            'first_name' => 'Staff',
            'last_name' => 'Cabang Lain',
            'email' => 'staff-cabang-lain-'.$branch->id.'@example.test',
            'gender' => 'female',
            'branch_id' => $otherBranch->id,
            'role' => 'Therapist',
            'current_status' => 'available',
            'status' => 'active',
        ]);

        $response = $this->getJson(route('api.branches.staff', ['branch' => $branch]));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $staff->id);

        $staffIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($staff->id, $staffIds);
        $this->assertNotContains($otherStaff->id, $staffIds);
    }

    public function test_customer_branch_search_filters_by_price_and_sort_parameter(): void
    {
        [$affordableBranch] = $this->bookableBranchServiceAndStaff();
        [$premiumBranch, $premiumService] = $this->bookableBranchServiceAndStaff();
        $premiumService->update(['price' => 250000]);

        foreach ([$affordableBranch->provider_id, $premiumBranch->provider_id] as $providerId) {
            ProviderSubscription::create([
                'provider_id' => $providerId,
                'plan_name' => 'Catalog test plan',
                'price' => 0,
                'currency' => 'IDR',
                'duration_days' => 30,
                'max_branches' => 5,
                'subscription_status' => 'active',
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(30),
            ]);
        }

        $response = $this->getJson(route('api.branches.index', [
            'min_price' => 200000,
            'max_price' => 300000,
            'sort' => 'price_desc',
            'per_page' => 100,
        ]));

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($premiumBranch->id, $response->json('data.0.id'));
        $this->assertNotSame($affordableBranch->id, $response->json('data.0.id'));
        $this->assertSame(250000.0, (float) $response->json('data.0.min_price'));
    }

    public function test_customer_team_rating_is_calculated_from_customer_reviews(): void
    {
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $customer = User::factory()->create(['role' => 'customer']);
        $secondCustomer = User::factory()->create(['role' => 'customer']);
        $staff->update(['rating' => 1.0]);

        foreach ([[$customer, 5], [$secondCustomer, 3]] as [$reviewer, $rating]) {
            $booking = Booking::create([
                'booking_code' => 'REV-'.uniqid(),
                'booking_date' => now()->toDateString(),
                'provider_id' => $branch->provider_id,
                'customer_id' => $reviewer->id,
                'branch_id' => $branch->id,
                'staff_id' => $staff->id,
                'total_price' => 100000,
                'total_duration' => 60,
                'status' => 'completed',
            ]);
            $booking->services()->attach($service->id, [
                'price' => 100000,
                'estimated_duration' => 60,
            ]);

            StaffReview::create([
                'booking_id' => $booking->id,
                'staff_id' => $staff->id,
                'rating' => $rating,
            ]);
        }

        $response = $this->getJson(route('api.branches.staff', ['branch' => $branch]));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.review_count', 2);
        $this->assertEquals(4.0, $response->json('data.0.rating'));
    }

    public function test_customer_graphql_availability_keeps_own_active_hold_available_without_hold_id(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);
        $otherCustomer = User::factory()->create([
            'role' => 'customer',
        ]);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();
        $bookingDate = now()->addDays(3)->toDateString();

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => $bookingDate,
                'start_time' => '13:00',
                'payment_type' => 'pay_at_salon',
                'hold_only' => true,
            ])
            ->assertCreated();

        $payload = [
            'operationName' => 'CustomerBookingAvailability',
            'query' => 'query CustomerBookingAvailability { customerBookingAvailability { available_slots eligible_staff } }',
            'variables' => [
                'branchId' => $branch->id,
                'serviceIds' => [$service->id],
                'bookingDate' => $bookingDate,
                'staffId' => $staff->id,
            ],
        ];

        $ownAvailability = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.graphql'), $payload);
        $otherAvailability = $this
            ->actingAs($otherCustomer, 'sanctum')
            ->postJson(route('api.customer.graphql'), $payload);

        $ownAvailability->assertOk();
        $otherAvailability->assertOk();

        $this->assertContains(
            '13:00',
            collect($ownAvailability->json('data.customerBookingAvailability.available_slots'))->pluck('time')->all()
        );
        $this->assertNotContains(
            '13:00',
            collect($otherAvailability->json('data.customerBookingAvailability.available_slots'))->pluck('time')->all()
        );
    }

    private function bookableBranchServiceAndStaff(): array
    {
        $provider = User::factory()->create([
            'role' => 'provider',
        ]);

        ProviderProfile::create([
            'user_id' => $provider->id,
            'phone_number' => '08123456789',
            'status' => 'active',
            'document_status' => 'verified',
        ]);

        $branch = ProviderBranch::create([
            'provider_id' => $provider->id,
            'branch_name' => 'Glow Salon '.$provider->id,
            'email' => 'glow-'.$provider->id.'@example.test',
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => 'Jl. Mawar '.$provider->id,
            'country_id' => 'Indonesia',
            'state_id' => 'DKI Jakarta',
            'city_id' => 'Jakarta',
            'zip_code' => '12345',
            'working_start_hour' => '09:00',
            'working_end_hour' => '21:00',
            'working_days' => [strtolower(now()->addDays(3)->englishDayOfWeek)],
            'holidays' => [],
            'status' => 'active',
        ]);

        $categoryRoot = ServiceCategory::create([
            'name' => 'Hair',
            'slug' => 'hair-'.$provider->id,
            'description' => 'Layanan rambut',
            'status' => 'active',
            'is_featured' => true,
        ]);
        $category = ServiceCategory::create([
            'parent_id' => $categoryRoot->id,
            'name' => 'Hair Spa',
            'slug' => 'hair-spa-category-'.$provider->id,
            'description' => 'Treatment rambut',
            'status' => 'active',
        ]);

        $service = Service::create([
            'provider_id' => $provider->id,
            'title' => 'Hair Spa '.$provider->id,
            'slug' => 'hair-spa-'.$provider->id,
            'category' => $category->name,
            'category_id' => $category->id,
            'code' => 'HAIRSPA'.$provider->id,
            'description' => 'Treatment rambut',
            'includes' => 'Konsultasi dan treatment',
            'price_type' => 'fixed',
            'price' => 100000,
            'minimum_duration' => 50,
            'estimated_duration' => 60,
            'maximum_duration' => 80,
            'is_queue_enabled' => true,
            'is_scheduled_enabled' => true,
            'requires_dp' => false,
            'dp_amount' => null,
            'payment_policy' => 'Bayar setelah layanan',
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
            'last_name' => 'Wijaya',
            'email' => 'sari-'.$provider->id.'@example.test',
            'gender' => 'female',
            'branch_id' => $branch->id,
            'role' => 'Senior Stylist',
            'current_status' => 'available',
            'status' => 'active',
        ]);
        $staff->skills()->attach($service->id);

        StaffSchedule::create([
            'staff_id' => $staff->id,
            'day_of_week' => strtolower(now()->addDays(3)->englishDayOfWeek),
            'start_time' => '09:00',
            'end_time' => '21:00',
            'is_available' => true,
        ]);

        return [$branch, $service, $staff];
    }
}
