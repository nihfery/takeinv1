<?php

namespace App\Modules\Media\Application\Data;

use App\Modules\Media\Infrastructure\Persistence\Models\MediaMigrationEntry;

final readonly class LegacyMediaCandidate
{
    public function __construct(
        public string $scope,
        public string $subjectType,
        public int $subjectId,
        public string $subjectField,
        public string $sourceDisk,
        public string $sourcePath,
        public string $targetDisk,
        public string $targetPath,
    ) {}

    public function migrationKey(): string
    {
        return hash('sha256', implode("\0", [
            $this->scope,
            $this->subjectType,
            (string) $this->subjectId,
            $this->subjectField,
            $this->sourceDisk,
            $this->sourcePath,
            $this->targetDisk,
            $this->targetPath,
        ]));
    }

    /** @return array<string, int|string> */
    public function manifestAttributes(): array
    {
        return [
            'scope' => $this->scope,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'subject_field' => $this->subjectField,
            'source_disk' => $this->sourceDisk,
            'source_path' => $this->sourcePath,
            'source_fingerprint' => MediaMigrationEntry::fingerprint($this->sourceDisk, $this->sourcePath),
            'target_disk' => $this->targetDisk,
            'target_path' => $this->targetPath,
            'target_fingerprint' => MediaMigrationEntry::fingerprint($this->targetDisk, $this->targetPath),
        ];
    }
}
