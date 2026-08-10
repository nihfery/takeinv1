<?php

namespace App\Modules\Chat\Application\Support;

use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use App\Modules\Chat\Infrastructure\Persistence\Models\ChatMessage;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Cache;

class ChatUnreadCounter
{
    private const TYPE_PROVIDER_ADMIN = 'provider_admin';
    private const TYPE_PROVIDER_BRANCH = 'provider_branch';
    private const CACHE_SECONDS = 12;

    public static function forUser(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return (int) Cache::remember(self::cacheKey($user), self::CACHE_SECONDS, fn () => match ($user->role) {
            'admin' => self::forAdmin(),
            'provider' => self::forProvider($user),
            default => 0,
        });
    }

    public static function forgetForUser(?User $user): void
    {
        if (! $user) {
            return;
        }

        Cache::forget(self::cacheKey($user));
    }

    private static function cacheKey(User $user): string
    {
        return 'chat_unread_count:user:' . (int) $user->id;
    }

    private static function forAdmin(): int
    {
        return ChatMessage::query()
            ->join('chat_threads', 'chat_messages.chat_thread_id', '=', 'chat_threads.id')
            ->where('chat_threads.conversation_type', self::TYPE_PROVIDER_ADMIN)
            ->where('chat_threads.ticket_status', 'approved')
            ->where(function ($query) {
                $query->whereNull('chat_threads.status')
                    ->orWhere('chat_threads.status', '!=', 'closed');
            })
            ->whereNull('chat_threads.closed_at')
            ->where('chat_messages.sender_role', 'provider')
            ->where(function ($query) {
                $query->whereNull('chat_threads.last_admin_read_at')
                    ->orWhereColumn('chat_messages.created_at', '>', 'chat_threads.last_admin_read_at');
            })
            ->count();
    }

    private static function forProvider(User $user): int
    {
        if (! ProviderMenuAccess::userCanAccess($user, 'chat')) {
            return 0;
        }

        return self::providerAdminCount($user)
            + self::providerInternalOwnerCount($user)
            + self::providerInternalBranchCount($user);
    }

    private static function providerAdminCount(User $user): int
    {
        $providerId = ProviderMenuAccess::providerOwnerId($user);
        $isProviderOwner = ProviderMenuAccess::isProviderOwner($user);

        return ChatMessage::query()
            ->join('chat_threads', 'chat_messages.chat_thread_id', '=', 'chat_threads.id')
            ->where('chat_threads.conversation_type', self::TYPE_PROVIDER_ADMIN)
            ->where('chat_threads.provider_id', $providerId)
            ->where('chat_threads.ticket_status', 'approved')
            ->where(function ($query) {
                $query->whereNull('chat_threads.status')
                    ->orWhere('chat_threads.status', '!=', 'closed');
            })
            ->whereNull('chat_threads.closed_at')
            ->where('chat_messages.sender_role', 'admin')
            ->where(function ($query) use ($user, $isProviderOwner) {
                $query->where('chat_threads.provider_user_id', $user->id);

                if ($isProviderOwner) {
                    $query->orWhereNull('chat_threads.provider_user_id');
                }
            })
            ->where(function ($query) {
                $query->whereNull('chat_threads.last_provider_read_at')
                    ->orWhereColumn('chat_messages.created_at', '>', 'chat_threads.last_provider_read_at');
            })
            ->count();
    }

    private static function providerInternalOwnerCount(User $user): int
    {
        return ChatMessage::query()
            ->join('chat_threads', 'chat_messages.chat_thread_id', '=', 'chat_threads.id')
            ->where('chat_threads.conversation_type', self::TYPE_PROVIDER_BRANCH)
            ->where('chat_threads.provider_id', ProviderMenuAccess::providerOwnerId($user))
            ->where('chat_threads.ticket_status', 'approved')
            ->where('chat_threads.provider_user_id', $user->id)
            ->where('chat_messages.sender_role', 'provider_branch')
            ->where(function ($query) {
                $query->whereNull('chat_threads.last_provider_read_at')
                    ->orWhereColumn('chat_messages.created_at', '>', 'chat_threads.last_provider_read_at');
            })
            ->count();
    }

    private static function providerInternalBranchCount(User $user): int
    {
        return ChatMessage::query()
            ->join('chat_threads', 'chat_messages.chat_thread_id', '=', 'chat_threads.id')
            ->where('chat_threads.conversation_type', self::TYPE_PROVIDER_BRANCH)
            ->where('chat_threads.provider_id', ProviderMenuAccess::providerOwnerId($user))
            ->where('chat_threads.ticket_status', 'approved')
            ->where('chat_threads.branch_user_id', $user->id)
            ->where('chat_messages.sender_role', 'provider_owner')
            ->where(function ($query) {
                $query->whereNull('chat_threads.last_branch_read_at')
                    ->orWhereColumn('chat_messages.created_at', '>', 'chat_threads.last_branch_read_at');
            })
            ->count();
    }
}
