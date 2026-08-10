<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Models;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerActivity;
use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\AdminProfile;
use App\Modules\Notification\Infrastructure\Persistence\Models\AppNotification;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRole;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use Database\Factories\UserFactory;
// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;

class User extends Authenticatable
{

    use HasApiTokens, HasFactory, Notifiable;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'provider_id',
        'branch_id',
        'provider_role_id',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function adminProfile()
    {
        return $this->hasOne(AdminProfile::class);
    }

    public function providerProfile()
    {
        return $this->hasOne(ProviderProfile::class, 'user_id');
    }

    public function parentProvider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function providerBranch()
    {
        return $this->belongsTo(ProviderBranch::class, 'branch_id');
    }

    public function providerRole()
    {
        return $this->belongsTo(ProviderRole::class, 'provider_role_id');
    }

    public function customerProfile()
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function providerBookings()
    {
        return $this->hasMany(Booking::class, 'provider_id');
    }

    public function customerBookings()
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    public function customerActivities()
    {
        return $this->hasMany(CustomerActivity::class, 'customer_id');
    }

    public function providerBranches()
    {
        return $this->hasMany(ProviderBranch::class, 'provider_id');
    }

    public function branchAccounts()
    {
        return $this->hasMany(User::class, 'provider_id')->where('role', 'provider');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'provider_id');
    }

    public function providerStaffs()
    {
        return $this->hasMany(ProviderStaff::class, 'provider_id');
    }

    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isProvider(): bool
    {
        return $this->role === 'provider';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }


public function isProviderAccountActive(): bool
{
    if ($this->role !== 'provider') {
        return true;
    }

    return optional(ProviderProfile::where('user_id', ProviderMenuAccess::providerOwnerId($this))->first())->status === 'active';
}

public function isProviderDocumentVerified(): bool
{
    if ($this->role !== 'provider') {
        return true;
    }

    return optional(ProviderProfile::where('user_id', ProviderMenuAccess::providerOwnerId($this))->first())->document_status === 'verified';
}
}
