<?php

namespace App\Modules\Review\Infrastructure\Persistence\Models;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'rating',
        'comment',
        'images',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'images' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

}
