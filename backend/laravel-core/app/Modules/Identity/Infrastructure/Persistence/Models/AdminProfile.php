<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone_number',
        'position',
        'avatar',
        'bio',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
