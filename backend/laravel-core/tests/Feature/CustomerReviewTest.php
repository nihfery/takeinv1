<?php

namespace Tests\Feature;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Review\Infrastructure\Persistence\Models\BranchReview;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_review_completed_booking_once(): void
    {
        [$customer, $booking, $staff] = $this->bookingFixture('completed');
        Storage::fake('public');

        $this
            ->actingAs($customer, 'sanctum')
            ->post(route('api.customer.bookings.review.store', $booking->booking_code), [
                'rating' => 5,
                'comment' => 'Tempat bersih dan pelayanannya nyaman.',
                'staff_id' => $staff->id,
                'staff_rating' => 4,
                'staff_comment' => 'Profesional dan ramah.',
                'images' => [UploadedFile::fake()->create('hasil-layanan.jpg', 100, 'image/jpeg')],
            ], [
                'Accept' => 'application/json',
            ])
            ->assertCreated()
            ->assertJsonPath('data.branch_review.rating', 5)
            ->assertJsonPath('data.staff_review.rating', 4);

        $review = BranchReview::query()->where('booking_id', $booking->id)->firstOrFail();
        $this->assertCount(1, $review->images);
        Storage::disk('public')->assertExists($review->images[0]);

        $this->assertDatabaseHas('branch_reviews', [
            'booking_id' => $booking->id,
            'rating' => 5,
            'comment' => 'Tempat bersih dan pelayanannya nyaman.',
        ]);
        $this->assertDatabaseHas('staff_reviews', [
            'booking_id' => $booking->id,
            'staff_id' => $staff->id,
            'rating' => 4,
            'comment' => 'Profesional dan ramah.',
        ]);

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.review.store', $booking->booking_code), [
                'rating' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A venue review has already been submitted for this booking.');
    }

    public function test_customer_cannot_review_booking_before_it_is_completed(): void
    {
        [$customer, $booking] = $this->bookingFixture('confirmed');

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.review.store', $booking->booking_code), [
                'rating' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Reviews can only be submitted after the service is completed.');

        $this->assertDatabaseMissing('branch_reviews', [
            'booking_id' => $booking->id,
        ]);
    }

    private function bookingFixture(string $status): array
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $provider = User::factory()->create(['role' => 'provider']);
        $branch = ProviderBranch::create([
            'provider_id' => $provider->id,
            'branch_name' => 'Review Salon',
            'email' => 'review-salon@example.test',
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => 'Jl. Review No. 1',
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
        $staff = ProviderStaff::create([
            'provider_id' => $provider->id,
            'branch_id' => $branch->id,
            'first_name' => 'Fajar',
            'last_name' => 'Hidayat',
            'email' => 'fajar-review@example.test',
            'gender' => 'male',
            'role' => 'Stylist',
            'current_status' => 'available',
            'status' => 'active',
        ]);
        $booking = Booking::create([
            'booking_code' => 'REVIEW-' . fake()->unique()->numerify('######'),
            'booking_date' => now()->subDay()->toDateString(),
            'start_time' => '10:00',
            'estimated_end_time' => '11:00',
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'booking_type' => 'scheduled',
            'total_price' => 100000,
            'total_duration' => 60,
            'participant_count' => 1,
            'status' => $status,
        ]);

        return [$customer, $booking, $staff];
    }
}
