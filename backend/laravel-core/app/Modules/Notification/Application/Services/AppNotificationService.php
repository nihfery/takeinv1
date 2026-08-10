<?php

namespace App\Modules\Notification\Application\Services;

use App\Modules\Notification\Domain\Events\UserNotificationSent;
use App\Modules\Notification\Infrastructure\Persistence\Models\AppNotification;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use Illuminate\Support\Collection;
use Throwable;

class AppNotificationService
{
    public function createForUser(User $user, string $type, string $title, ?string $body = null, ?string $url = null, array $data = []): AppNotification
    {
        $notification = AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'data' => $data,
        ]);

        $unreadCount = $this->visibleUnreadCount($user);

        try {
            broadcast(new UserNotificationSent($notification, $unreadCount));
        } catch (Throwable) {
            // Database notifications should still be saved if realtime is temporarily unavailable.
        }

        return $notification;
    }

    public function createForUsers(iterable $users, string $type, string $title, ?string $body = null, ?string $url = null, array $data = [], ?int $exceptUserId = null): void
    {
        collect($users)
            ->filter(fn (?User $user) => $user && (! $exceptUserId || (int) $user->id !== (int) $exceptUserId))
            ->unique(fn (User $user) => (int) $user->id)
            ->each(fn (User $user) => $this->createForUser($user, $type, $title, $body, $url, $data));
    }

    public function adminRecipients(): Collection
    {
        return User::query()
            ->where('role', 'admin')
            ->get();
    }

    public function providerRecipients(int $providerId, ?string $menuKey = null): Collection
    {
        return User::query()
            ->where('role', 'provider')
            ->where(function ($query) use ($providerId) {
                $query->whereKey($providerId)
                    ->orWhere('provider_id', $providerId);
            })
            ->get()
            ->filter(fn (User $user) => ProviderMenuAccess::userCanAccess($user, $menuKey));
    }

    private function visibleUnreadCount(User $user): int
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->visibleInNotificationCenter()
            ->whereNull('read_at')
            ->count();
    }
}
