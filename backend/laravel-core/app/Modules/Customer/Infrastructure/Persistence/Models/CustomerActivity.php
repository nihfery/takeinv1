<?php

namespace App\Modules\Customer\Infrastructure\Persistence\Models;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'booking_id',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public static function recordBooking(Booking $booking): void
    {
        self::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'customer_id' => $booking->customer_id,
            ]
        );
    }
}
