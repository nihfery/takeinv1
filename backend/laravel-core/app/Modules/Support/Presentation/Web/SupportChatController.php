<?php

namespace App\Modules\Support\Presentation\Web;

use App\Http\Controllers\Controller;
use App\Modules\Chat\Application\Services\ChatThreadAccessService;
use App\Modules\Chat\Application\Support\ChatUnreadCounter;
use App\Modules\Chat\Domain\Events\ChatMessageSent;
use App\Modules\Chat\Domain\Events\ChatThreadUpdated;
use App\Modules\Chat\Infrastructure\Persistence\Models\ChatMessage;
use App\Modules\Chat\Infrastructure\Persistence\Models\ChatThread;
use App\Modules\Chat\Presentation\Support\ChatMessagePresenter;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Media\Application\Services\ChatAttachmentStorage;
use App\Modules\Notification\Application\Services\AppNotificationService;
use App\Modules\Notification\Infrastructure\Persistence\Models\AppNotification;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use App\Modules\Subscription\Application\Services\ProviderEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupportChatController extends Controller
{
    private const TYPE_PROVIDER_ADMIN = 'provider_admin';

    private const TYPE_PROVIDER_BRANCH = 'provider_branch';

    private const THREAD_LIST_LIMIT = 40;

    private const MESSAGE_PAGE_LIMIT = 80;

    public function __construct(
        private readonly ChatThreadAccessService $chatThreadAccess,
        private readonly ChatAttachmentStorage $chatAttachments,
    ) {}

    public function adminIndex(Request $request)
    {
        $admin = $request->user();

        abort_unless($admin?->role === 'admin', 403);

        $search = trim((string) $request->query('search', ''));
        $activeTab = $request->query('tab') === 'tickets' ? 'tickets' : 'messages';
        $providerIds = $this->providerOwners($search)->pluck('id');

        $threads = ChatThread::query()
            ->with(['provider.providerProfile', 'providerUser.providerBranch', 'lastMessage.sender'])
            ->whereIn('provider_id', $providerIds)
            ->where('conversation_type', self::TYPE_PROVIDER_ADMIN)
            ->where('ticket_status', 'approved')
            ->orderByDesc('last_message_at')
            ->orderByDesc('ticket_requested_at')
            ->orderByDesc('updated_at')
            ->limit(self::THREAD_LIST_LIMIT)
            ->get();

        $ticketThreads = ChatThread::query()
            ->with(['provider.providerProfile', 'providerUser.providerBranch', 'ticketReviewer', 'lastMessage.sender'])
            ->whereIn('provider_id', $providerIds)
            ->where('conversation_type', self::TYPE_PROVIDER_ADMIN)
            ->whereIn('ticket_status', ['pending', 'approved', 'rejected', 'closed'])
            ->orderByDesc('ticket_requested_at')
            ->orderByDesc('ticket_reviewed_at')
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->limit(self::THREAD_LIST_LIMIT)
            ->get();

        $requestedThreadId = (int) $request->query('thread');
        $requestedTicketId = (int) $request->query('ticket');
        $listOnly = $request->boolean('list') || $activeTab === 'tickets';
        $requestedThread = $requestedThreadId > 0 ? $threads->firstWhere('id', $requestedThreadId) : null;

        if ($requestedThreadId > 0 && ! $requestedThread && ! $listOnly) {
            $requestedThread = ChatThread::query()
                ->with(['provider.providerProfile', 'providerUser.providerBranch', 'lastMessage.sender'])
                ->whereKey($requestedThreadId)
                ->whereIn('provider_id', $providerIds)
                ->where('conversation_type', self::TYPE_PROVIDER_ADMIN)
                ->where('ticket_status', 'approved')
                ->first();

            if ($requestedThread) {
                $threads = collect([$requestedThread])
                    ->merge($threads)
                    ->unique('id')
                    ->values();
            }
        }

        $this->applyUnreadCounts($threads, 'admin');

        $activeThread = $listOnly ? null : $requestedThread;
        $activeTicketThread = $activeTab === 'tickets' && $requestedTicketId > 0
            ? $ticketThreads->firstWhere('id', $requestedTicketId)
            : null;
        $activeThreadCanChat = $activeThread ? $this->threadChatApproved($activeThread) : false;
        $messages = collect();

        if ($activeThread && $activeThreadCanChat) {
            $messages = $this->messagesFor($activeThread, $admin);
            $this->markThreadRead($activeThread, 'admin');
            $activeThread->setAttribute('unread_count', 0);
        }

        $approvedThreadIds = $threads
            ->filter(fn (ChatThread $thread) => $this->threadChatApproved($thread))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('admin.chat.index', [
            'threads' => $threads,
            'ticketThreads' => $ticketThreads,
            'activeThread' => $activeThread,
            'activeTicketThread' => $activeTicketThread,
            'activeThreadCanChat' => $activeThreadCanChat,
            'messages' => $messages,
            'search' => $search,
            'activeTab' => $activeTab,
            'chatConfig' => $this->chatConfig(
                $admin,
                $activeThread,
                $approvedThreadIds,
                $activeThreadCanChat ? route('admin.chat.messages.store', $activeThread) : null,
                $activeThreadCanChat ? route('admin.chat.read', $activeThread) : null,
                $activeThreadCanChat ? route('admin.chat.messages.index', $activeThread) : null
            ),
        ]);
    }

    public function adminTicketsIndex(Request $request)
    {
        $admin = $request->user();

        abort_unless($admin?->role === 'admin', 403);

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'pending');
        $allowedStatuses = ['pending', 'approved', 'rejected', 'closed', 'all'];
        $status = in_array($status, $allowedStatuses, true) ? $status : 'pending';
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
        $sortBy = (string) $request->query('sort_by', 'requested_at');
        $sortDirection = strtolower((string) $request->query('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['requested_at', 'reviewed_at', 'last_message_at', 'status', 'subject', 'requester', 'provider'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'requested_at';

        $baseQuery = ChatThread::query()
            ->with([
                'provider.providerProfile',
                'providerUser.providerBranch',
                'providerUser.providerRole',
                'ticketReviewer',
                'opener.providerBranch',
                'closer',
                'lastMessage.sender',
            ])
            ->where('conversation_type', self::TYPE_PROVIDER_ADMIN)
            ->whereIn('ticket_status', ['pending', 'approved', 'rejected', 'closed'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('ticket_subject', 'like', "%{$search}%")
                        ->orWhere('ticket_body', 'like', "%{$search}%")
                        ->orWhere('ticket_rejection_reason', 'like', "%{$search}%")
                        ->orWhereHas('provider', function ($providerQuery) use ($search) {
                            $providerQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhereHas('providerProfile', function ($profileQuery) use ($search) {
                                    $profileQuery
                                        ->where('phone_number', 'like', "%{$search}%")
                                        ->orWhere('category', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('providerUser', function ($requesterQuery) use ($search) {
                            $requesterQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhereHas('providerBranch', function ($branchQuery) use ($search) {
                                    $branchQuery->where('branch_name', 'like', "%{$search}%");
                                })
                                ->orWhereHas('providerRole', function ($roleQuery) use ($search) {
                                    $roleQuery->where('role_name', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('ticketReviewer', function ($reviewerQuery) use ($search) {
                            $reviewerQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });

        $statusCounts = (clone $baseQuery)
            ->select('ticket_status', DB::raw('count(*) as aggregate'))
            ->groupBy('ticket_status')
            ->pluck('aggregate', 'ticket_status');

        $allThreads = (clone $baseQuery)
            ->when($status !== 'all', fn ($query) => $query->where('ticket_status', $status))
            ->get()
            ->sortBy(function (ChatThread $thread) use ($sortBy) {
                return match ($sortBy) {
                    'reviewed_at' => optional($thread->ticket_reviewed_at)->timestamp ?: 0,
                    'last_message_at' => optional($thread->last_message_at)->timestamp ?: 0,
                    'status' => $thread->ticket_status ?: '',
                    'subject' => Str::lower($thread->ticket_subject ?: ''),
                    'requester' => Str::lower(($thread->providerUser ?: $thread->provider)?->name ?: ''),
                    'provider' => Str::lower($thread->provider?->name ?: ''),
                    default => optional($thread->ticket_requested_at ?: $thread->created_at)->timestamp ?: 0,
                };
            }, SORT_REGULAR, $sortDirection === 'desc')
            ->values();

        $requestedThreadId = (int) $request->query('thread');
        $page = max(1, (int) $request->query('page', 1));
        $threads = new LengthAwarePaginator(
            $allThreads->forPage($page, $perPage)->values(),
            $allThreads->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
        $activeThread = $allThreads->firstWhere('id', $requestedThreadId) ?: $threads->getCollection()->first();
        $summary = [
            'total' => (int) $statusCounts->sum(),
            'pending' => (int) ($statusCounts['pending'] ?? 0),
            'approved' => (int) ($statusCounts['approved'] ?? 0),
            'completed' => (int) ($statusCounts['rejected'] ?? 0) + (int) ($statusCounts['closed'] ?? 0),
        ];

        return view('admin.tickets.index', [
            'threads' => $threads,
            'activeThread' => $activeThread,
            'search' => $search,
            'status' => $status,
            'perPage' => $perPage,
            'sortBy' => $sortBy,
            'sortDirection' => $sortDirection,
            'statusCounts' => $statusCounts,
            'summary' => $summary,
        ]);
    }

    public function providerIndex(Request $request)
    {
        $providerUser = $request->user();

        abort_unless($providerUser?->role === 'provider', 403);

        $adminThread = $this->activeProviderAdminThread($providerUser);
        $adminThread->loadMissing(['provider.providerProfile', 'providerUser.providerBranch', 'ticketReviewer']);

        $threads = $this->providerChatThreads($providerUser);
        $internalContacts = $this->internalChatContacts($providerUser);

        $requestedThreadId = (int) $request->query('thread');
        $listOnly = $request->boolean('list');
        $requestedThread = $requestedThreadId > 0 ? $threads->firstWhere('id', $requestedThreadId) : null;

        if ($requestedThreadId > 0 && ! $requestedThread && ! $listOnly) {
            $requestedThread = ChatThread::query()
                ->with(['provider.providerProfile', 'providerUser.providerBranch', 'branchUser.providerBranch', 'lastMessage.sender'])
                ->whereKey($requestedThreadId)
                ->first();

            if ($requestedThread && $this->providerCanAccessThread($providerUser, $requestedThread)) {
                $threads = collect([$requestedThread])
                    ->merge($threads)
                    ->unique('id')
                    ->values();
            } else {
                $requestedThread = null;
            }
        }

        $this->applyUnreadCounts($threads, fn (ChatThread $thread) => $this->readerRoleForUser($thread, $providerUser));

        $activeThread = $listOnly ? null : $requestedThread;
        $activeThreadCanChat = $activeThread ? $this->threadChatApproved($activeThread) : false;
        $messages = collect();

        if ($activeThread && $activeThreadCanChat) {
            $messages = $this->messagesFor($activeThread, $providerUser);
            $this->markThreadRead($activeThread, $this->readerRoleForUser($activeThread, $providerUser));
            $activeThread->setAttribute('unread_count', 0);
        }

        return view('provider.pages.chat.index', [
            'adminThread' => $adminThread,
            'threads' => $threads,
            'internalContacts' => $internalContacts,
            'activeThread' => $activeThread,
            'activeThreadCanChat' => $activeThreadCanChat,
            'messages' => $messages,
            'chatConfig' => $this->chatConfig(
                $providerUser,
                $activeThread,
                $threads->pluck('id')->map(fn ($id) => (int) $id)->all(),
                $activeThreadCanChat ? provider_route('provider.chat.messages.store', $activeThread) : null,
                $activeThreadCanChat ? provider_route('provider.chat.read', $activeThread) : null,
                $activeThreadCanChat ? provider_route('provider.chat.messages.index', $activeThread) : null
            ),
        ]);
    }

    public function providerTicketsIndex(Request $request)
    {
        $providerUser = $request->user();

        abort_unless($providerUser?->role === 'provider', 403);

        $adminThread = $this->activeProviderAdminThread($providerUser);
        $adminThread->loadMissing(['provider.providerProfile', 'providerUser.providerBranch', 'ticketReviewer']);

        $isProviderOwner = ProviderMenuAccess::isProviderOwner($providerUser);
        $canUseSupportChat = app(ProviderEntitlementService::class)->canUseSupportChat($providerUser);

        return view('provider.pages.tickets.index', [
            'adminThread' => $adminThread,
            'thread' => $adminThread,
            'canChat' => $this->threadChatApproved($adminThread) && $canUseSupportChat,
            'isLocked' => ! $canUseSupportChat,
            'isProviderOwner' => $isProviderOwner,
        ]);
    }

    public function providerTicketStore(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! app(ProviderEntitlementService::class)->canUseSupportChat($user)) {
            return back()->with('error', 'Layanan Support Chat hanya tersedia untuk akun berlangganan aktif.');
        }

        abort_unless(
            $user?->role === 'provider'
            && ProviderMenuAccess::userCanAccess($user, 'tickets'),
            403
        );

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $subject = trim($validated['subject']);
        $body = trim($validated['body']);

        if ($subject === '' || $body === '') {
            return back()
                ->withErrors(['subject' => 'Ticket subject and details cannot be empty.'])
                ->withInput();
        }

        $thread = $this->activeProviderAdminThread($user);

        if ($this->threadChatApproved($thread)) {
            return provider_route_redirect('provider.chat.index')
                ->with('success', 'Ticket approved. Admin chat is now open.');
        }

        if ($thread->ticket_status === 'pending') {
            return provider_route_redirect('provider.tickets.index')
                ->with('success', 'Your chat ticket is still waiting for admin approval.');
        }

        $thread->forceFill([
            'ticket_status' => 'pending',
            'ticket_subject' => $subject,
            'ticket_body' => $body,
            'ticket_rejection_reason' => null,
            'ticket_requested_at' => now(),
            'ticket_reviewed_at' => null,
            'ticket_reviewed_by' => null,
            'opened_by_user_id' => $user->id,
            'closed_by_user_id' => null,
            'closed_at' => null,
            'status' => 'open',
        ])->save();

        $this->notificationService()->createForUsers(
            $this->notificationService()->adminRecipients(),
            'ticket.pending',
            'New chat ticket',
            ($user->name ?: 'Provider').' submitted a chat ticket to admin.',
            route('admin.tickets.index', ['status' => 'pending', 'thread' => $thread->id]),
            [
                'thread_id' => (int) $thread->id,
                'provider_id' => (int) $thread->provider_id,
                'subject' => $subject,
            ],
            (int) $user->id
        );

        return provider_route_redirect('provider.tickets.index')
            ->with('success', 'Chat ticket submitted. Admin will review it.');
    }

    public function providerInternalStart(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user?->role === 'provider'
            && ProviderMenuAccess::userCanAccess($user, 'chat'),
            403
        );

        $providerId = ProviderMenuAccess::providerOwnerId($user);
        $contactIds = $this->internalChatContacts($user)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $validated = $request->validate([
            'contact_user_id' => ['required', 'integer', Rule::in($contactIds)],
        ]);

        $contact = $this->internalChatContacts($user)->firstWhere('id', (int) $validated['contact_user_id']);
        abort_unless($contact, 404);

        $thread = $this->ensureInternalThread($providerId, $user, $contact);

        return provider_route_redirect('provider.chat.index', ['thread' => $thread->id])
            ->with('success', 'Internal chat opened. Conversation history remains available.');
    }

    public function providerInternalTicketStore(Request $request): RedirectResponse
    {
        return $this->providerInternalStart($request);
    }

    public function adminStore(Request $request, ChatThread $thread): JsonResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);
        abort_unless($this->threadType($thread) === self::TYPE_PROVIDER_ADMIN, 404);
        abort_unless($this->threadChatApproved($thread), 403, 'Chat ticket has not been approved yet.');

        return $this->storeMessage($request, $thread, 'admin');
    }

    public function adminMessages(Request $request, ChatThread $thread): JsonResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);
        abort_unless($this->threadType($thread) === self::TYPE_PROVIDER_ADMIN, 404);
        abort_unless(in_array($thread->ticket_status, ['approved', 'closed'], true), 403);

        return $this->messagesResponse($request, $thread, $request->user());
    }

    public function providerStore(Request $request, ChatThread $thread): JsonResponse
    {
        $user = $request->user();

        if (! app(ProviderEntitlementService::class)->canUseSupportChat($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan Support Chat hanya tersedia untuk akun berlangganan aktif.',
            ], 403);
        }

        abort_unless($this->providerCanChatThread($user, $thread), 403);

        return $this->storeMessage($request, $thread, $this->senderRoleForUser($thread, $user));
    }

    public function providerMessages(Request $request, ChatThread $thread): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user?->role === 'provider'
            && ProviderMenuAccess::userCanAccess($user, 'chat')
            && $this->providerCanAccessThread($user, $thread)
            && in_array($thread->ticket_status, ['approved', 'closed'], true),
            403
        );

        return $this->messagesResponse($request, $thread, $user);
    }

    public function adminRead(Request $request, ChatThread $thread): JsonResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);
        abort_unless($this->threadType($thread) === self::TYPE_PROVIDER_ADMIN, 404);
        abort_unless($this->threadChatApproved($thread), 403);

        $this->markThreadRead($thread, 'admin');

        return response()->json([
            'ok' => true,
            'thread' => $this->threadStatePayload($thread),
        ]);
    }

    public function providerRead(Request $request, ChatThread $thread): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->providerCanChatThread($user, $thread), 403);

        $this->markThreadRead($thread, $this->readerRoleForUser($thread, $user));

        return response()->json([
            'ok' => true,
            'thread' => $this->threadStatePayload($thread),
        ]);
    }

    public function adminTicketApprove(Request $request, ChatThread $thread): RedirectResponse
    {
        $admin = $request->user();

        abort_unless($admin?->role === 'admin', 403);
        abort_unless($this->threadType($thread) === self::TYPE_PROVIDER_ADMIN, 404);
        abort_unless(in_array($thread->ticket_status, ['pending', 'rejected'], true), 404);

        $thread->forceFill([
            'ticket_status' => 'approved',
            'ticket_rejection_reason' => null,
            'ticket_reviewed_at' => now(),
            'ticket_reviewed_by' => $admin->id,
            'last_admin_read_at' => now(),
            'closed_by_user_id' => null,
            'closed_at' => null,
            'status' => 'open',
        ])->save();

        $this->notifyProviderParticipants(
            $thread,
            'tickets',
            'ticket.approved',
            'Chat ticket approved',
            'Admin approved the chat ticket. Chat is now available.',
            'provider.chat.index',
            ['thread' => $thread->id],
            (int) $admin->id
        );

        $message = 'Ticket approved.';

        if ($request->input('return_to') === 'chat') {
            return redirect()
                ->route('admin.chat.index', ['tab' => 'tickets', 'ticket' => $thread->id])
                ->with('success', $message);
        }

        return redirect()
            ->route('admin.tickets.index', ['status' => 'approved', 'thread' => $thread->id])
            ->with('success', $message);
    }

    public function adminTicketReject(Request $request, ChatThread $thread): RedirectResponse
    {
        $admin = $request->user();

        abort_unless($admin?->role === 'admin', 403);
        abort_unless($this->threadType($thread) === self::TYPE_PROVIDER_ADMIN, 404);
        abort_unless($thread->ticket_status === 'pending', 404);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = trim((string) ($validated['reason'] ?? ''));

        $thread->forceFill([
            'ticket_status' => 'rejected',
            'ticket_rejection_reason' => $reason,
            'ticket_reviewed_at' => now(),
            'ticket_reviewed_by' => $admin->id,
        ])->save();

        $this->notifyProviderParticipants(
            $thread,
            'tickets',
            'ticket.rejected',
            'Chat ticket rejected',
            $reason !== '' ? $reason : 'Admin rejected the chat ticket request.',
            'provider.tickets.index',
            [],
            (int) $admin->id
        );

        $message = 'Chat ticket rejected.';

        if ($request->input('return_to') === 'chat') {
            return redirect()
                ->route('admin.chat.index', ['tab' => 'tickets', 'ticket' => $thread->id])
                ->with('success', $message);
        }

        return redirect()
            ->route('admin.tickets.index', ['status' => 'rejected', 'thread' => $thread->id])
            ->with('success', $message);
    }

    public function adminTicketEnd(Request $request, ChatThread $thread): RedirectResponse
    {
        $admin = $request->user();

        abort_unless($admin?->role === 'admin', 403);
        abort_unless($this->threadType($thread) === self::TYPE_PROVIDER_ADMIN, 404);
        abort_unless($this->threadChatApproved($thread), 404);

        $reason = $this->closeThread($request, $thread, $admin, 'Chat ended by admin.', 'admin');

        $this->notifyProviderParticipants(
            $thread,
            'tickets',
            'ticket.closed',
            'Chat ended by admin',
            $reason.' Submit a new ticket to reopen chat.',
            'provider.tickets.index',
            [],
            (int) $admin->id
        );

        return redirect()
            ->route('admin.tickets.index', ['status' => 'closed', 'thread' => $thread->id])
            ->with('success', 'Chat ended.');
    }

    public function providerInternalTicketApprove(Request $request, ChatThread $thread): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->role === 'provider' && ProviderMenuAccess::isProviderOwner($user), 403);
        abort_unless($this->threadType($thread) === self::TYPE_PROVIDER_BRANCH, 404);
        abort_unless((int) $thread->provider_id === (int) $user->id, 403);
        abort_unless(in_array($thread->ticket_status, ['pending', 'rejected'], true), 404);

        $thread->forceFill([
            'ticket_status' => 'approved',
            'ticket_rejection_reason' => null,
            'ticket_reviewed_at' => now(),
            'ticket_reviewed_by' => $user->id,
            'last_provider_read_at' => now(),
            'closed_by_user_id' => null,
            'closed_at' => null,
            'status' => 'open',
        ])->save();

        if ($thread->branchUser) {
            $this->notificationService()->createForUser(
                $thread->branchUser,
                'ticket.internal.approved',
                'Internal chat ticket approved',
                'Main provider approved the internal chat ticket. Chat is now available.',
                provider_route('provider.chat.index', ['thread' => $thread->id], true, true),
                [
                    'thread_id' => (int) $thread->id,
                    'provider_id' => (int) $thread->provider_id,
                ]
            );
        }

        return provider_route_redirect('provider.tickets.index', ['thread' => $thread->id])
            ->with('success', 'Internal ticket approved. Main-to-branch chat is now open.');
    }

    public function providerInternalTicketReject(Request $request, ChatThread $thread): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->role === 'provider' && ProviderMenuAccess::isProviderOwner($user), 403);
        abort_unless($this->threadType($thread) === self::TYPE_PROVIDER_BRANCH, 404);
        abort_unless((int) $thread->provider_id === (int) $user->id, 403);
        abort_unless($thread->ticket_status === 'pending', 404);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = trim((string) ($validated['reason'] ?? ''));

        $thread->forceFill([
            'ticket_status' => 'rejected',
            'ticket_rejection_reason' => $reason,
            'ticket_reviewed_at' => now(),
            'ticket_reviewed_by' => $user->id,
        ])->save();

        if ($thread->branchUser) {
            $this->notificationService()->createForUser(
                $thread->branchUser,
                'ticket.internal.rejected',
                'Internal chat ticket rejected',
                $reason !== '' ? $reason : 'Main provider rejected the internal chat request.',
                provider_route('provider.tickets.index', [], true, true),
                [
                    'thread_id' => (int) $thread->id,
                    'provider_id' => (int) $thread->provider_id,
                ]
            );
        }

        return provider_route_redirect('provider.tickets.index', ['thread' => $thread->id])
            ->with('success', 'Internal ticket rejected.');
    }

    public function providerTicketEnd(Request $request, ChatThread $thread): RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->providerCanEndThread($user, $thread), 403);
        abort_unless($this->threadChatApproved($thread), 404);

        $reason = $this->closeThread($request, $thread, $user, 'Chat ended by main provider.', 'provider_owner');

        if ($thread->branchUser) {
            $this->notificationService()->createForUser(
                $thread->branchUser,
                'ticket.internal.closed',
                'Chat ended by main provider',
                $reason.' Submit a new ticket to reopen chat.',
                provider_route('provider.tickets.index', [], true, true),
                [
                    'thread_id' => (int) $thread->id,
                    'provider_id' => (int) $thread->provider_id,
                ]
            );
        }

        return provider_route_redirect('provider.tickets.index', ['thread' => $thread->id])
            ->with('success', 'Internal chat ended. The branch must submit a new ticket to chat again.');
    }

    private function providerOwners(string $search = ''): Collection
    {
        return User::query()
            ->with('providerProfile')
            ->where('role', 'provider')
            ->whereNull('provider_id')
            ->whereNull('provider_role_id')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhereHas('providerProfile', function ($profileQuery) use ($search) {
                            $profileQuery->where('phone_number', 'like', "%{$search}%")
                                ->orWhere('category', 'like', "%{$search}%");
                        })
                        ->orWhereHas('branchAccounts', function ($branchQuery) use ($search) {
                            $branchQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('name')
            ->get();
    }

    private function branchAccounts(int $providerId): Collection
    {
        return User::query()
            ->with('providerBranch')
            ->where('role', 'provider')
            ->where('provider_id', $providerId)
            ->where(function ($query) {
                $query->whereNotNull('provider_role_id')
                    ->orWhereNotNull('branch_id');
            })
            ->orderBy('name')
            ->get();
    }

    private function internalChatContacts(User $user): Collection
    {
        $providerId = ProviderMenuAccess::providerOwnerId($user);
        $owner = User::query()
            ->with('providerBranch')
            ->whereKey($providerId)
            ->where('role', 'provider')
            ->first();

        $branchAccounts = $this->branchAccounts($providerId);
        $contacts = ProviderMenuAccess::isProviderOwner($user)
            ? $branchAccounts
            : collect([$owner])->merge($branchAccounts);

        return $contacts
            ->filter(fn (?User $contact) => $contact
                && (int) $contact->id !== (int) $user->id
                && ProviderMenuAccess::userCanAccess($contact, 'chat'))
            ->unique(fn (User $contact) => (int) $contact->id)
            ->values();
    }

    private function activeProviderAdminThread(User $user): ChatThread
    {
        $providerId = ProviderMenuAccess::providerOwnerId($user);

        $thread = ChatThread::query()
            ->where('provider_id', $providerId)
            ->where('conversation_type', self::TYPE_PROVIDER_ADMIN)
            ->where('ticket_status', '!=', 'closed')
            ->where(function ($query) use ($user) {
                $query->where('provider_user_id', $user->id);

                if (ProviderMenuAccess::isProviderOwner($user)) {
                    $query->orWhereNull('provider_user_id');
                }
            })
            ->latest('updated_at')
            ->first();

        if ($thread) {
            return $thread;
        }

        return ChatThread::query()->create([
            'provider_id' => $providerId,
            'provider_user_id' => $user->id,
            'conversation_type' => self::TYPE_PROVIDER_ADMIN,
            'status' => 'open',
            'ticket_status' => 'none',
            'opened_by_user_id' => $user->id,
        ]);
    }

    private function activeBranchInternalThread(User $branchUser): ?ChatThread
    {
        if (ProviderMenuAccess::isProviderOwner($branchUser)) {
            return null;
        }

        $owner = User::query()->whereKey(ProviderMenuAccess::providerOwnerId($branchUser))->first();

        return $owner ? $this->ensureInternalThread(ProviderMenuAccess::providerOwnerId($branchUser), $branchUser, $owner) : null;
    }

    private function activeProviderBranchThread(int $providerId, int $branchUserId, ?int $openedByUserId = null): ChatThread
    {
        $owner = User::query()->whereKey($providerId)->first();
        $branchUser = User::query()->whereKey($branchUserId)->first();

        if ($owner && $branchUser) {
            return $this->ensureInternalThread($providerId, $owner, $branchUser);
        }

        $thread = ChatThread::query()
            ->where('provider_id', $providerId)
            ->where('conversation_type', self::TYPE_PROVIDER_BRANCH)
            ->where('branch_user_id', $branchUserId)
            ->where('ticket_status', '!=', 'closed')
            ->latest('updated_at')
            ->first();

        if ($thread) {
            return $thread;
        }

        return ChatThread::query()->create([
            'provider_id' => $providerId,
            'provider_user_id' => $providerId,
            'branch_user_id' => $branchUserId,
            'conversation_type' => self::TYPE_PROVIDER_BRANCH,
            'status' => 'open',
            'ticket_status' => 'none',
            'opened_by_user_id' => $openedByUserId ?: $branchUserId,
        ]);
    }

    private function ensureInternalThread(int $providerId, User $firstUser, User $secondUser): ChatThread
    {
        abort_if((int) $firstUser->id === (int) $secondUser->id, 422, 'Invalid chat contact.');
        abort_if(ProviderMenuAccess::providerOwnerId($firstUser) !== $providerId, 403);
        abort_if(ProviderMenuAccess::providerOwnerId($secondUser) !== $providerId, 403);

        [$slotAUserId, $slotBUserId] = $this->internalThreadPair($providerId, (int) $firstUser->id, (int) $secondUser->id);

        $thread = ChatThread::query()
            ->where('provider_id', $providerId)
            ->where('conversation_type', self::TYPE_PROVIDER_BRANCH)
            ->where('provider_user_id', $slotAUserId)
            ->where('branch_user_id', $slotBUserId)
            ->first();

        $payload = [
            'provider_user_id' => $slotAUserId,
            'branch_user_id' => $slotBUserId,
            'conversation_type' => self::TYPE_PROVIDER_BRANCH,
            'ticket_status' => 'approved',
            'ticket_subject' => 'Internal provider chat',
            'ticket_body' => 'Internal provider conversation.',
            'ticket_rejection_reason' => null,
            'ticket_requested_at' => $thread?->ticket_requested_at ?: now(),
            'ticket_reviewed_at' => $thread?->ticket_reviewed_at ?: now(),
            'ticket_reviewed_by' => $thread?->ticket_reviewed_by,
            'closed_by_user_id' => null,
            'closed_at' => null,
            'status' => 'open',
        ];

        if ($thread) {
            $thread->forceFill($payload)->save();

            return $thread->refresh()->load(['provider', 'providerUser.providerBranch', 'branchUser.providerBranch']);
        }

        return ChatThread::query()->create(array_merge($payload, [
            'provider_id' => $providerId,
            'opened_by_user_id' => $firstUser->id,
        ]))->load(['provider', 'providerUser.providerBranch', 'branchUser.providerBranch']);
    }

    private function internalThreadPair(int $providerId, int $firstUserId, int $secondUserId): array
    {
        if ($firstUserId === $providerId || $secondUserId === $providerId) {
            return [$providerId, $firstUserId === $providerId ? $secondUserId : $firstUserId];
        }

        $ids = [$firstUserId, $secondUserId];
        sort($ids);

        return $ids;
    }

    private function internalTicketsForOwner(int $providerId): Collection
    {
        return ChatThread::query()
            ->with(['provider', 'branchUser.providerBranch', 'ticketReviewer', 'lastMessage.sender'])
            ->where('provider_id', $providerId)
            ->where('conversation_type', self::TYPE_PROVIDER_BRANCH)
            ->whereIn('ticket_status', ['pending', 'approved', 'rejected', 'closed'])
            ->get()
            ->sortByDesc(fn (ChatThread $thread) => optional(
                $thread->ticket_requested_at ?: $thread->ticket_reviewed_at ?: $thread->last_message_at ?: $thread->updated_at
            )->timestamp ?: 0)
            ->values();
    }

    private function providerChatThreads(User $user): Collection
    {
        $providerId = ProviderMenuAccess::providerOwnerId($user);
        $isProviderOwner = ProviderMenuAccess::isProviderOwner($user);

        return ChatThread::query()
            ->with(['provider.providerProfile', 'providerUser.providerBranch', 'branchUser.providerBranch', 'lastMessage.sender'])
            ->where(function ($query) use ($user, $providerId, $isProviderOwner) {
                $query->where(function ($adminQuery) use ($user, $providerId, $isProviderOwner) {
                    $adminQuery->where('conversation_type', self::TYPE_PROVIDER_ADMIN)
                        ->where('provider_id', $providerId)
                        ->where('ticket_status', 'approved')
                        ->whereNull('closed_at')
                        ->where(function ($participantQuery) use ($user, $isProviderOwner) {
                            $participantQuery->where('provider_user_id', $user->id);

                            if ($isProviderOwner) {
                                $participantQuery->orWhereNull('provider_user_id');
                            }
                        });
                });

                $query->orWhere(function ($internalQuery) use ($user, $providerId) {
                    $internalQuery->where('conversation_type', self::TYPE_PROVIDER_BRANCH)
                        ->where('provider_id', $providerId)
                        ->where('ticket_status', 'approved')
                        ->where(function ($participantQuery) use ($user) {
                            $participantQuery->where('provider_user_id', $user->id)
                                ->orWhere('branch_user_id', $user->id);
                        });
                });
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('ticket_reviewed_at')
            ->orderByDesc('ticket_requested_at')
            ->orderByDesc('updated_at')
            ->limit(self::THREAD_LIST_LIMIT)
            ->get();
    }

    private function threadChatApproved(ChatThread $thread): bool
    {
        return $this->chatThreadAccess->threadChatApproved($thread);
    }

    private function messagesFor(ChatThread $thread, User $viewer): Collection
    {
        return $thread->messages()
            ->with('sender')
            ->latest('id')
            ->limit(self::MESSAGE_PAGE_LIMIT)
            ->get()
            ->sortBy('id')
            ->values()
            ->map(fn (ChatMessage $message) => ChatMessagePresenter::make($message, $viewer, $thread));
    }

    private function storeMessage(Request $request, ChatThread $thread, string $senderRole): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'extensions:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]);

        $user = $request->user();
        $body = trim((string) ($validated['body'] ?? ''));
        $image = $request->file('image');

        if ($body === '' && ! $image) {
            return response()->json([
                'message' => 'Message or image cannot be empty.',
            ], 422);
        }

        $attachment = null;

        if ($image) {
            $storedAttachment = $this->chatAttachments->stage($image, (int) $thread->id);
            $attachment = [
                'path' => $storedAttachment->path,
                'name' => $image->getClientOriginalName(),
                'mime' => $image->getMimeType(),
                'size' => $image->getSize(),
            ];
        }

        try {
            $message = DB::transaction(function () use ($thread, $user, $senderRole, $body, $attachment) {
                $payload = [
                    'sender_id' => $user->id,
                    'sender_role' => $senderRole,
                    'body' => $body,
                ];

                if ($attachment) {
                    $payload = array_merge($payload, [
                        'attachment_path' => $attachment['path'],
                        'attachment_name' => $attachment['name'],
                        'attachment_mime' => $attachment['mime'],
                        'attachment_size' => $attachment['size'],
                    ]);
                }

                $message = $thread->messages()->create($payload);

                $thread->forceFill([
                    'last_message_id' => $message->id,
                    'last_message_at' => $message->created_at,
                    $this->readColumnForRole($senderRole) => now(),
                ])->save();

                return $message;
            });
        } catch (\Throwable $error) {
            if ($attachment) {
                $this->chatAttachments->delete($attachment['path']);
            }

            throw $error;
        }

        $message->load('sender');

        try {
            broadcast(new ChatMessageSent($message))->toOthers();
        } catch (\Throwable) {
            // The sender should still receive a successful JSON response if realtime is offline.
        }

        try {
            $this->notifyChatRecipients($thread, $message, $senderRole, $user);
        } catch (\Throwable) {
            // Chat delivery is already persisted; notification failures must not block the composer.
        }

        return response()->json([
            'message' => ChatMessagePresenter::make($message, $user, $thread),
        ]);
    }

    private function messagesResponse(Request $request, ChatThread $thread, User $viewer): JsonResponse
    {
        $afterId = max(0, (int) $request->query('after_id', 0));

        $query = $thread->messages()->with('sender');

        $rawMessages = $afterId > 0
            ? $query
                ->where('id', '>', $afterId)
                ->oldest('id')
                ->limit(self::MESSAGE_PAGE_LIMIT)
                ->get()
            : $query
                ->latest('id')
                ->limit(self::MESSAGE_PAGE_LIMIT)
                ->get()
                ->sortBy('id')
                ->values();

        $messages = $rawMessages
            ->map(fn (ChatMessage $message) => ChatMessagePresenter::make($message, $viewer, $thread))
            ->values();

        return response()->json([
            'messages' => $messages,
            'thread' => $this->threadStatePayload($thread),
        ]);
    }

    private function threadStatePayload(ChatThread $thread): array
    {
        return [
            'id' => (int) $thread->id,
            'conversation_type' => $thread->conversation_type ?: self::TYPE_PROVIDER_ADMIN,
            'ticket_status' => $thread->ticket_status ?: 'none',
            'status' => $thread->status ?: 'open',
            'ticket_rejection_reason' => $thread->ticket_rejection_reason,
            'closed_at' => $thread->closed_at?->toIso8601String(),
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

    private function closeThread(Request $request, ChatThread $thread, User $closer, string $defaultReason, string $readerRole): string
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = trim((string) ($validated['reason'] ?? ''));
        $reason = $reason !== '' ? $reason : $defaultReason;

        $thread->forceFill([
            'ticket_status' => 'closed',
            'status' => 'closed',
            'ticket_rejection_reason' => $reason,
            'ticket_reviewed_at' => now(),
            'ticket_reviewed_by' => $closer->id,
            'closed_by_user_id' => $closer->id,
            'closed_at' => now(),
            $this->readColumnForRole($readerRole) => now(),
        ])->save();

        try {
            broadcast(new ChatThreadUpdated($thread));
        } catch (\Throwable) {
            // Polling will still pick up the closed state when realtime is unavailable.
        }

        return $reason;
    }

    private function notifyChatRecipients(ChatThread $thread, ChatMessage $message, string $senderRole, User $sender): void
    {
        $preview = $message->body !== ''
            ? Str::limit($message->body, 120)
            : ($message->attachment_path ? 'Sending image' : '');

        if ($this->threadType($thread) === self::TYPE_PROVIDER_ADMIN) {
            if ($senderRole === 'provider') {
                $this->notificationService()->createForUsers(
                    $this->notificationService()->adminRecipients(),
                    'chat.message',
                    'New message from '.($sender->name ?: 'Provider'),
                    $preview,
                    route('admin.chat.index', ['thread' => $thread->id]),
                    [
                        'thread_id' => (int) $thread->id,
                        'message_id' => (int) $message->id,
                        'provider_id' => (int) $thread->provider_id,
                    ],
                    (int) $sender->id
                );

                return;
            }

            $this->notifyProviderParticipants(
                $thread,
                'chat',
                'chat.message',
                'New message from admin',
                $preview,
                'provider.chat.index',
                ['thread' => $thread->id],
                (int) $sender->id,
                ['message_id' => (int) $message->id]
            );

            return;
        }

        $recipient = $senderRole === 'provider_owner' ? $thread->branchUser : $thread->providerUser;

        if (! $recipient || (int) $recipient->id === (int) $sender->id) {
            return;
        }

        $this->notificationService()->createForUser(
            $recipient,
            'chat.message',
            'New message from '.($sender->name ?: 'Provider'),
            $preview,
            provider_route('provider.chat.index', ['thread' => $thread->id], true, ! ProviderMenuAccess::isProviderOwner($recipient)),
            [
                'thread_id' => (int) $thread->id,
                'message_id' => (int) $message->id,
                'provider_id' => (int) $thread->provider_id,
            ]
        );
    }

    private function notifyProviderParticipants(
        ChatThread $thread,
        string $menuKey,
        string $type,
        string $title,
        string $body,
        string $routeName,
        array $routeParams = [],
        ?int $exceptUserId = null,
        array $extraData = []
    ): void {
        $this->providerParticipants($thread, $menuKey)
            ->filter(fn (User $user) => ! $exceptUserId || (int) $user->id !== (int) $exceptUserId)
            ->each(function (User $user) use ($thread, $type, $title, $body, $routeName, $routeParams, $extraData) {
                $this->notificationService()->createForUser(
                    $user,
                    $type,
                    $title,
                    $body,
                    provider_route($routeName, $routeParams, true, ! ProviderMenuAccess::isProviderOwner($user)),
                    array_merge([
                        'thread_id' => (int) $thread->id,
                        'provider_id' => (int) $thread->provider_id,
                    ], $extraData)
                );
            });
    }

    private function providerParticipants(ChatThread $thread, ?string $menuKey = null): Collection
    {
        if ($this->threadType($thread) === self::TYPE_PROVIDER_BRANCH) {
            return collect([$thread->providerUser ?: $thread->provider, $thread->branchUser])
                ->filter(fn (?User $user) => $user && ProviderMenuAccess::userCanAccess($user, $menuKey))
                ->unique(fn (User $user) => (int) $user->id)
                ->values();
        }

        $participant = $thread->providerUser ?: $thread->provider;

        return collect([$participant])
            ->filter(fn (?User $user) => $user && ProviderMenuAccess::userCanAccess($user, $menuKey))
            ->unique(fn (User $user) => (int) $user->id)
            ->values();
    }

    private function notificationService(): AppNotificationService
    {
        return app(AppNotificationService::class);
    }

    private function applyUnreadCounts(Collection $threads, callable|string $readerRoleResolver): void
    {
        $threads = $threads
            ->filter(fn ($thread) => $thread instanceof ChatThread)
            ->unique('id')
            ->values();

        if ($threads->isEmpty()) {
            return;
        }

        $counts = ChatMessage::query()
            ->select('chat_thread_id', DB::raw('COUNT(*) as aggregate'))
            ->whereIn('chat_thread_id', $threads->pluck('id')->all())
            ->where(function ($query) use ($threads, $readerRoleResolver) {
                foreach ($threads as $thread) {
                    $readerRole = is_string($readerRoleResolver)
                        ? $readerRoleResolver
                        : (string) $readerRoleResolver($thread);
                    $readAt = $thread->{$this->readColumnForRole($readerRole)};
                    $oppositeRoles = $this->oppositeSenderRoles($thread, $readerRole);

                    $query->orWhere(function ($threadQuery) use ($thread, $oppositeRoles, $readAt) {
                        $threadQuery
                            ->where('chat_thread_id', $thread->id)
                            ->whereIn('sender_role', $oppositeRoles)
                            ->when($readAt, fn ($dateQuery) => $dateQuery->where('created_at', '>', $readAt));
                    });
                }
            })
            ->groupBy('chat_thread_id')
            ->pluck('aggregate', 'chat_thread_id');

        $threads->each(function (ChatThread $thread) use ($counts) {
            $thread->setAttribute('unread_count', (int) ($counts[$thread->id] ?? 0));
        });
    }

    private function markThreadRead(ChatThread $thread, string $readerRole): void
    {
        $column = $this->readColumnForRole($readerRole);
        $now = now();

        ChatThread::query()
            ->whereKey($thread->getKey())
            ->update([
                $column => $now,
                'updated_at' => $now,
            ]);

        $thread->setAttribute($column, $now);
        $thread->setAttribute('updated_at', $now);
        $thread->syncOriginalAttributes([$column, 'updated_at']);
        ChatUnreadCounter::forgetForUser(request()->user());
    }

    private function providerCanChatThread(?User $user, ChatThread $thread): bool
    {
        return $this->chatThreadAccess->providerCanChatThread($user, $thread);
    }

    private function providerCanEndThread(?User $user, ChatThread $thread): bool
    {
        return $this->chatThreadAccess->providerCanEndThread($user, $thread);
    }

    private function providerCanAccessThread(User $user, ChatThread $thread): bool
    {
        return $this->chatThreadAccess->providerCanAccessThread($user, $thread);
    }

    private function readerRoleForUser(ChatThread $thread, User $user): string
    {
        return $this->chatThreadAccess->readerRoleForUser($thread, $user);
    }

    private function senderRoleForUser(ChatThread $thread, User $user): string
    {
        return $this->chatThreadAccess->senderRoleForUser($thread, $user);
    }

    private function readColumnForRole(string $role): string
    {
        return $this->chatThreadAccess->readColumnForRole($role);
    }

    private function oppositeSenderRoles(ChatThread $thread, string $readerRole): array
    {
        return $this->chatThreadAccess->oppositeSenderRoles($thread, $readerRole);
    }

    private function threadType(ChatThread $thread): string
    {
        return $this->chatThreadAccess->threadType($thread);
    }

    private function chatConfig(User $user, ?ChatThread $activeThread, array $threadIds, ?string $sendUrl, ?string $readUrl, ?string $messagesUrl = null): array
    {
        $connection = config('broadcasting.connections.reverb', []);
        $options = $connection['public_options'] ?? ($connection['options'] ?? []);
        $scheme = (string) ($options['scheme'] ?? 'http');
        $host = (string) ($options['host'] ?? request()->getHost());

        return [
            'currentUserId' => (int) $user->id,
            'activeThreadId' => $activeThread ? (int) $activeThread->id : null,
            'conversationType' => $activeThread ? $this->threadType($activeThread) : null,
            'threadIds' => array_values(array_unique($threadIds)),
            'sendUrl' => $sendUrl,
            'readUrl' => $readUrl,
            'messagesUrl' => $messagesUrl,
            'readReceipts' => $activeThread ? $this->threadStatePayload($activeThread)['read_receipts'] : [],
            'csrfToken' => csrf_token(),
            'authEndpoint' => url('/broadcasting/auth'),
            'closedMessage' => 'Chat has ended. Submit a new ticket to open the next chat session.',
            'closedRedirectUrl' => $user->role === 'provider'
                ? provider_route('provider.chat.index', ['list' => 1], true, ! ProviderMenuAccess::isProviderOwner($user))
                : null,
            'chatNotificationPollUrl' => $user->role === 'admin'
                ? route('admin.notifications.index', ['include_chat' => 1])
                : provider_route('provider.notifications.index', ['include_chat' => 1], true, ! ProviderMenuAccess::isProviderOwner($user)),
            'latestChatNotificationId' => (int) AppNotification::query()
                ->where('user_id', $user->id)
                ->where('type', 'chat.message')
                ->max('id'),
            'broadcast' => [
                'key' => (string) ($connection['key'] ?? ''),
                'host' => $host !== '' ? $host : request()->getHost(),
                'port' => (int) ($options['port'] ?? 8080),
                'scheme' => $scheme,
            ],
        ];
    }
}
