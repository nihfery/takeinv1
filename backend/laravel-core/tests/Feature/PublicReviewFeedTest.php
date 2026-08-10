<?php

namespace Tests\Feature;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Review\Infrastructure\Persistence\Models\BranchReview;
use App\Modules\Review\Infrastructure\Persistence\Models\StaffReview;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicReviewFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_review_feed_returns_latest_branch_reviews_with_safe_branch_context_and_pagination(): void
    {
        [$provider, $branch] = $this->publicBranch('Homepage Salon');

        foreach (range(1, 11) as $index) {
            $this->createBranchReview(
                $branch,
                "Public review {$index}",
                Carbon::parse('2026-01-01 09:00:00')->addMinutes($index),
                "Customer {$index}",
                $index === 11 ? ['reviews/latest.jpg', 'https://cdn.example.test/review.jpg'] : [],
            );
        }

        $staff = ProviderStaff::create([
            'provider_id' => $provider->id,
            'branch_id' => $branch->id,
            'first_name' => 'Public',
            'last_name' => 'Stylist',
            'email' => 'public-stylist@example.test',
            'gender' => 'female',
            'role' => 'Stylist',
            'current_status' => 'available',
            'status' => 'active',
        ]);
        $staffOnlyBooking = $this->createBooking($branch, 'Staff Review Customer', $staff->id);
        StaffReview::create([
            'booking_id' => $staffOnlyBooking->id,
            'staff_id' => $staff->id,
            'rating' => 5,
            'comment' => 'Staff-only review',
        ]);

        $response = $this->getJson(route('api.reviews.index'));

        $response
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 11)
            ->assertJsonPath('data.0.comment', 'Public review 11')
            ->assertJsonPath('data.0.customer_name', 'Customer 11')
            ->assertJsonPath('data.0.images.0', asset('storage/reviews/latest.jpg'))
            ->assertJsonPath('data.0.images.1', 'https://cdn.example.test/review.jpg')
            ->assertJsonPath('data.0.branch.id', $branch->id)
            ->assertJsonPath('data.0.branch.name', 'Homepage Salon')
            ->assertJsonPath('data.0.branch.city', 'Bandung')
            ->assertJsonPath('data.0.branch.state', 'Jawa Barat')
            ->assertJsonPath('data.0.branch.provider.id', $provider->id)
            ->assertJsonPath('data.0.branch.provider.name', $provider->name)
            ->assertJsonMissing(['comment' => 'Staff-only review']);

        $this->assertSame(
            ['id', 'rating', 'comment', 'images', 'customer_name', 'created_at', 'branch'],
            array_keys($response->json('data.0')),
        );
        $this->assertSame(
            ['id', 'name', 'city', 'state', 'provider'],
            array_keys($response->json('data.0.branch')),
        );

        $this->getJson(route('api.reviews.index', ['page' => 2]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 11)
            ->assertJsonPath('data.0.comment', 'Public review 1');

        $branchResponse = $this->getJson(route('api.branches.reviews', ['branch' => $branch]));
        $branchResponse
            ->assertOk()
            ->assertJsonPath('meta.total', 11);
        $this->assertArrayNotHasKey('branch', $branchResponse->json('data.0'));
    }

    public function test_public_review_feed_excludes_reviews_outside_the_public_bookable_scope(): void
    {
        [, $eligibleBranch] = $this->publicBranch('Eligible Salon');
        $this->createBranchReview($eligibleBranch, 'Eligible review', now()->subMinute());

        [, $inactiveBranch] = $this->publicBranch('Inactive Branch Salon', branchStatus: 'inactive');
        $this->createBranchReview($inactiveBranch, 'Inactive branch review', now());

        [, $inactiveProviderBranch] = $this->publicBranch('Inactive Provider Salon', profileStatus: 'inactive');
        $this->createBranchReview($inactiveProviderBranch, 'Inactive provider review', now());

        [, $unverifiedProviderBranch] = $this->publicBranch('Unverified Provider Salon', documentStatus: 'pending');
        $this->createBranchReview($unverifiedProviderBranch, 'Unverified provider review', now());

        [, $wrongRoleBranch] = $this->publicBranch('Wrong Role Salon', providerRole: 'customer');
        $this->createBranchReview($wrongRoleBranch, 'Wrong role review', now());

        [, $missingProfileBranch] = $this->publicBranch('Missing Profile Salon', createProfile: false);
        $this->createBranchReview($missingProfileBranch, 'Missing profile review', now());

        $this->getJson(route('api.reviews.index', ['per_page' => 25]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.comment', 'Eligible review');
    }

    /**
     * @return array{User, ProviderBranch}
     */
    private function publicBranch(
        string $name,
        string $branchStatus = 'active',
        string $profileStatus = 'active',
        string $documentStatus = 'verified',
        string $providerRole = 'provider',
        bool $createProfile = true,
    ): array {
        $provider = User::factory()->create([
            'name' => "{$name} Group",
            'role' => $providerRole,
        ]);

        if ($createProfile) {
            ProviderProfile::create([
                'user_id' => $provider->id,
                'status' => $profileStatus,
                'document_status' => $documentStatus,
            ]);
        }

        $branch = ProviderBranch::create([
            'provider_id' => $provider->id,
            'branch_name' => $name,
            'email' => str($name)->slug()->append('@example.test')->toString(),
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => 'Jl. Public Review No. 1',
            'country_id' => 'Indonesia',
            'state_id' => 'Jawa Barat',
            'city_id' => 'Bandung',
            'zip_code' => '40111',
            'working_start_hour' => '09:00',
            'working_end_hour' => '21:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'holidays' => [],
            'status' => $branchStatus,
        ]);

        return [$provider, $branch];
    }

    private function createBranchReview(
        ProviderBranch $branch,
        string $comment,
        Carbon $createdAt,
        string $customerName = 'Public Customer',
        array $images = [],
    ): BranchReview {
        $booking = $this->createBooking($branch, $customerName);
        $review = BranchReview::create([
            'booking_id' => $booking->id,
            'rating' => 5,
            'comment' => $comment,
            'images' => $images,
        ]);
        $review->created_at = $createdAt;
        $review->updated_at = $createdAt;
        $review->save();

        return $review;
    }

    private function createBooking(ProviderBranch $branch, string $customerName, ?int $staffId = null): Booking
    {
        $customer = User::factory()->create([
            'name' => $customerName,
            'role' => 'customer',
        ]);

        return Booking::create([
            'booking_code' => 'PUBLIC-REVIEW-'.fake()->unique()->numerify('########'),
            'booking_date' => now()->subDay()->toDateString(),
            'start_time' => '10:00',
            'estimated_end_time' => '11:00',
            'provider_id' => $branch->provider_id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'staff_id' => $staffId,
            'booking_type' => 'scheduled',
            'total_price' => 100000,
            'total_duration' => 60,
            'participant_count' => 1,
            'status' => 'completed',
        ]);
    }
}
