<?php

namespace App\Modules\Audit\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'action',
        'resource_type',
        'resource_id',
        'provider_id',
        'branch_id',
        'request_id',
        'correlation_id',
        'ip',
        'user_agent',
        'before',
        'after',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }
}
