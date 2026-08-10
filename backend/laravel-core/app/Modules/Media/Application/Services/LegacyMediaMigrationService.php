<?php

namespace App\Modules\Media\Application\Services;

use App\Modules\Chat\Infrastructure\Persistence\Models\ChatMessage;
use App\Modules\Media\Application\Contracts\MediaStorage;
use App\Modules\Media\Application\Data\LegacyMediaCandidate;
use App\Modules\Media\Domain\MediaVisibility;
use App\Modules\Media\Infrastructure\Persistence\Models\MediaMigrationEntry;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Throwable;

final readonly class LegacyMediaMigrationService
{
    public const SCOPE_ALL = 'all';

    public const SCOPE_PROVIDER_DOCUMENTS = 'provider-documents';

    public const SCOPE_CHAT_ATTACHMENTS = 'chat-attachments';

    private const SUBJECT_PROVIDER_PROFILE = 'provider_profile';

    private const SUBJECT_CHAT_MESSAGE = 'chat_message';

    public function __construct(private MediaStorage $media) {}

    /** @return array<int, string> */
    public static function scopes(): array
    {
        return [self::SCOPE_ALL, self::SCOPE_PROVIDER_DOCUMENTS, self::SCOPE_CHAT_ATTACHMENTS];
    }

    public function retirementEnabled(): bool
    {
        return filter_var(
            config('filesystems.media.legacy_retirement_enabled', false),
            FILTER_VALIDATE_BOOL,
        ) === true;
    }

    public function retirementMinimumAgeDays(): int
    {
        return (int) config('filesystems.media.legacy_retirement_min_age_days', 30);
    }

    /** @return Generator<int, LegacyMediaCandidate> */
    public function candidates(string $scope, ?int $subjectId, int $chunkSize): Generator
    {
        if (in_array($scope, [self::SCOPE_ALL, self::SCOPE_PROVIDER_DOCUMENTS], true)) {
            $profiles = ProviderProfile::query()
                ->when($subjectId, fn (Builder $query, int $id): Builder => $query->whereKey($id))
                ->where(function (Builder $query): void {
                    $query->whereNotNull('ktp_image')->orWhereNotNull('nib_document');
                })
                ->lazyById($chunkSize);

            foreach ($profiles as $profile) {
                foreach (['ktp_image' => 'ktp', 'nib_document' => 'nib'] as $field => $collection) {
                    $sourcePath = $profile->getAttribute($field);

                    if (! is_string($sourcePath) || $sourcePath === '') {
                        continue;
                    }

                    $candidate = $this->providerDocumentCandidate($profile, $field, $collection, $sourcePath);

                    if ($this->media->exists($candidate->sourceDisk, $candidate->sourcePath)) {
                        yield $candidate;
                    }
                }
            }
        }

        if (in_array($scope, [self::SCOPE_ALL, self::SCOPE_CHAT_ATTACHMENTS], true)) {
            $messages = ChatMessage::query()
                ->when($subjectId, fn (Builder $query, int $id): Builder => $query->whereKey($id))
                ->whereNotNull('attachment_path')
                ->with('thread:id')
                ->lazyById($chunkSize);

            foreach ($messages as $message) {
                if (! is_string($message->attachment_path) || $message->attachment_path === '') {
                    continue;
                }

                $candidate = $this->chatAttachmentCandidate($message);

                if ($this->media->exists($candidate->sourceDisk, $candidate->sourcePath)) {
                    yield $candidate;
                }
            }
        }
    }

    public function copyAndVerify(LegacyMediaCandidate $candidate): MediaMigrationEntry
    {
        $this->media->assertDiskVisibility($candidate->targetDisk, MediaVisibility::Private);
        $entry = $this->manifestFor($candidate);

        try {
            if (! in_array($entry->status, [MediaMigrationEntry::STATUS_CUTOVER, MediaMigrationEntry::STATUS_ROLLED_BACK], true)) {
                $entry->forceFill([
                    'status' => MediaMigrationEntry::STATUS_COPYING,
                    'copy_started_at' => now(),
                    'error_message' => null,
                ])->save();
            }

            $sourceChecksumBefore = $this->media->checksum($candidate->sourceDisk, $candidate->sourcePath);

            if ($this->media->exists($candidate->targetDisk, $candidate->targetPath)) {
                $existingTargetChecksum = $this->media->checksum($candidate->targetDisk, $candidate->targetPath);

                if (! hash_equals($sourceChecksumBefore, $existingTargetChecksum)) {
                    throw new RuntimeException('The existing target object checksum does not match the source.');
                }
            } else {
                $sourceStream = $this->media->readStream($candidate->sourceDisk, $candidate->sourcePath);

                try {
                    $this->media->writeStream(
                        $candidate->targetDisk,
                        $candidate->targetPath,
                        $sourceStream,
                        MediaVisibility::Private,
                    );
                } finally {
                    fclose($sourceStream);
                }
            }

            $sourceChecksumAfter = $this->media->checksum($candidate->sourceDisk, $candidate->sourcePath);
            $targetChecksum = $this->media->checksum($candidate->targetDisk, $candidate->targetPath);

            if (
                ! hash_equals($sourceChecksumBefore, $sourceChecksumAfter)
                || ! hash_equals($sourceChecksumBefore, $targetChecksum)
            ) {
                throw new RuntimeException('The copied media checksum verification failed.');
            }

            $status = in_array($entry->status, [MediaMigrationEntry::STATUS_CUTOVER, MediaMigrationEntry::STATUS_ROLLED_BACK], true)
                ? $entry->status
                : MediaMigrationEntry::STATUS_VERIFIED;

            $entry->forceFill([
                'source_checksum' => $sourceChecksumBefore,
                'target_checksum' => $targetChecksum,
                'status' => $status,
                'copied_at' => $entry->copied_at ?: now(),
                'verified_at' => now(),
                'error_message' => null,
            ])->save();

            return $entry->refresh();
        } catch (Throwable $error) {
            if ($entry->status !== MediaMigrationEntry::STATUS_CUTOVER) {
                $entry->forceFill([
                    'status' => MediaMigrationEntry::STATUS_FAILED,
                    'error_message' => mb_substr($error->getMessage(), 0, 2000),
                ])->save();
            }

            throw $error;
        }
    }

    public function cutover(MediaMigrationEntry $entry): MediaMigrationEntry
    {
        return DB::transaction(function () use ($entry): MediaMigrationEntry {
            $locked = MediaMigrationEntry::query()->lockForUpdate()->findOrFail($entry->id);

            $this->media->assertDiskVisibility($locked->target_disk, MediaVisibility::Private);

            if (! in_array($locked->status, [
                MediaMigrationEntry::STATUS_VERIFIED,
                MediaMigrationEntry::STATUS_CUTOVER,
                MediaMigrationEntry::STATUS_ROLLED_BACK,
            ], true)) {
                throw new LogicException('Only checksum-verified media can be cut over.');
            }

            $subject = $this->lockedSubject($locked);
            $this->assertAllowlistedManifest($locked, $subject);
            $currentPath = $subject->getAttribute($locked->subject_field);

            // A retired source was deliberately removed only after its
            // checksum-matched private archive was persisted. Re-running a
            // cutover must remain a verified no-op instead of requiring that
            // retired public object to reappear.
            if ($locked->source_retired_at !== null) {
                if ($currentPath !== $locked->target_path) {
                    throw new LogicException('A retired media pointer no longer references its private target.');
                }

                $this->assertTargetIntegrity($locked);
                $this->assertArchiveIntegrity($locked);

                return $locked;
            }

            $this->assertCopyIntegrity($locked);

            if ($currentPath !== $locked->source_path && $currentPath !== $locked->target_path) {
                throw new LogicException('The subject pointer changed after the migration was planned.');
            }

            if ($currentPath !== $locked->target_path) {
                $subject->forceFill([$locked->subject_field => $locked->target_path])->save();
            }

            $resetCutoverAge = $locked->status === MediaMigrationEntry::STATUS_ROLLED_BACK
                || $currentPath !== $locked->target_path;

            $locked->forceFill([
                'status' => MediaMigrationEntry::STATUS_CUTOVER,
                'cutover_at' => $resetCutoverAge ? now() : ($locked->cutover_at ?: now()),
                'rolled_back_at' => null,
                'error_message' => null,
            ])->save();

            return $locked->refresh();
        }, 3);
    }

    public function retire(MediaMigrationEntry $entry): MediaMigrationEntry
    {
        $this->prepareRetirementArchive($entry);

        return DB::transaction(function () use ($entry): MediaMigrationEntry {
            $locked = MediaMigrationEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if ($locked->status !== MediaMigrationEntry::STATUS_CUTOVER) {
                throw new LogicException('Only a cut-over media source can be retired.');
            }

            $this->assertRetirementPolicy($locked);
            $subject = $this->lockedSubject($locked);
            $this->assertAllowlistedManifest($locked, $subject);

            if ($subject->getAttribute($locked->subject_field) !== $locked->target_path) {
                throw new LogicException('The subject must still point to the verified private target.');
            }

            $this->assertTargetIntegrity($locked);
            $this->assertArchiveIntegrity($locked);
            $this->media->assertDiskVisibility($locked->source_disk, MediaVisibility::Public);

            $deletedSource = false;

            if ($this->media->exists($locked->source_disk, $locked->source_path)) {
                $this->assertSourceIntegrity($locked);
                $this->media->delete($locked->source_disk, $locked->source_path);

                if ($this->media->exists($locked->source_disk, $locked->source_path)) {
                    throw new RuntimeException('The verified legacy source could not be retired.');
                }

                $deletedSource = true;
            }

            $recordRetirement = $deletedSource || $locked->source_retired_at === null;

            $locked->forceFill([
                'source_retired_at' => $locked->source_retired_at ?: now(),
                'source_restored_at' => null,
                'retirement_count' => $recordRetirement
                    ? $locked->retirement_count + 1
                    : $locked->retirement_count,
                'error_message' => null,
            ])->save();

            return $locked->refresh();
        }, 3);
    }

    public function rollback(MediaMigrationEntry $entry): MediaMigrationEntry
    {
        return DB::transaction(function () use ($entry): MediaMigrationEntry {
            $locked = MediaMigrationEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if (! in_array($locked->status, [
                MediaMigrationEntry::STATUS_CUTOVER,
                MediaMigrationEntry::STATUS_ROLLED_BACK,
            ], true)) {
                throw new LogicException('Only a cut-over media pointer can be rolled back.');
            }

            $subject = $this->lockedSubject($locked);
            $this->assertAllowlistedManifest($locked, $subject);
            $currentPath = $subject->getAttribute($locked->subject_field);

            if ($currentPath !== $locked->source_path && $currentPath !== $locked->target_path) {
                throw new LogicException('The subject pointer changed after cutover.');
            }

            $sourceWasRetired = ! $this->media->exists($locked->source_disk, $locked->source_path);
            $shouldRecordRestore = $sourceWasRetired || $locked->source_retired_at !== null;
            $this->media->assertDiskVisibility($locked->source_disk, MediaVisibility::Public);

            if ($sourceWasRetired) {
                $this->assertArchiveIntegrity($locked);
                [$archiveDisk, $archivePath] = $this->expectedArchive($locked);
                $archiveStream = $this->media->readStream($archiveDisk, $archivePath);

                try {
                    $this->media->writeStream(
                        $locked->source_disk,
                        $locked->source_path,
                        $archiveStream,
                        MediaVisibility::Public,
                    );
                } finally {
                    fclose($archiveStream);
                }

                $this->assertArchiveIntegrity($locked);
            }

            $this->assertSourceIntegrity($locked);
            $wasAlreadyRolledBack = $currentPath === $locked->source_path;

            if (! $wasAlreadyRolledBack) {
                $subject->forceFill([$locked->subject_field => $locked->source_path])->save();
            }

            $locked->forceFill([
                'status' => MediaMigrationEntry::STATUS_ROLLED_BACK,
                'source_retired_at' => null,
                'source_restored_at' => $shouldRecordRestore
                    ? ($locked->source_restored_at ?: now())
                    : $locked->source_restored_at,
                'rolled_back_at' => $locked->rolled_back_at ?: now(),
                'rollback_count' => $wasAlreadyRolledBack
                    ? $locked->rollback_count
                    : $locked->rollback_count + 1,
                'error_message' => null,
            ])->save();

            return $locked->refresh();
        }, 3);
    }

    public function entries(string $scope, ?int $subjectId, string $stage): Builder
    {
        $statuses = match ($stage) {
            'rollback' => [MediaMigrationEntry::STATUS_CUTOVER, MediaMigrationEntry::STATUS_ROLLED_BACK],
            'retire' => [MediaMigrationEntry::STATUS_CUTOVER],
            default => [
                MediaMigrationEntry::STATUS_VERIFIED,
                MediaMigrationEntry::STATUS_CUTOVER,
                MediaMigrationEntry::STATUS_ROLLED_BACK,
            ],
        };

        return MediaMigrationEntry::query()
            ->when($scope !== self::SCOPE_ALL, fn (Builder $query): Builder => $query->where('scope', $scope))
            ->when($subjectId, fn (Builder $query, int $id): Builder => $query->where('subject_id', $id))
            ->whereIn('status', $statuses)
            ->orderBy('id');
    }

    private function providerDocumentCandidate(
        ProviderProfile $profile,
        string $field,
        string $collection,
        string $sourcePath,
    ): LegacyMediaCandidate {
        $sourceDisk = (string) config('filesystems.media.legacy_public_disk', 'public');
        $targetDisk = (string) config('filesystems.provider_documents_disk', 'provider_documents');
        $targetPath = sprintf(
            '%s/providers/%d/%s.%s',
            $collection,
            (int) $profile->user_id,
            substr(hash('sha256', $sourceDisk."\0".$sourcePath), 0, 32),
            $this->safeExtension($sourcePath),
        );

        return new LegacyMediaCandidate(
            self::SCOPE_PROVIDER_DOCUMENTS,
            self::SUBJECT_PROVIDER_PROFILE,
            (int) $profile->id,
            $field,
            $sourceDisk,
            $sourcePath,
            $targetDisk,
            $targetPath,
        );
    }

    private function chatAttachmentCandidate(ChatMessage $message): LegacyMediaCandidate
    {
        $sourceDisk = (string) config('filesystems.media.legacy_public_disk', 'public');
        $targetDisk = (string) config('filesystems.media.chat_attachments_disk', 'media_private');
        $sourcePath = (string) $message->attachment_path;
        $targetPath = sprintf(
            'support/chat/%d/%d/%s.%s',
            (int) $message->chat_thread_id,
            (int) $message->id,
            substr(hash('sha256', $sourceDisk."\0".$sourcePath), 0, 32),
            $this->safeExtension($sourcePath),
        );

        return new LegacyMediaCandidate(
            self::SCOPE_CHAT_ATTACHMENTS,
            self::SUBJECT_CHAT_MESSAGE,
            (int) $message->id,
            'attachment_path',
            $sourceDisk,
            $sourcePath,
            $targetDisk,
            $targetPath,
        );
    }

    private function manifestFor(LegacyMediaCandidate $candidate): MediaMigrationEntry
    {
        try {
            $entry = MediaMigrationEntry::query()->firstOrCreate(
                ['migration_key' => $candidate->migrationKey()],
                $candidate->manifestAttributes() + ['status' => MediaMigrationEntry::STATUS_PLANNED],
            );
        } catch (QueryException $error) {
            $entry = MediaMigrationEntry::query()
                ->where('migration_key', $candidate->migrationKey())
                ->first();

            if (! $entry) {
                throw $error;
            }
        }

        foreach ($candidate->manifestAttributes() as $field => $value) {
            if ((string) $entry->getAttribute($field) !== (string) $value) {
                throw new LogicException('The existing media migration manifest does not match the candidate.');
            }
        }

        return $entry;
    }

    private function prepareRetirementArchive(MediaMigrationEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            $locked = MediaMigrationEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if ($locked->status !== MediaMigrationEntry::STATUS_CUTOVER) {
                throw new LogicException('Only a cut-over media source can be archived for retirement.');
            }

            $this->assertRetirementPolicy($locked);
            $subject = $this->lockedSubject($locked);
            $this->assertAllowlistedManifest($locked, $subject);

            if ($subject->getAttribute($locked->subject_field) !== $locked->target_path) {
                throw new LogicException('The subject must still point to the verified private target.');
            }

            $this->assertTargetIntegrity($locked);
            [$archiveDisk, $archivePath, $archiveFingerprint] = $this->expectedArchive($locked);
            $this->assertArchiveMetadataMatches(
                $locked,
                $archiveDisk,
                $archivePath,
                $archiveFingerprint,
            );
            $this->media->assertDiskVisibility($archiveDisk, MediaVisibility::Private);

            if (! $this->media->exists($locked->source_disk, $locked->source_path)) {
                $this->assertArchiveIntegrity($locked);

                return;
            }

            $this->assertSourceIntegrity($locked);
            $sourceChecksumBefore = $this->media->checksum($locked->source_disk, $locked->source_path);

            if ($this->media->exists($archiveDisk, $archivePath)) {
                $existingArchiveChecksum = $this->media->checksum($archiveDisk, $archivePath);

                if (! hash_equals($sourceChecksumBefore, $existingArchiveChecksum)) {
                    throw new RuntimeException('The existing retirement archive checksum does not match the source.');
                }
            } else {
                $sourceStream = $this->media->readStream($locked->source_disk, $locked->source_path);

                try {
                    $this->media->writeStream(
                        $archiveDisk,
                        $archivePath,
                        $sourceStream,
                        MediaVisibility::Private,
                    );
                } finally {
                    fclose($sourceStream);
                }
            }

            $sourceChecksumAfter = $this->media->checksum($locked->source_disk, $locked->source_path);
            $archiveChecksum = $this->media->checksum($archiveDisk, $archivePath);
            $this->assertTargetIntegrity($locked);

            if (
                ! hash_equals($sourceChecksumBefore, $sourceChecksumAfter)
                || ! hash_equals($sourceChecksumBefore, $archiveChecksum)
                || ! hash_equals((string) $locked->target_checksum, $archiveChecksum)
            ) {
                throw new RuntimeException('The retirement archive checksum verification failed.');
            }

            $locked->forceFill([
                'archive_disk' => $archiveDisk,
                'archive_path' => $archivePath,
                'archive_fingerprint' => $archiveFingerprint,
                'archive_checksum' => $archiveChecksum,
                'archive_verified_at' => now(),
                'error_message' => null,
            ])->save();
        }, 3);
    }

    private function assertCopyIntegrity(MediaMigrationEntry $entry): void
    {
        $this->assertSourceIntegrity($entry);
        $this->assertTargetIntegrity($entry);
    }

    private function assertRetirementPolicy(MediaMigrationEntry $entry): void
    {
        if (! $this->retirementEnabled()) {
            throw new LogicException('Legacy media retirement is disabled by configuration.');
        }

        $minimumAgeDays = $this->retirementMinimumAgeDays();

        if ($minimumAgeDays < 1) {
            throw new LogicException('Legacy media retirement requires a minimum age of at least one day.');
        }

        if (! $entry->cutover_at) {
            throw new LogicException('Legacy media retirement requires a recorded cutover time.');
        }

        if ($entry->cutover_at->isAfter(now()->subDays($minimumAgeDays))) {
            throw new LogicException(sprintf(
                'Legacy media retirement requires the cutover to be at least %d day(s) old.',
                $minimumAgeDays,
            ));
        }
    }

    private function assertSourceIntegrity(MediaMigrationEntry $entry): void
    {
        $this->media->assertDiskVisibility($entry->source_disk, MediaVisibility::Public);

        if (! $entry->source_checksum) {
            throw new RuntimeException('The migration manifest has no verified source checksum.');
        }

        if (! $this->media->exists($entry->source_disk, $entry->source_path)) {
            throw new RuntimeException('The verified legacy source object is missing.');
        }

        $sourceChecksum = $this->media->checksum($entry->source_disk, $entry->source_path);

        if (! hash_equals($entry->source_checksum, $sourceChecksum)) {
            throw new RuntimeException('The legacy source changed after checksum verification.');
        }
    }

    private function assertTargetIntegrity(MediaMigrationEntry $entry): void
    {
        $this->media->assertDiskVisibility($entry->target_disk, MediaVisibility::Private);

        if (! $entry->source_checksum || ! $entry->target_checksum) {
            throw new RuntimeException('The migration manifest has no verified target checksum.');
        }

        if (! $this->media->exists($entry->target_disk, $entry->target_path)) {
            throw new RuntimeException('The verified private target object is missing.');
        }

        $targetChecksum = $this->media->checksum($entry->target_disk, $entry->target_path);

        if (
            ! hash_equals($entry->target_checksum, $targetChecksum)
            || ! hash_equals($entry->source_checksum, $targetChecksum)
        ) {
            throw new RuntimeException('The private target changed after checksum verification.');
        }
    }

    private function assertArchiveIntegrity(MediaMigrationEntry $entry): void
    {
        [$archiveDisk, $archivePath, $archiveFingerprint] = $this->expectedArchive($entry);
        $this->assertArchiveMetadataMatches($entry, $archiveDisk, $archivePath, $archiveFingerprint);
        $this->media->assertDiskVisibility($archiveDisk, MediaVisibility::Private);

        if (
            $entry->archive_disk !== $archiveDisk
            || $entry->archive_path !== $archivePath
            || $entry->archive_fingerprint !== $archiveFingerprint
        ) {
            throw new RuntimeException('The retirement archive metadata is incomplete.');
        }

        if (! $entry->archive_verified_at || ! $entry->archive_checksum || ! $entry->source_checksum) {
            throw new RuntimeException('The retirement archive has not been checksum-verified.');
        }

        if (! $this->media->exists($archiveDisk, $archivePath)) {
            throw new RuntimeException('The verified retirement archive object is missing.');
        }

        $archiveChecksum = $this->media->checksum($archiveDisk, $archivePath);

        if (
            ! hash_equals($entry->archive_checksum, $archiveChecksum)
            || ! hash_equals($entry->source_checksum, $archiveChecksum)
            || ($entry->target_checksum && ! hash_equals($entry->target_checksum, $archiveChecksum))
        ) {
            throw new RuntimeException('The retirement archive failed checksum verification.');
        }
    }

    /** @return array{string, string, string} */
    private function expectedArchive(MediaMigrationEntry $entry): array
    {
        $archiveDisk = (string) config('filesystems.media.legacy_archive_disk', 'media_private');
        $prefix = trim((string) config('filesystems.media.legacy_archive_prefix', 'legacy-retirement'), '/');

        if (preg_match('#^[a-zA-Z0-9._-]+(?:/[a-zA-Z0-9._-]+)*$#D', $prefix) !== 1) {
            throw new LogicException('The legacy retirement archive prefix is invalid.');
        }

        $archivePath = sprintf(
            '%s/%s/%s.%s',
            $prefix,
            $entry->scope,
            $entry->migration_key,
            $this->safeExtension($entry->source_path),
        );

        if (
            ($archiveDisk === $entry->source_disk && $archivePath === $entry->source_path)
            || ($archiveDisk === $entry->target_disk && $archivePath === $entry->target_path)
        ) {
            throw new LogicException('The retirement archive must be distinct from source and target objects.');
        }

        return [
            $archiveDisk,
            $archivePath,
            MediaMigrationEntry::fingerprint($archiveDisk, $archivePath),
        ];
    }

    private function assertArchiveMetadataMatches(
        MediaMigrationEntry $entry,
        string $archiveDisk,
        string $archivePath,
        string $archiveFingerprint,
    ): void {
        foreach ([
            'archive_disk' => $archiveDisk,
            'archive_path' => $archivePath,
            'archive_fingerprint' => $archiveFingerprint,
        ] as $field => $expected) {
            $actual = $entry->getAttribute($field);

            if ($actual !== null && (string) $actual !== $expected) {
                throw new LogicException('The retirement archive metadata does not match its deterministic location.');
            }
        }

        if (
            $entry->archive_checksum !== null
            && $entry->source_checksum !== null
            && ! hash_equals($entry->source_checksum, $entry->archive_checksum)
        ) {
            throw new RuntimeException('The recorded retirement archive checksum does not match the source.');
        }
    }

    private function assertAllowlistedManifest(
        MediaMigrationEntry $entry,
        ProviderProfile|ChatMessage $subject,
    ): void {
        if (
            $subject instanceof ProviderProfile
            && $entry->scope === self::SCOPE_PROVIDER_DOCUMENTS
            && $entry->subject_type === self::SUBJECT_PROVIDER_PROFILE
            && in_array($entry->subject_field, ['ktp_image', 'nib_document'], true)
        ) {
            $candidate = $this->providerDocumentCandidate(
                $subject,
                $entry->subject_field,
                $entry->subject_field === 'ktp_image' ? 'ktp' : 'nib',
                $entry->source_path,
            );
        } elseif (
            $subject instanceof ChatMessage
            && $entry->scope === self::SCOPE_CHAT_ATTACHMENTS
            && $entry->subject_type === self::SUBJECT_CHAT_MESSAGE
            && $entry->subject_field === 'attachment_path'
        ) {
            $candidate = new LegacyMediaCandidate(
                self::SCOPE_CHAT_ATTACHMENTS,
                self::SUBJECT_CHAT_MESSAGE,
                (int) $subject->id,
                'attachment_path',
                (string) config('filesystems.media.legacy_public_disk', 'public'),
                $entry->source_path,
                (string) config('filesystems.media.chat_attachments_disk', 'media_private'),
                sprintf(
                    'support/chat/%d/%d/%s.%s',
                    (int) $subject->chat_thread_id,
                    (int) $subject->id,
                    substr(hash('sha256', $entry->source_disk."\0".$entry->source_path), 0, 32),
                    $this->safeExtension($entry->source_path),
                ),
            );
        } else {
            throw new LogicException('The media migration manifest is not allowlisted.');
        }

        if ($entry->migration_key !== $candidate->migrationKey()) {
            throw new LogicException('The media migration key does not match the allowlisted manifest.');
        }

        foreach ($candidate->manifestAttributes() as $field => $expected) {
            if ((string) $entry->getAttribute($field) !== (string) $expected) {
                throw new LogicException('The media migration manifest does not match its allowlisted subject.');
            }
        }
    }

    private function lockedSubject(MediaMigrationEntry $entry): ProviderProfile|ChatMessage
    {
        if (
            $entry->subject_type === self::SUBJECT_PROVIDER_PROFILE
            && in_array($entry->subject_field, ['ktp_image', 'nib_document'], true)
        ) {
            return ProviderProfile::query()->lockForUpdate()->findOrFail($entry->subject_id);
        }

        if (
            $entry->subject_type === self::SUBJECT_CHAT_MESSAGE
            && $entry->subject_field === 'attachment_path'
        ) {
            return ChatMessage::query()->lockForUpdate()->findOrFail($entry->subject_id);
        }

        throw new LogicException('The media migration subject is not allowlisted.');
    }

    private function safeExtension(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{1,12}$/', $extension) === 1 ? $extension : 'bin';
    }
}
