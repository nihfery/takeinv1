<?php

namespace App\Modules\Notification\Domain\Events;

use App\Modules\Notification\Infrastructure\Persistence\Models\AppNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNotificationSent implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public string $queue = 'notifications';

    public int $tries = 3;

    public int $timeout = 15;

    public int $backoff = 10;

    public function __construct(
        public AppNotification $notification,
        public int $unreadCount
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications.user.' . $this->notification->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => $this->payload(),
            'unread_count' => $this->unreadCount,
        ];
    }

    private function payload(): array
    {
        return [
            'id' => (int) $this->notification->id,
            'type' => $this->notification->type,
            'title' => $this->notification->title,
            'body' => $this->notification->body,
            'url' => $this->notification->url,
            'data' => $this->notification->data ?? [],
            'is_read' => (bool) $this->notification->read_at,
            'time' => $this->notification->created_at?->diffForHumans() ?? '',
            'created_at' => $this->notification->created_at?->toIso8601String(),
        ];
    }
}
