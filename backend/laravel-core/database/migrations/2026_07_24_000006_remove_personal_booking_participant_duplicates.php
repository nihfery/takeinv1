<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('booking_participants')
            ->whereIn('booking_id', function ($query) {
                $query
                    ->select('id')
                    ->from('bookings')
                    ->where('participant_count', '<=', 1);
            })
            ->delete();
    }

    public function down(): void
    {
        DB::table('bookings')
            ->leftJoin('users', 'users.id', '=', 'bookings.customer_id')
            ->select([
                'bookings.id',
                'bookings.customer_name',
                'bookings.customer_phone',
                'users.name as user_name',
                'users.email as user_email',
            ])
            ->where('bookings.participant_count', '<=', 1)
            ->whereNotExists(function ($query) {
                $query
                    ->selectRaw('1')
                    ->from('booking_participants')
                    ->whereColumn('booking_participants.booking_id', 'bookings.id');
            })
            ->orderBy('bookings.id')
            ->chunkById(100, function ($bookings) {
                $now = now();

                DB::table('booking_participants')->insert(
                    $bookings->map(fn ($booking) => [
                        'booking_id' => $booking->id,
                        'position' => 1,
                        'is_primary' => true,
                        'name' => $booking->customer_name ?: ($booking->user_name ?: 'Customer'),
                        'phone' => $booking->customer_phone,
                        'email' => $booking->user_email,
                        'provider_staff_id' => null,
                        'booking_date' => null,
                        'start_time' => null,
                        'estimated_end_time' => null,
                        'total_duration' => 0,
                        'total_price' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            }, 'bookings.id', 'id');
    }
};
