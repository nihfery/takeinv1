<?php

use App\Modules\Chat\Application\Services\ChatThreadAccessService;
use App\Modules\Chat\Infrastructure\Persistence\Models\ChatThread;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['admin', 'provider', 'provider_branch', 'web']]);

Broadcast::channel('notifications.user.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['admin', 'provider', 'provider_branch', 'web']]);

Broadcast::channel('chat.thread.{threadId}', function (User $user, int $threadId) {
    $thread = ChatThread::query()->find($threadId);

    if (! $thread) {
        return false;
    }

    $access = app(ChatThreadAccessService::class);

    if ($user->role === 'admin') {
        return $access->threadType($thread) === 'provider_admin'
            && $access->threadChatApproved($thread);
    }

    if ($user->role !== 'provider') {
        return false;
    }

    $profile = ProviderMenuAccess::providerProfile($user);

    if ($profile?->status !== 'active' || $profile->document_status !== 'verified') {
        return false;
    }

    return $access->providerCanChatThread($user, $thread);
}, ['guards' => ['admin', 'provider', 'provider_branch', 'web']]);
