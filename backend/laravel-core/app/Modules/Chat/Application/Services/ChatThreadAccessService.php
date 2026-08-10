<?php

namespace App\Modules\Chat\Application\Services;

use App\Modules\Chat\Infrastructure\Persistence\Models\ChatThread;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;

class ChatThreadAccessService
{
    private const TYPE_PROVIDER_ADMIN = 'provider_admin';

    private const TYPE_PROVIDER_BRANCH = 'provider_branch';

    public function providerCanChatThread(?User $user, ChatThread $thread): bool
    {
        return $user?->role === 'provider'
            && ProviderMenuAccess::userCanAccess($user, 'chat')
            && $this->threadChatApproved($thread)
            && $this->providerCanAccessThread($user, $thread);
    }

    public function providerCanEndThread(?User $user, ChatThread $thread): bool
    {
        return false;
    }

    public function providerCanAccessThread(User $user, ChatThread $thread): bool
    {
        if ($this->threadType($thread) === self::TYPE_PROVIDER_ADMIN) {
            if ($thread->provider_user_id) {
                return (int) $thread->provider_user_id === (int) $user->id;
            }

            return ProviderMenuAccess::isProviderOwner($user)
                && (int) $thread->provider_id === (int) $user->id;
        }

        return in_array((int) $user->id, [
            (int) $thread->provider_user_id,
            (int) $thread->branch_user_id,
        ], true)
            && ProviderMenuAccess::providerOwnerId($user) === (int) $thread->provider_id;
    }

    public function readerRoleForUser(ChatThread $thread, User $user): string
    {
        if ($this->threadType($thread) === self::TYPE_PROVIDER_BRANCH) {
            return (int) $thread->provider_user_id === (int) $user->id
                ? 'provider_owner'
                : 'provider_branch';
        }

        return 'provider';
    }

    public function senderRoleForUser(ChatThread $thread, User $user): string
    {
        return $this->readerRoleForUser($thread, $user);
    }

    public function readColumnForRole(string $role): string
    {
        return match ($role) {
            'admin' => 'last_admin_read_at',
            'provider_branch' => 'last_branch_read_at',
            default => 'last_provider_read_at',
        };
    }

    public function oppositeSenderRoles(ChatThread $thread, string $readerRole): array
    {
        if ($this->threadType($thread) === self::TYPE_PROVIDER_BRANCH) {
            return $readerRole === 'provider_branch'
                ? ['provider_owner']
                : ['provider_branch'];
        }

        return $readerRole === 'admin'
            ? ['provider']
            : ['admin'];
    }

    public function threadType(ChatThread $thread): string
    {
        return $thread->conversation_type ?: self::TYPE_PROVIDER_ADMIN;
    }

    public function threadChatApproved(ChatThread $thread): bool
    {
        return $thread->ticket_status === 'approved'
            && $thread->status !== 'closed'
            && $thread->closed_at === null;
    }
}
