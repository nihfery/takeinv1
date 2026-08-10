<?php

namespace Tests\Feature\Media;

use App\Modules\Chat\Infrastructure\Persistence\Models\ChatMessage;
use App\Modules\Chat\Infrastructure\Persistence\Models\ChatThread;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Media\Application\Services\MediaReadResolver;
use App\Modules\Media\Infrastructure\Persistence\Models\MediaMigrationEntry;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyMediaMigrationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('provider_documents');
        Storage::fake('media_private');
        config([
            'filesystems.media.legacy_public_disk' => 'public',
            'filesystems.media.legacy_archive_disk' => 'media_private',
            'filesystems.media.legacy_archive_prefix' => 'legacy-retirement',
            'filesystems.media.legacy_retirement_enabled' => false,
            'filesystems.media.legacy_retirement_min_age_days' => 30,
            'filesystems.media.chat_attachments_disk' => 'media_private',
            'filesystems.provider_documents_disk' => 'provider_documents',
        ]);
    }

    public function test_subject_id_is_rejected_for_the_ambiguous_all_scope(): void
    {
        $this->artisan('media:migrate-legacy', [
            '--scope' => 'all',
            '--id' => 1,
        ])->assertExitCode(2);

        $this->assertDatabaseCount('media_migration_entries', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('provider_documents')->allFiles());
        $this->assertSame([], Storage::disk('media_private')->allFiles());
    }

    public function test_provider_document_migration_is_dry_run_by_default_then_requires_verified_copy_before_cutover(): void
    {
        $provider = User::factory()->create(['role' => 'provider']);
        $profile = ProviderProfile::query()->create([
            'user_id' => $provider->id,
            'status' => 'active',
            'document_status' => 'verified',
            'ktp_image' => 'provider/documents/legacy-ktp.jpg',
        ]);
        Storage::disk('public')->put($profile->ktp_image, 'legacy-private-identity');

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
        ])->assertSuccessful();

        $this->assertDatabaseCount('media_migration_entries', 0);
        $this->assertSame('provider/documents/legacy-ktp.jpg', $profile->refresh()->ktp_image);
        $this->assertSame([], Storage::disk('provider_documents')->allFiles());

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'copy',
            '--execute' => true,
        ])->assertSuccessful();

        $entry = MediaMigrationEntry::query()->sole();
        $this->assertSame(MediaMigrationEntry::STATUS_VERIFIED, $entry->status);
        $this->assertSame(hash('sha256', 'legacy-private-identity'), $entry->source_checksum);
        $this->assertSame($entry->source_checksum, $entry->target_checksum);
        $this->assertSame($entry->source_path, $profile->refresh()->ktp_image);
        Storage::disk('public')->assertExists($entry->source_path);
        Storage::disk('provider_documents')->assertExists($entry->target_path);

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'cutover',
        ])->assertSuccessful();
        $this->assertSame($entry->source_path, $profile->refresh()->ktp_image);

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'cutover',
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertSame($entry->target_path, $profile->refresh()->ktp_image);
        $this->assertSame(MediaMigrationEntry::STATUS_CUTOVER, $entry->refresh()->status);
        Storage::disk('public')->assertExists($entry->source_path);

        Storage::disk('provider_documents')->delete($entry->target_path);
        $fallback = app(MediaReadResolver::class)->resolve(
            'provider_documents',
            $entry->target_path,
            ['public'],
        );
        $this->assertSame('public', $fallback?->disk);
        $this->assertSame($entry->source_path, $fallback?->path);
        Storage::disk('provider_documents')->put($entry->target_path, 'legacy-private-identity');

        // Re-running both pointer stages is idempotent and never removes either copy.
        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'cutover',
            '--execute' => true,
        ])->assertSuccessful();

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'rollback',
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertSame($entry->source_path, $profile->refresh()->ktp_image);
        $this->assertSame(MediaMigrationEntry::STATUS_ROLLED_BACK, $entry->refresh()->status);
        $this->assertSame(1, $entry->rollback_count);
        Storage::disk('public')->assertExists($entry->source_path);
        Storage::disk('provider_documents')->assertExists($entry->target_path);
    }

    public function test_cutover_refuses_a_target_changed_after_verification(): void
    {
        $provider = User::factory()->create(['role' => 'provider']);
        $profile = ProviderProfile::query()->create([
            'user_id' => $provider->id,
            'status' => 'active',
            'document_status' => 'verified',
            'nib_document' => 'provider/documents/legacy-nib.pdf',
        ]);
        Storage::disk('public')->put($profile->nib_document, '%PDF-1.4 verified source');

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--execute' => true,
        ])->assertSuccessful();

        $entry = MediaMigrationEntry::query()->sole();
        Storage::disk('provider_documents')->put($entry->target_path, 'tampered target');

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'cutover',
            '--execute' => true,
        ])->assertFailed();

        $this->assertSame($entry->source_path, $profile->refresh()->nib_document);
        $this->assertNotNull($entry->refresh()->error_message);
        Storage::disk('public')->assertExists($entry->source_path);
    }

    public function test_retirement_execute_is_hard_disabled_by_default(): void
    {
        [$profile, $entry] = $this->cutOverProviderDocument(
            'ktp_image',
            'provider/documents/disabled-retirement.jpg',
            'disabled-retirement-source',
        );

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'retire',
            '--execute' => true,
        ])->assertFailed();

        $this->assertSame($entry->target_path, $profile->refresh()->ktp_image);
        $this->assertNull($entry->refresh()->archive_path);
        $this->assertNull($entry->source_retired_at);
        $this->assertSame(0, $entry->retirement_count);
        Storage::disk('public')->assertExists($entry->source_path);
    }

    public function test_retirement_rejects_a_cutover_younger_than_the_configured_minimum_age(): void
    {
        config([
            'filesystems.media.legacy_retirement_enabled' => true,
            'filesystems.media.legacy_retirement_min_age_days' => 30,
        ]);
        [$profile, $entry] = $this->cutOverProviderDocument(
            'nib_document',
            'provider/documents/young-retirement.pdf',
            '%PDF young-retirement-source',
        );

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'retire',
            '--execute' => true,
        ])->assertFailed();

        $this->assertSame($entry->target_path, $profile->refresh()->nib_document);
        $this->assertNull($entry->refresh()->archive_path);
        $this->assertNull($entry->source_retired_at);
        $this->assertNotNull($entry->error_message);
        Storage::disk('public')->assertExists($entry->source_path);
    }

    public function test_retirement_is_dry_run_then_archives_and_rollback_restores_the_public_source(): void
    {
        config(['filesystems.media.legacy_retirement_enabled' => true]);
        $provider = User::factory()->create(['role' => 'provider']);
        $profile = ProviderProfile::query()->create([
            'user_id' => $provider->id,
            'status' => 'active',
            'document_status' => 'verified',
            'ktp_image' => 'provider/documents/retire-legacy-ktp.jpg',
        ]);
        Storage::disk('public')->put($profile->ktp_image, 'retirement-source');

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'copy',
            '--execute' => true,
        ])->assertSuccessful();
        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'cutover',
            '--execute' => true,
        ])->assertSuccessful();

        $entry = MediaMigrationEntry::query()->sole();
        $entry->forceFill(['cutover_at' => now()->subDays(31)])->save();

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'retire',
        ])->assertSuccessful();

        Storage::disk('public')->assertExists($entry->source_path);
        $this->assertNull($entry->refresh()->archive_path);
        $this->assertSame([], Storage::disk('media_private')->allFiles());

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'retire',
            '--execute' => true,
        ])->assertSuccessful();

        $entry->refresh();
        $this->assertSame(MediaMigrationEntry::STATUS_CUTOVER, $entry->status);
        $this->assertSame('media_private', $entry->archive_disk);
        $this->assertStringStartsWith('legacy-retirement/provider-documents/', $entry->archive_path);
        $this->assertSame($entry->source_checksum, $entry->archive_checksum);
        $this->assertNotNull($entry->archive_verified_at);
        $this->assertNotNull($entry->source_retired_at);
        $this->assertSame(1, $entry->retirement_count);
        Storage::disk('public')->assertMissing($entry->source_path);
        Storage::disk('media_private')->assertExists($entry->archive_path);
        $this->assertSame('retirement-source', Storage::disk('media_private')->get($entry->archive_path));

        // A later mass/single cutover replay remains a verified no-op even
        // though the deliberately retired public source no longer exists.
        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'cutover',
            '--execute' => true,
        ])->assertSuccessful();
        Storage::disk('public')->assertMissing($entry->source_path);

        // Retirement is resumable and does not create or count another deletion.
        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'retire',
            '--execute' => true,
        ])->assertSuccessful();
        $this->assertSame(1, $entry->refresh()->retirement_count);

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'rollback',
            '--execute' => true,
        ])->assertSuccessful();

        $entry->refresh();
        $this->assertSame(MediaMigrationEntry::STATUS_ROLLED_BACK, $entry->status);
        $this->assertSame($entry->source_path, $profile->refresh()->ktp_image);
        $this->assertNull($entry->source_retired_at);
        $this->assertNotNull($entry->source_restored_at);
        $this->assertSame(1, $entry->rollback_count);
        Storage::disk('public')->assertExists($entry->source_path);
        Storage::disk('provider_documents')->assertExists($entry->target_path);
        Storage::disk('media_private')->assertExists($entry->archive_path);
        $this->assertSame('retirement-source', Storage::disk('public')->get($entry->source_path));
    }

    public function test_retirement_and_restore_fail_closed_when_target_or_archive_is_tampered(): void
    {
        config(['filesystems.media.legacy_retirement_enabled' => true]);
        $provider = User::factory()->create(['role' => 'provider']);
        $profile = ProviderProfile::query()->create([
            'user_id' => $provider->id,
            'status' => 'active',
            'document_status' => 'verified',
            'nib_document' => 'provider/documents/retire-legacy-nib.pdf',
        ]);
        Storage::disk('public')->put($profile->nib_document, '%PDF retirement source');

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'copy',
            '--execute' => true,
        ])->assertSuccessful();
        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'cutover',
            '--execute' => true,
        ])->assertSuccessful();

        $entry = MediaMigrationEntry::query()->sole();
        $entry->forceFill(['cutover_at' => now()->subDays(31)])->save();
        Storage::disk('provider_documents')->put($entry->target_path, 'tampered target');

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'retire',
            '--execute' => true,
        ])->assertFailed();

        Storage::disk('public')->assertExists($entry->source_path);
        $this->assertNull($entry->refresh()->archive_path);

        Storage::disk('provider_documents')->put($entry->target_path, '%PDF retirement source');
        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'retire',
            '--execute' => true,
        ])->assertSuccessful();

        $entry->refresh();
        Storage::disk('media_private')->put($entry->archive_path, 'tampered archive');

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'rollback',
            '--execute' => true,
        ])->assertFailed();

        Storage::disk('public')->assertMissing($entry->source_path);
        $this->assertSame($entry->target_path, $profile->refresh()->nib_document);
        $this->assertSame(MediaMigrationEntry::STATUS_CUTOVER, $entry->refresh()->status);
        $this->assertNotNull($entry->error_message);
    }

    public function test_legacy_chat_attachment_can_be_copied_to_private_storage_and_cut_over(): void
    {
        config(['filesystems.media.legacy_retirement_enabled' => true]);
        $provider = User::factory()->create(['role' => 'provider']);
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
            'attachment_path' => 'chat-images/legacy.png',
            'attachment_name' => 'legacy.png',
            'attachment_mime' => 'image/png',
            'attachment_size' => 12,
        ]);
        Storage::disk('public')->put($message->attachment_path, 'legacy-chat');

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'chat-attachments',
            '--id' => $message->id,
            '--execute' => true,
        ])->assertSuccessful();
        $this->artisan('media:migrate-legacy', [
            '--scope' => 'chat-attachments',
            '--id' => $message->id,
            '--stage' => 'cutover',
            '--execute' => true,
        ])->assertSuccessful();

        $entry = MediaMigrationEntry::query()->sole();
        $entry->forceFill(['cutover_at' => now()->subDays(31)])->save();
        $this->assertSame($entry->target_path, $message->refresh()->attachment_path);
        Storage::disk('media_private')->assertExists($entry->target_path);
        Storage::disk('public')->assertExists($entry->source_path);

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'chat-attachments',
            '--id' => $message->id,
            '--stage' => 'retire',
            '--execute' => true,
        ])->assertSuccessful();
        $entry->refresh();
        Storage::disk('public')->assertMissing($entry->source_path);
        Storage::disk('media_private')->assertExists($entry->archive_path);

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'chat-attachments',
            '--id' => $message->id,
            '--stage' => 'rollback',
            '--execute' => true,
        ])->assertSuccessful();
        $this->assertSame($entry->source_path, $message->refresh()->attachment_path);
        Storage::disk('public')->assertExists($entry->source_path);
        Storage::disk('media_private')->assertExists($entry->target_path);
        Storage::disk('media_private')->assertExists($entry->archive_path);
    }

    /** @return array{ProviderProfile, MediaMigrationEntry} */
    private function cutOverProviderDocument(string $field, string $path, string $contents): array
    {
        $provider = User::factory()->create(['role' => 'provider']);
        $profile = ProviderProfile::query()->create([
            'user_id' => $provider->id,
            'status' => 'active',
            'document_status' => 'verified',
            $field => $path,
        ]);
        Storage::disk('public')->put($path, $contents);

        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'copy',
            '--execute' => true,
        ])->assertSuccessful();
        $this->artisan('media:migrate-legacy', [
            '--scope' => 'provider-documents',
            '--id' => $profile->id,
            '--stage' => 'cutover',
            '--execute' => true,
        ])->assertSuccessful();

        return [$profile, MediaMigrationEntry::query()->sole()];
    }
}
