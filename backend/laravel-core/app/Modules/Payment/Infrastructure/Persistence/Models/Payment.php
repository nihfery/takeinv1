<?php

namespace App\Modules\Payment\Infrastructure\Persistence\Models;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Payment\Infrastructure\Persistence\Models\PaymentGatewayTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $with = ['gatewayTransaction'];

    protected $hidden = ['gatewayTransaction', 'gateway_transaction'];

    protected $appends = [
        'payment_channel',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'midtrans_transaction_status',
        'fraud_status',
        'payment_code_label',
        'payment_code',
        'biller_code',
        'qr_url',
        'deeplink_url',
        'expiry_time',
    ];

    protected $fillable = [
        'booking_id',
        'payment_type',
        'amount',
        'status',
        'payment_method',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function gatewayTransaction()
    {
        return $this->hasOne(PaymentGatewayTransaction::class);
    }

    public function getPaymentChannelAttribute()
    {
        return $this->gatewayTransaction?->payment_channel;
    }

    public function getMidtransOrderIdAttribute()
    {
        return $this->gatewayTransaction?->provider_order_id;
    }

    public function getMidtransTransactionIdAttribute()
    {
        return $this->gatewayTransaction?->provider_transaction_id;
    }

    public function getMidtransTransactionStatusAttribute()
    {
        return $this->gatewayTransaction?->provider_status;
    }

    public function getFraudStatusAttribute()
    {
        return $this->gatewayTransaction?->fraud_status;
    }

    public function getPaymentCodeLabelAttribute()
    {
        return $this->gatewayTransaction?->payment_code_label;
    }

    public function getPaymentCodeAttribute()
    {
        return $this->gatewayTransaction?->payment_code;
    }

    public function getBillerCodeAttribute()
    {
        return $this->gatewayTransaction?->biller_code;
    }

    public function getQrUrlAttribute()
    {
        return $this->gatewayTransaction?->qr_url;
    }

    public function getDeeplinkUrlAttribute()
    {
        return $this->gatewayTransaction?->deeplink_url;
    }

    public function getExpiryTimeAttribute()
    {
        return $this->gatewayTransaction?->expires_at;
    }
}
