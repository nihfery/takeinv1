<?php

namespace App\Modules\Media\Application\Services;

use App\Modules\Chat\Infrastructure\Persistence\Models\ChatMessage;
use App\Modules\Media\Application\Contracts\MediaStorage;
use App\Modules\Media\Application\Data\StoredMedia;
use App\Modules\Media\Domain\MediaVisibility;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ChatAttachmentStorage
{
    public function __construct(
        private MediaStorage $media,
        private MediaReadResolver $readResolver,
    ) {}

    public function stage(UploadedFile $file, int $threadId): StoredMedia
    {
        return $this->media->storeUploadedFile(
            $file,
            "support/chat/{$threadId}",
            MediaVisibility::Private,
            $this->privateDisk(),
        );
    }

    public function delete(?string $path): void
    {
        if ($path && $this->media->exists($this->privateDisk(), $path)) {
            $this->media->delete($this->privateDisk(), $path);
        }
    }

    public function temporaryUrl(ChatMessage $message): string
    {
        return URL::temporarySignedRoute(
            'chat.attachments.show',
            now()->addMinutes($this->urlLifetimeMinutes()),
            ['message' => $message->getKey()],
            absolute: false,
        );
    }

    public function response(ChatMessage $message): StreamedResponse
    {
        abort_unless(is_string($message->attachment_path) && $message->attachment_path !== '', 404);

        $location = $this->readResolver->resolve(
            $this->privateDisk(),
            $message->attachment_path,
            [$this->legacyPublicDisk()],
        );

        abort_if(! $location, 404);

        return $this->media->response(
            $location->disk,
            $location->path,
            $this->safeDownloadName($message),
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'Referrer-Policy' => 'no-referrer',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
            ],
            'inline',
        );
    }

    private function privateDisk(): string
    {
        $disk = (string) config('filesystems.media.chat_attachments_disk', 'media_private');
        $this->media->assertDiskVisibility($disk, MediaVisibility::Private);

        return $disk;
    }

    private function legacyPublicDisk(): string
    {
        return (string) config('filesystems.media.legacy_public_disk', 'public');
    }

    private function urlLifetimeMinutes(): int
    {
        return min(15, max(1, (int) config('filesystems.media.private_url_lifetime', 10)));
    }

    private function safeDownloadName(ChatMessage $message): string
    {
        $name = basename(str_replace('\\', '/', (string) $message->attachment_name));
        $name = preg_replace('/[\x00-\x1F\x7F"]/u', '', $name) ?: '';

        return $name !== '' ? $name : 'chat-attachment';
    }
}
