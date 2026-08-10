<?php

namespace App\Modules\Customer\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomerProfile extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (self $profile): void {
            if ($profile->customer_id) {
                return;
            }

            $profile->forceFill([
                'customer_id' => self::newCustomerId(),
            ])->saveQuietly();
        });
    }

    private static function newCustomerId(): string
    {
        do {
            $customerId = Str::upper(Str::random(10));
        } while (self::query()->where('customer_id', $customerId)->exists());

        return $customerId;
    }

    protected $fillable = [
        'customer_id',
        'user_id',
        'phone_number',
        'gender',
        'date_of_birth',
        'religion',
        'allergies',
        'avatar',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country',
        'status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
