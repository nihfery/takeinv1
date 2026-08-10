<?php

namespace App\Modules\Chat\Presentation\Support;

use App\Modules\Chat\Infrastructure\Persistence\Models\ChatMessage;
use App\Modules\Chat\Infrastructure\Persistence\Models\ChatThread;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Media\Application\Services\ChatAttachmentStorage;
use Illuminate\Support\Str;

class ChatMessagePresenter
{
    public static function make(ChatMessage $message, ?User $viewer = null, ?ChatThread $thread = null): array
    {
        $message->loadMissing('sender');

        if (! $thread) {
            $message->loadMissing('thread');
        }

        $sender = $message->sender;
        $senderName = $sender?->name ?: ($message->sender_role === 'admin' ? 'Admin' : 'Provider');
        $createdAt = $message->created_at;
        $thread ??= $message->thread;
        $isMine = $viewer ? (int) $viewer->id === (int) $message->sender_id : false;
        $delivery = $thread ? self::deliveryStatus($message, $thread) : [
            'status' => 'sent',
            'label' => 'Dikirim',
            'read_at' => null,
        ];

        return [
            'id' => (int) $message->id,
            'thread_id' => (int) $message->chat_thread_id,
            'sender_id' => $message->sender_id ? (int) $message->sender_id : null,
            'sender_role' => $message->sender_role,
            'sender_name' => $senderName,
            'sender_email' => $sender?->email,
            'sender_initials' => self::initials($senderName),
            'body' => $message->body,
            'attachment' => self::attachment($message),
            'is_mine' => $isMine,
            'sent_at' => $createdAt?->format('H:i') ?? '',
            'sent_date' => $createdAt?->format('d M Y') ?? '',
            'created_at' => $createdAt?->toIso8601String(),
            'delivery_status' => $delivery['status'],
            'delivery_label' => $delivery['label'],
            'read_at' => $delivery['read_at']?->toIso8601String(),
        ];
    }

    private static function deliveryStatus(ChatMessage $message, ChatThread $thread): array
    {
        $readAt = $thread->{self::recipientReadColumn($message->sender_role, $thread)};
        $isRead = $readAt && $message->created_at && $readAt->gte($message->created_at);

        return [
            'status' => $isRead ? 'read' : 'sent',
            'label' => $isRead ? 'Dibaca' : 'Dikirim',
            'read_at' => $isRead ? $readAt : null,
        ];
    }

    private static function recipientReadColumn(string $senderRole, ChatThread $thread): string
    {
        if (($thread->conversation_type ?: 'provider_admin') === 'provider_branch') {
            return $senderRole === 'provider_branch'
                ? 'last_provider_read_at'
                : 'last_branch_read_at';
        }

        return $senderRole === 'admin'
            ? 'last_provider_read_at'
            : 'last_admin_read_at';
    }

    private static function attachment(ChatMessage $message): ?array
    {
        if (! $message->attachment_path) {
            return null;
        }

        return [
            'type' => str_starts_with((string) $message->attachment_mime, 'image/') ? 'image' : 'file',
            'url' => app(ChatAttachmentStorage::class)->temporaryUrl($message),
            'name' => $message->attachment_name,
            'mime' => $message->attachment_mime,
            'size' => $message->attachment_size ? (int) $message->attachment_size : null,
        ];
    }

    private static function initials(string $name): string
    {
        $parts = collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->values();

        if ($parts->count() >= 2) {
            return Str::upper(Str::substr($parts[0], 0, 1).Str::substr($parts[1], 0, 1));
        }

        return Str::upper(Str::substr($name, 0, 1) ?: 'U');
    }
}
