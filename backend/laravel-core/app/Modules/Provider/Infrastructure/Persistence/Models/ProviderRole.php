<?php

namespace App\Modules\Provider\Infrastructure\Persistence\Models;

use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRoleMenuPermission;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'branch_id',
        'role_name',
        'slug',
        'description',
        'status',
    ];

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function branch()
    {
        return $this->belongsTo(ProviderBranch::class, 'branch_id');
    }

    public function menuPermissions()
    {
        return $this->hasMany(ProviderRoleMenuPermission::class, 'provider_role_id');
    }

    public function staffs()
    {
        return $this->hasMany(ProviderStaff::class, 'provider_role_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'provider_role_id');
    }

    public function getCanBeDeletedAttribute(): bool
    {
        return !$this->users()->exists() && !$this->staffs()->exists();
    }
}
