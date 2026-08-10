<?php

namespace App\Modules\Chat\Infrastructure\Persistence\Models;

use App\Modules\Chat\Infrastructure\Persistence\Models\ChatMessage;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatThread extends Model
{
    protected $fillable = [
        'provider_id',
        'provider_user_id',
        'branch_user_id',
        'conversation_type',
        'last_message_id',
        'last_message_at',
        'last_admin_read_at',
        'last_provider_read_at',
        'last_branch_read_at',
        'status',
        'ticket_status',
        'ticket_subject',
        'ticket_body',
        'ticket_rejection_reason',
        'ticket_requested_at',
        'ticket_reviewed_at',
        'ticket_reviewed_by',
        'opened_by_user_id',
        'closed_by_user_id',
        'closed_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'last_admin_read_at' => 'datetime',
        'last_provider_read_at' => 'datetime',
        'last_branch_read_at' => 'datetime',
        'ticket_requested_at' => 'datetime',
        'ticket_reviewed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function providerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function branchUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'branch_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_message_id');
    }

    public function ticketReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ticket_reviewed_by');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}
