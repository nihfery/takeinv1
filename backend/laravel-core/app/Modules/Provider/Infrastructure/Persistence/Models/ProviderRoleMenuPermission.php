<?php

namespace App\Modules\Provider\Infrastructure\Persistence\Models;

use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderRoleMenuPermission extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'provider_role_id',
        'menu_key',
    ];

    public function role()
    {
        return $this->belongsTo(ProviderRole::class, 'provider_role_id');
    }
}
