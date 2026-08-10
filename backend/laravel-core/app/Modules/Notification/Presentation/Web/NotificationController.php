<?php

namespace App\Modules\Notification\Presentation\Web;

use App\Http\Controllers\Controller;

use App\Modules\Notification\Infrastructure\Persistence\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $user = $request->user();

        abort_unless($user, 403);

        if ($request->expectsJson()) {
            $notificationQuery = $request->boolean('include_chat')
                ? AppNotification::query()->where('user_id', $user->id)->where('type', 'chat.message')
                : $this->visibleNotificationsQuery((int) $user->id);

            $notifications = $notificationQuery
                ->latest()
                ->limit(12)
                ->get()
                ->map(fn (AppNotification $notification) => $this->payload($notification));

            $unreadCount = $this->visibleNotificationsQuery((int) $user->id)
                ->whereNull('read_at')
                ->count();

            return response()->json([
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ]);
        }

        $status = $request->query('status', 'all');

        if (! in_array($status, ['all', 'unread', 'read'], true)) {
            $status = 'all';
        }

        $baseQuery = $this->visibleNotificationsQuery((int) $user->id);
        $summary = [
            'total' => (clone $baseQuery)->count(),
            'unread' => (clone $baseQuery)->whereNull('read_at')->count(),
            'read' => (clone $baseQuery)->whereNotNull('read_at')->count(),
        ];

        $notifications = $baseQuery
            ->when($status === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($status === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('notifications.index', [
            'context' => $this->notificationContext($request, $user),
            'layout' => $this->notificationLayout($request, $user),
            'notifications' => $notifications,
            'routes' => $this->notificationRoutes($request),
            'status' => $status,
            'summary' => $summary,
        ]);
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        abort_unless($user, 403);

        $this->visibleNotificationsQuery((int) $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if (! $request->expectsJson()) {
            return back()->with('success', 'All notifications have been marked as read.');
        }

        return response()->json([
            'ok' => true,
            'unread_count' => 0,
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse|RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()?->id, 403);
        abort_unless(
            $this->visibleNotificationsQuery((int) $request->user()->id)->whereKey($notification->id)->exists(),
            404
        );

        if (! $notification->read_at) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        $unreadCount = $this->visibleNotificationsQuery((int) $request->user()->id)
            ->whereNull('read_at')
            ->count();

        if (! $request->expectsJson()) {
            return back()->with('success', 'Notification has been marked as read.');
        }

        return response()->json([
            'ok' => true,
            'unread_count' => $unreadCount,
        ]);
    }

    private function payload(AppNotification $notification): array
    {
        return [
            'id' => (int) $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'url' => $notification->url,
            'data' => $notification->data ?? [],
            'is_read' => (bool) $notification->read_at,
            'time' => $notification->created_at?->diffForHumans() ?? '',
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    private function visibleNotificationsQuery(int $userId)
    {
        return AppNotification::query()
            ->where('user_id', $userId)
            ->visibleInNotificationCenter();
    }

    private function notificationContext(Request $request, $user): string
    {
        if ($request->routeIs('provider.*') || $request->routeIs('provider-branch.*')) {
            return 'provider';
        }

        return $user->role === 'provider' ? 'provider' : 'admin';
    }

    private function notificationLayout(Request $request, $user): string
    {
        return $this->notificationContext($request, $user) === 'provider'
            ? 'provider.layouts.dashboard'
            : 'admin.layouts.app';
    }

    private function notificationRoutes(Request $request): array
    {
        if ($request->routeIs('provider.*') || $request->routeIs('provider-branch.*')) {
            return [
                'index' => provider_route('provider.notifications.index'),
                'read_all' => provider_route('provider.notifications.read-all'),
                'read' => provider_route('provider.notifications.read', ['notification' => '__ID__']),
            ];
        }

        if ($request->routeIs('admin.*')) {
            return [
                'index' => route('admin.notifications.index'),
                'read_all' => route('admin.notifications.read-all'),
                'read' => url('/admin/notifications/__ID__/read'),
            ];
        }

        return [
            'index' => route('notifications.index'),
            'read_all' => route('notifications.read-all'),
            'read' => url('/notifications/__ID__/read'),
        ];
    }
}
