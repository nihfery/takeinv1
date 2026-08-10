<?php

namespace App\Modules\Media\Presentation\Web;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\Actions\RecordAuditEvent;
use App\Modules\Chat\Application\Services\ChatThreadAccessService;
use App\Modules\Chat\Infrastructure\Persistence\Models\ChatMessage;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Media\Application\Services\ChatAttachmentStorage;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ChatAttachmentController extends Controller
{
    public function __construct(
        private readonly ChatThreadAccessService $threadAccess,
        private readonly ChatAttachmentStorage $attachments,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function __invoke(Request $request, ChatMessage $message): StreamedResponse
    {
        abort_unless($request->hasValidRelativeSignature(), 403);

        $message->loadMissing('thread');
        $thread = $message->thread;

        abort_if(! $thread || ! $message->attachment_path, 404);

        $admin = Auth::guard('admin')->user();

        if ($admin instanceof User && $admin->role === 'admin') {
            abort_unless(
                $this->threadAccess->threadType($thread) === 'provider_admin'
                && in_array($thread->ticket_status, ['approved', 'closed'], true),
                403,
            );

            return $this->authorizedResponse($message, $admin, 'admin-web');
        }

        $provider = Auth::guard('provider')->user() ?: Auth::guard('provider_branch')->user();
        $profile = $provider instanceof User ? ProviderMenuAccess::providerProfile($provider) : null;

        abort_unless(
            $provider instanceof User
            && $provider->role === 'provider'
            && $profile?->status === 'active'
            && $profile->document_status === 'verified'
            && ProviderMenuAccess::userCanAccess($provider, 'chat')
            && $this->threadAccess->providerCanAccessThread($provider, $thread)
            && in_array($thread->ticket_status, ['approved', 'closed'], true),
            403,
        );

        return $this->authorizedResponse($message, $provider, 'provider-web');
    }

    private function authorizedResponse(ChatMessage $message, User $actor, string $channel): StreamedResponse
    {
        $response = $this->attachments->response($message);
        $thread = $message->thread;

        $this->audit->execute(
            'chat.attachment.accessed',
            ChatMessage::class,
            $message->id,
            after: [
                'thread_id' => $thread?->id,
                'channel' => $channel,
            ],
            actor: $actor,
            providerId: $thread?->provider_id ? (int) $thread->provider_id : null,
            branchId: $actor->branch_id ? (int) $actor->branch_id : null,
        );

        return $response;
    }
}
