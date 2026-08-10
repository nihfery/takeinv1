<?php

namespace App\Modules\Media\Console\Commands;

use App\Modules\Media\Application\Services\LegacyMediaMigrationService;
use App\Modules\Media\Infrastructure\Persistence\Models\MediaMigrationEntry;
use Illuminate\Console\Command;
use Throwable;

final class MigrateLegacyMedia extends Command
{
    protected $signature = 'media:migrate-legacy
        {--scope=all : all, provider-documents, or chat-attachments}
        {--stage=copy : copy, cutover, retire, or rollback}
        {--id= : Restrict to one allowlisted profile/message ID}
        {--chunk=100 : Database chunk size}
        {--execute : Perform the selected mutation; omitted means dry-run}';

    protected $description = 'Copy, verify, cut over, archive-retire, or roll back allowlisted legacy public media';

    public function handle(LegacyMediaMigrationService $migrations): int
    {
        $scope = (string) $this->option('scope');
        $stage = (string) $this->option('stage');
        $execute = (bool) $this->option('execute');
        $chunkSize = (int) $this->option('chunk');
        $subjectId = $this->subjectId();

        if (! in_array($scope, LegacyMediaMigrationService::scopes(), true)) {
            $this->error('Invalid --scope. Use all, provider-documents, or chat-attachments.');

            return self::INVALID;
        }

        if (! in_array($stage, ['copy', 'cutover', 'retire', 'rollback'], true)) {
            $this->error('Invalid --stage. Use copy, cutover, retire, or rollback.');

            return self::INVALID;
        }

        if ($chunkSize < 1 || $chunkSize > 1000 || $subjectId === false) {
            $this->error('--chunk must be 1..1000 and --id must be a positive integer.');

            return self::INVALID;
        }

        if ($scope === LegacyMediaMigrationService::SCOPE_ALL && $subjectId !== 0) {
            $this->error('--id requires an explicit provider-documents or chat-attachments scope.');

            return self::INVALID;
        }

        if ($stage === 'retire' && $execute && ! $migrations->retirementEnabled()) {
            $this->error('Retirement is disabled. Set MEDIA_LEGACY_RETIREMENT_ENABLED=true only after approval.');

            return self::FAILURE;
        }

        if ($stage === 'retire' && $execute && $migrations->retirementMinimumAgeDays() < 1) {
            $this->error('MEDIA_LEGACY_RETIREMENT_MIN_AGE_DAYS must be at least 1.');

            return self::FAILURE;
        }

        $this->warn($execute ? 'EXECUTE mode enabled.' : 'DRY RUN: no files, manifests, or database pointers will be changed.');
        $this->line("Scope: {$scope}; stage: {$stage}");

        if ($stage === 'retire' && ! $execute && ! $migrations->retirementEnabled()) {
            $this->warn('Retirement execution is currently disabled by configuration.');
        }

        return $stage === 'copy'
            ? $this->copy($migrations, $scope, $subjectId ?: null, $chunkSize, $execute)
            : $this->changePointers($migrations, $scope, $subjectId ?: null, $stage, $execute);
    }

    private function copy(
        LegacyMediaMigrationService $migrations,
        string $scope,
        ?int $subjectId,
        int $chunkSize,
        bool $execute,
    ): int {
        $count = 0;
        $failures = 0;

        foreach ($migrations->candidates($scope, $subjectId, $chunkSize) as $candidate) {
            $count++;
            $this->line(sprintf(
                '[%s:%d:%s] %s:%s -> %s:%s',
                $candidate->subjectType,
                $candidate->subjectId,
                $candidate->subjectField,
                $candidate->sourceDisk,
                $candidate->sourcePath,
                $candidate->targetDisk,
                $candidate->targetPath,
            ));

            if (! $execute) {
                continue;
            }

            try {
                $entry = $migrations->copyAndVerify($candidate);
                $this->info("Verified manifest #{$entry->id} ({$entry->source_checksum}).");
            } catch (Throwable $error) {
                $failures++;
                $this->error("Copy failed: {$error->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Candidates: {$count}; failures: {$failures}.");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function changePointers(
        LegacyMediaMigrationService $migrations,
        string $scope,
        ?int $subjectId,
        string $stage,
        bool $execute,
    ): int {
        $count = 0;
        $failures = 0;

        $migrations->entries($scope, $subjectId, $stage)
            ->chunkById(100, function ($entries) use ($migrations, $stage, $execute, &$count, &$failures): void {
                foreach ($entries as $entry) {
                    $count++;
                    $this->line(sprintf(
                        '[manifest:%d %s:%d:%s] %s -> %s',
                        $entry->id,
                        $entry->subject_type,
                        $entry->subject_id,
                        $entry->subject_field,
                        $entry->source_path,
                        $entry->target_path,
                    ));

                    if (! $execute) {
                        continue;
                    }

                    try {
                        $updated = match ($stage) {
                            'rollback' => $migrations->rollback($entry),
                            'retire' => $migrations->retire($entry),
                            default => $migrations->cutover($entry),
                        };
                        $this->info("Manifest #{$updated->id}: {$updated->status}.");
                    } catch (Throwable $error) {
                        $failures++;
                        $this->recordPointerFailure($entry, $error);
                        $this->error("{$stage} failed: {$error->getMessage()}");
                    }
                }
            });

        $this->newLine();
        $this->info("Manifests: {$count}; failures: {$failures}.");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function recordPointerFailure(MediaMigrationEntry $entry, Throwable $error): void
    {
        $entry->refresh()->forceFill([
            'error_message' => mb_substr($error->getMessage(), 0, 2000),
        ])->save();
    }

    private function subjectId(): int|false
    {
        $value = $this->option('id');

        if ($value === null || $value === '') {
            return 0;
        }

        $value = (string) $value;

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : false;
    }
}
