<?php

namespace App\Modules\Payment\Infrastructure\Persistence\Models;

use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'gateway',
        'payment_channel',
        'provider_order_id',
        'provider_transaction_id',
        'provider_status',
        'fraud_status',
        'payment_code_label',
        'payment_code',
        'biller_code',
        'qr_url',
        'deeplink_url',
        'expires_at',
        'raw_response',
        'raw_notification',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'raw_response' => 'array',
            'raw_notification' => 'array',
        ];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
