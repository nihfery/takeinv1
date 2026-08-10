<?php

namespace App\Modules\Review\Presentation\Api\Customer;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Review\Infrastructure\Persistence\Models\BranchReview;
use App\Modules\Review\Infrastructure\Persistence\Models\StaffReview;
use App\Modules\Booking\Application\Services\BookingFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviewController extends ApiController
{
    public function store(Request $request, string $bookingCode): JsonResponse
    {
        $this->authorizeRole($request, 'customer');

        $booking = Booking::query()
            ->where('booking_code', $bookingCode)
            ->where('customer_id', $request->user()->id)
            ->with(['participants.staff', 'branchReview', 'staffReview'])
            ->firstOrFail();

        abort_unless(
            in_array($booking->status, ['completed', 'order_completed'], true),
            422,
            'Reviews can only be submitted after the service is completed.'
        );

        abort_if($booking->branchReview, 422, 'A venue review has already been submitted for this booking.');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'staff_id' => ['nullable', 'integer', 'required_with:staff_rating,staff_comment'],
            'staff_rating' => ['nullable', 'integer', 'between:1,5', 'required_with:staff_id,staff_comment'],
            'staff_comment' => ['nullable', 'string', 'max:1000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $eligibleStaffIds = collect([$booking->staff_id])
            ->merge($booking->participants->pluck('provider_staff_id'))
            ->filter()
            ->map(fn ($staffId) => (int) $staffId)
            ->unique()
            ->values();

        $staffId = isset($validated['staff_id']) ? (int) $validated['staff_id'] : null;

        if ($staffId !== null) {
            abort_unless(
                $eligibleStaffIds->contains($staffId),
                422,
                'Profesional yang dipilih bukan bagian dari booking ini.'
            );

            abort_if(
                StaffReview::query()
                    ->where('booking_id', $booking->id)
                    ->where('staff_id', $staffId)
                    ->exists(),
                422,
                'A review for this professional has already been submitted.'
            );
        }

        $storedImages = [];

        try {
            foreach ($request->file('images', []) as $image) {
                $storedImages[] = $image->store("review-images/{$booking->id}", 'public');
            }

            DB::transaction(function () use ($booking, $validated, $staffId, $storedImages): void {
                BranchReview::create([
                    'booking_id' => $booking->id,
                    'rating' => $validated['rating'],
                    'comment' => $validated['comment'] ?? null,
                    'images' => $storedImages,
                ]);

                if ($staffId !== null) {
                    StaffReview::create([
                        'booking_id' => $booking->id,
                        'staff_id' => $staffId,
                        'rating' => $validated['staff_rating'],
                        'comment' => $validated['staff_comment'] ?? ($validated['comment'] ?? null),
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            if ($storedImages !== []) {
                Storage::disk('public')->delete($storedImages);
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Thank you, your review has been submitted.',
            'data' => $booking->fresh()->load(app(BookingFlowService::class)->bookingRelations()),
        ], 201);
    }
}
