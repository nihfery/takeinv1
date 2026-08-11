<?php

namespace Tests\Feature\Security;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Chat\Infrastructure\Persistence\Models\ChatMessage;
use App\Modules\Chat\Infrastructure\Persistence\Models\ChatThread;
use App\Modules\Chat\Presentation\Support\ChatMessagePresenter;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Media\Application\Services\ChatAttachmentStorage;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateChatAttachmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('media_private');
        config([
            'filesystems.media.chat_attachments_disk' => 'media_private',
            'filesystems.media.legacy_public_disk' => 'public',
            'filesystems.media.private_url_lifetime' => 10,
        ]);
    }

    public function test_new_chat_attachment_is_staged_privately_with_a_generated_key(): void
    {
        $stored = app(ChatAttachmentStorage::class)->stage(
            UploadedFile::fake()->createWithContent('customer screenshot.png', 'private-image'),
            73,
        );

        $this->assertSame('media_private', $stored->disk);
        $this->assertStringStartsWith('support/chat/73/', $stored->path);
        $this->assertStringNotContainsString('customer screenshot', $stored->path);
        Storage::disk('media_private')->assertExists($stored->path);
        Storage::disk('public')->assertMissing($stored->path);
    }

    public function test_relative_signed_url_works_on_admin_and_provider_hosts_but_remains_actor_authorized(): void
    {
        $provider = $this->verifiedProvider();
        $admin = User::factory()->create(['role' => 'admin']);
        $thread = ChatThread::query()->create([
            'provider_id' => $provider->id,
            'conversation_type' => 'provider_admin',
            'ticket_status' => 'approved',
            'status' => 'open',
        ]);
        $message = ChatMessage::query()->create([
            'chat_thread_id' => $thread->id,
            'sender_id' => $provider->id,
            'sender_role' => 'provider',
            'body' => '',
            'attachment_path' => 'chat-images/legacy-private.png',
            'attachment_name' => 'private.png',
            'attachment_mime' => 'image/png',
            'attachment_size' => 13,
        ]);
        Storage::disk('public')->put($message->attachment_path, 'legacy-private');

        $presentedAttachment = ChatMessagePresenter::make($message, null, $thread)['attachment'];
        $url = $presentedAttachment['url'];

        $this->assertArrayNotHasKey('path', $presentedAttachment);
        $this->assertStringNotContainsString('chat-images/legacy-private.png', json_encode($presentedAttachment));
        $this->assertStringStartsWith('/private/chat-attachments/', $url);
        $this->assertStringNotContainsString('://', $url);
        $this->assertStringNotContainsString('/storage/', $url);

        $this->actingAs($admin, 'admin')
            ->withHeader('Host', 'admin.example.test')
            ->get($url)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        Auth::guard('admin')->logout();

        $this->actingAs($provider, 'provider')
            ->withHeader('Host', 'provider.example.test')
            ->get($url)
            ->assertOk();

        $accessEvents = AuditLog::query()
            ->where('action', 'chat.attachment.accessed')
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $accessEvents);
        $this->assertSame(['channel' => 'admin-web', 'thread_id' => $thread->id], Arr::sortRecursive($accessEvents[0]->after));
        $this->assertSame(['channel' => 'provider-web', 'thread_id' => $thread->id], Arr::sortRecursive($accessEvents[1]->after));
        $this->assertStringNotContainsString('chat-images/', $accessEvents->toJson());
        $this->assertStringNotContainsString('private.png', $accessEvents->toJson());

        $unsignedPath = (string) parse_url($url, PHP_URL_PATH);
        $this->withHeader('Host', 'provider.example.test')->get($unsignedPath)->assertForbidden();

        $foreign = $this->verifiedProvider();
        Auth::guard('provider')->logout();
        $this->actingAs($foreign, 'provider')
            ->withHeader('Host', 'provider.example.test')
            ->get($url)
            ->assertForbidden();
    }

    public function test_inactive_or_unverified_provider_cannot_use_a_valid_attachment_signature(): void
    {
        $provider = $this->verifiedProvider();
        $thread = ChatThread::query()->create([
            'provider_id' => $provider->id,
            'conversation_type' => 'provider_admin',
            'ticket_status' => 'approved',
            'status' => 'open',
        ]);
        $message = ChatMessage::query()->create([
            'chat_thread_id' => $thread->id,
            'sender_id' => $provider->id,
            'sender_role' => 'provider',
            'body' => '',
            'attachment_path' => 'chat-images/private.png',
            'attachment_name' => 'private.png',
            'attachment_mime' => 'image/png',
            'attachment_size' => 7,
        ]);
        Storage::disk('public')->put($message->attachment_path, 'private');
        $url = app(ChatAttachmentStorage::class)->temporaryUrl($message);

        $provider->providerProfile()->update(['document_status' => 'pending']);

        $this->actingAs($provider, 'provider')->get($url)->assertForbidden();
    }

    private function verifiedProvider(): User
    {
        $provider = User::factory()->create(['role' => 'provider']);

        ProviderProfile::query()->create([
            'user_id' => $provider->id,
            'status' => 'active',
            'document_status' => 'verified',
        ]);

        return $provider;
    }
}
