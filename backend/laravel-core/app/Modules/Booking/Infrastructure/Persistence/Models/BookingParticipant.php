<?php

namespace App\Modules\Booking\Infrastructure\Persistence\Models;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'position',
        'is_primary',
        'name',
        'phone',
        'email',
        'gender',
        'age_group',
        'description',
        'provider_staff_id',
        'booking_date',
        'start_time',
        'estimated_end_time',
        'total_duration',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_primary' => 'boolean',
            'booking_date' => 'date',
            'total_duration' => 'integer',
            'total_price' => 'decimal:2',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function staff()
    {
        return $this->belongsTo(ProviderStaff::class, 'provider_staff_id');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'booking_participant_services')
            ->withPivot(['price', 'estimated_duration'])
            ->withTimestamps();
    }
}
