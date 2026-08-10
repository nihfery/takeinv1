<?php

namespace App\Modules\Customer\Presentation\Api\Customer;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'customer');

        $activities = CustomerActivity::query()
            ->where('customer_id', $request->user()->id)
            ->whereHas('booking')
            ->with([
                'booking.branch',
                'booking.staff',
                'booking.services.serviceCategory',
                'booking.payment',
                'booking.branchReview',
                'booking.staffReview',
                'booking.participants.staff',
                'booking.participants.services',
            ])
            ->latest('id')
            ->get()
            ->map(fn (CustomerActivity $activity) => $this->activityPayload($activity))
            ->values();

        return response()->json([
            'data' => $activities,
            'count' => $activities->count(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'customer');

        $count = CustomerActivity::query()
            ->where('customer_id', $request->user()->id)
            ->whereHas('booking')
            ->count();

        return response()->json([
            'has_activity' => $count > 0,
            'count' => $count,
        ]);
    }

    private function activityPayload(CustomerActivity $activity): array
    {
        $booking = $activity->booking;

        return [
            'id' => $activity->id,
            'booking_id' => $booking->id,
            'code' => $booking->booking_code,
            'status' => $booking->status,
            'payment_status' => $booking->payment?->status,
            'can_review' => in_array($booking->status, ['completed', 'order_completed'], true)
                && ! (bool) $booking->branchReview,
            'reviewed' => (bool) $booking->branchReview,
            'salon_id' => $booking->branch_id,
            'salon_name' => $booking->branch?->branch_name,
            'salon_image' => $booking->branch?->image_url,
            'salon_address' => $booking->branch?->address,
            'date' => $booking->booking_date?->format('Y-m-d'),
            'time' => $booking->start_time ? substr((string) $booking->start_time, 0, 5) : null,
            'duration' => (int) $booking->total_duration,
            'total' => (float) $booking->total_price,
            'participant_count' => (int) $booking->participant_count,
            'staff' => $booking->staff ? [
                'id' => $booking->staff->id,
                'name' => $booking->staff->full_name,
            ] : null,
            'services' => $booking->services->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->title,
                'duration' => (int) ($service->pivot?->estimated_duration ?? 0),
                'price' => (float) ($service->pivot?->price ?? 0),
            ])->values(),
            'participants' => $booking->participants->map(fn ($participant) => [
                'position' => (int) $participant->position,
                'name' => $participant->name,
                'gender' => $participant->gender,
                'description' => $participant->description,
                'date' => $participant->booking_date?->format('Y-m-d'),
                'time' => $participant->start_time ? substr((string) $participant->start_time, 0, 5) : null,
                'staff_name' => $participant->staff?->full_name,
            ])->values(),
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }
}
