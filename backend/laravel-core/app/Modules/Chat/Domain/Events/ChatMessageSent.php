<?php

namespace App\Modules\Chat\Domain\Events;

use App\Modules\Chat\Infrastructure\Persistence\Models\ChatMessage;
use App\Modules\Chat\Presentation\Support\ChatMessagePresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public string $queue = 'notifications';

    public int $tries = 3;

    public int $timeout = 15;

    public int $backoff = 10;

    public array $message;

    public function __construct(ChatMessage $message)
    {
        $message->loadMissing(['sender', 'thread']);

        $this->message = ChatMessagePresenter::make($message, null, $message->thread);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.thread.' . $this->message['thread_id']),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
        ];
    }
}
