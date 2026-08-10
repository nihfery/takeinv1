<?php

namespace App\Modules\Provider\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Subscription\Infrastructure\Persistence\Models\ProviderSubscription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'image',
        'phone_number',
        'status',
        'document_status',
        'ktp_image',
        'nib_number',
        'nib_document',
        'business_image',
        'document_note',
        'document_submitted_at',
        'document_verified_at',
    ];

    protected $hidden = [
        'ktp_image',
        'nib_document',
    ];

    protected $appends = [
        'has_ktp_document',
        'has_nib_document',
    ];

    protected $casts = [
        'document_submitted_at' => 'datetime',
        'document_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function getHasKtpDocumentAttribute(): bool
    {
        return filled($this->ktp_image);
    }

    public function getBusinessImageUrlAttribute(): ?string
    {
        return $this->business_image ? asset('storage/' . $this->business_image) : null;
    }

    public function getHasNibDocumentAttribute(): bool
    {
        return filled($this->nib_document);
    }

    public function activeSubscription()
    {
        return $this->hasOne(ProviderSubscription::class, 'provider_id', 'user_id')
                    ->where('subscription_status', 'active')
                    ->where(function($q) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                    })
                    ->latest('id');
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }
}
