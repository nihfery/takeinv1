<?php

namespace App\Modules\Subscription\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Subscription\Infrastructure\Persistence\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProviderSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'plan_id',
        'plan_name',
        'price',
        'currency',
        'duration_days',
        'max_branches',
        'payment_status',
        'subscription_status',
        'starts_at',
        'ends_at',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'payment_channel',
        'midtrans_transaction_status',
        'fraud_status',
        'payment_code_label',
        'payment_code',
        'biller_code',
        'qr_url',
        'deeplink_url',
        'gateway_expires_at',
        'gateway_response',
        'gateway_notification',
        'superseded_at',
        'late_settlement_at',
        'paid_at',
    ];

    protected $hidden = [
        'gateway_response',
        'gateway_notification',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'gateway_expires_at' => 'datetime',
        'gateway_response' => 'array',
        'gateway_notification' => 'array',
        'superseded_at' => 'datetime',
        'late_settlement_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
