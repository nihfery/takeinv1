<?php

namespace App\Modules\Chat\Domain\Events;

use App\Modules\Chat\Infrastructure\Persistence\Models\ChatThread;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatThreadUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public string $queue = 'notifications';

    public int $tries = 3;

    public int $timeout = 15;

    public int $backoff = 10;

    public array $thread;

    public function __construct(ChatThread $thread)
    {
        $thread->loadMissing(['provider', 'providerUser', 'branchUser', 'closer']);

        $this->thread = [
            'id' => (int) $thread->id,
            'conversation_type' => $thread->conversation_type ?: 'provider_admin',
            'ticket_status' => $thread->ticket_status ?: 'none',
            'status' => $thread->status ?: 'open',
            'ticket_rejection_reason' => $thread->ticket_rejection_reason,
            'closed_at' => $thread->closed_at?->toIso8601String(),
            'closed_by' => $thread->closer?->name,
            'last_admin_read_at' => $thread->last_admin_read_at?->toIso8601String(),
            'last_provider_read_at' => $thread->last_provider_read_at?->toIso8601String(),
            'last_branch_read_at' => $thread->last_branch_read_at?->toIso8601String(),
            'read_receipts' => [
                'last_admin_read_at' => $thread->last_admin_read_at?->toIso8601String(),
                'last_provider_read_at' => $thread->last_provider_read_at?->toIso8601String(),
                'last_branch_read_at' => $thread->last_branch_read_at?->toIso8601String(),
            ],
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.thread.' . $this->thread['id']),
        ];
    }

    public function broadcastAs(): string
    {
        return 'thread.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'thread' => $this->thread,
        ];
    }
}
