<?php

namespace App\Modules\Booking\Presentation\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Audit\Application\Actions\RecordAuditEvent;
use App\Modules\Booking\Application\Services\BookingFlowService;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends ApiController
{
    public function __construct(
        private readonly BookingFlowService $bookingFlow,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $bookings = Booking::query()
            ->with($this->bookingFlow->bookingRelations())
            ->when($request->query('status') && $request->query('status') !== 'all', fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->query('search'), function ($query, $search) {
                $query->where('booking_code', 'like', "%{$search}%")
                    ->orWhereHas('provider', fn ($providerQuery) => $providerQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('services', fn ($serviceQuery) => $serviceQuery->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('branch', fn ($branchQuery) => $branchQuery->where('branch_name', 'like', "%{$search}%"));
            })
            ->orderByDesc('booking_date')
            ->paginate($this->perPage($request));

        return response()->json($bookings);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        return response()->json(['data' => $booking->load($this->bookingFlow->bookingRelations())]);
    }

    public function updateStatus(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                'open',
                'pending',
                'pending_hold',
                'expired_hold',
                'payment_expired',
                'inprogress',
                'completed',
                'order_completed',
                'refund_completed',
                'provider_cancelled',
                'customer_cancelled',
                'rescheduled',
                'pending_payment',
                'confirmed',
                'waiting',
                'checked_in',
                'in_progress',
                'cancelled',
                'no_show',
            ])],
        ]);

        $before = ['status' => $booking->status];
        $booking->update($validated);
        $booking->refresh();

        $this->recordAuditEvent->execute(
            action: 'admin.booking.status-updated',
            resourceType: Booking::class,
            resourceId: $booking->id,
            before: $before,
            after: ['status' => $booking->status],
            actor: $request->user(),
            providerId: $booking->provider_id,
            branchId: $booking->branch_id,
        );

        return response()->json(['message' => 'Booking status has been updated.', 'data' => $booking]);
    }
}
