<?php

namespace App\Modules\Media\Application\Contracts;

use App\Modules\Media\Application\Data\StoredMedia;
use App\Modules\Media\Domain\MediaVisibility;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface MediaStorage
{
    public function diskFor(MediaVisibility $visibility): string;

    public function assertDiskVisibility(string $disk, MediaVisibility $visibility): void;

    public function storeUploadedFile(
        UploadedFile $file,
        string $directory,
        MediaVisibility $visibility,
        ?string $disk = null,
    ): StoredMedia;

    /** @param resource $stream */
    public function writeStream(
        string $disk,
        string $path,
        mixed $stream,
        MediaVisibility $visibility,
        ?string $mimeType = null,
    ): void;

    /** @return resource */
    public function readStream(string $disk, string $path): mixed;

    public function checksum(string $disk, string $path): string;

    public function exists(string $disk, string $path): bool;

    public function delete(string $disk, string $path): void;

    /** @param array<string, string> $headers */
    public function response(
        string $disk,
        string $path,
        ?string $name = null,
        array $headers = [],
        string $disposition = 'inline',
    ): StreamedResponse;
}
