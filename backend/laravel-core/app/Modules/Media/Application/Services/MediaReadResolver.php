<?php

namespace App\Modules\Media\Application\Services;

use App\Modules\Media\Application\Contracts\MediaStorage;
use App\Modules\Media\Application\Data\MediaLocation;
use App\Modules\Media\Infrastructure\Persistence\Models\MediaMigrationEntry;
use Illuminate\Database\QueryException;
use League\Flysystem\FilesystemException;

final readonly class MediaReadResolver
{
    public function __construct(private MediaStorage $media) {}

    /** @param array<int, string> $samePathFallbackDisks */
    public function resolve(
        string $primaryDisk,
        string $path,
        array $samePathFallbackDisks = [],
    ): ?MediaLocation {
        if ($this->isAvailable($primaryDisk, $path)) {
            return new MediaLocation($primaryDisk, $path);
        }

        try {
            $migration = MediaMigrationEntry::query()
                ->where('target_fingerprint', MediaMigrationEntry::fingerprint($primaryDisk, $path))
                ->where('target_disk', $primaryDisk)
                ->where('target_path', $path)
                ->where('status', MediaMigrationEntry::STATUS_CUTOVER)
                ->latest('id')
                ->first();

            if (
                $migration
                && $this->isAvailable($migration->source_disk, $migration->source_path)
            ) {
                return new MediaLocation($migration->source_disk, $migration->source_path);
            }
        } catch (QueryException) {
            // Deploys remain readable while the additive manifest migration is pending.
        }

        foreach (array_unique($samePathFallbackDisks) as $disk) {
            if ($disk !== $primaryDisk && $this->isAvailable($disk, $path)) {
                return new MediaLocation($disk, $path);
            }
        }

        return null;
    }

    private function isAvailable(string $disk, string $path): bool
    {
        try {
            return $this->media->exists($disk, $path);
        } catch (FilesystemException) {
            return false;
        }
    }
}
