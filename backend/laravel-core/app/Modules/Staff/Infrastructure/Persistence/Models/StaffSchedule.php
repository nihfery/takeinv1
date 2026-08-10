<?php

namespace App\Modules\Staff\Infrastructure\Persistence\Models;

use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
        ];
    }

    public function staff()
    {
        return $this->belongsTo(ProviderStaff::class, 'staff_id');
    }
}
