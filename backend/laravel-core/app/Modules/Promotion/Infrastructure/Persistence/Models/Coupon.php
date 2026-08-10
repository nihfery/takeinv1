<?php

namespace App\Modules\Promotion\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'product_type',
        'product_ids',
        'coupon_type',
        'coupon_value',
        'quantity',
        'used_count',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'product_ids' => 'array',
            'coupon_value' => 'decimal:2',
            'quantity' => 'integer',
            'used_count' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function isExpired(): bool
    {
        return $this->end_date->isPast() && ! $this->end_date->isToday();
    }

    public function isValid(): bool
    {
        return $this->status === 'active' && ! $this->isExpired();
    }
}