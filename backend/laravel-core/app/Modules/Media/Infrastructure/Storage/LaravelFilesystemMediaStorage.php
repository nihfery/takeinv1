<?php

namespace App\Modules\Media\Infrastructure\Storage;

use App\Modules\Media\Application\Contracts\MediaStorage;
use App\Modules\Media\Application\Data\StoredMedia;
use App\Modules\Media\Domain\MediaVisibility;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LaravelFilesystemMediaStorage implements MediaStorage
{
    public function diskFor(MediaVisibility $visibility): string
    {
        return (string) config(
            $visibility === MediaVisibility::Public
                ? 'filesystems.media.public_disk'
                : 'filesystems.media.private_disk',
            $visibility === MediaVisibility::Public ? 'media_public' : 'media_private',
        );
    }

    public function assertDiskVisibility(string $disk, MediaVisibility $visibility): void
    {
        $diskConfiguration = config("filesystems.disks.{$disk}");

        if (
            ! is_array($diskConfiguration)
            || ($diskConfiguration['visibility'] ?? null) !== $visibility->value
        ) {
            throw new RuntimeException(sprintf(
                'The %s media disk must declare %s visibility.',
                $disk,
                $visibility->value,
            ));
        }
    }

    public function storeUploadedFile(
        UploadedFile $file,
        string $directory,
        MediaVisibility $visibility,
        ?string $disk = null,
    ): StoredMedia {
        $directory = $this->safePath($directory);
        $extension = strtolower((string) $file->extension());
        $extension = preg_match('/^[a-z0-9]{1,12}$/', $extension) === 1 ? $extension : 'bin';
        $path = $directory.'/'.Str::uuid()->toString().'.'.$extension;
        $stream = fopen($file->getRealPath(), 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('The uploaded media stream could not be opened.');
        }

        try {
            $targetDisk = $disk ?: $this->diskFor($visibility);
            $this->writeStream($targetDisk, $path, $stream, $visibility, $file->getMimeType());
        } finally {
            fclose($stream);
        }

        return new StoredMedia(
            disk: $targetDisk,
            path: $path,
            visibility: $visibility,
            checksum: $this->checksum($targetDisk, $path),
            size: (int) $file->getSize(),
            mimeType: $file->getMimeType(),
        );
    }

    public function writeStream(
        string $disk,
        string $path,
        mixed $stream,
        MediaVisibility $visibility,
        ?string $mimeType = null,
    ): void {
        if (! is_resource($stream)) {
            throw new InvalidArgumentException('Media content must be an open stream resource.');
        }

        $this->assertDiskVisibility($disk, $visibility);

        $options = ['visibility' => $visibility->value];

        if (is_string($mimeType) && $mimeType !== '') {
            $options['ContentType'] = $mimeType;
        }

        $written = Storage::disk($disk)->writeStream($this->safePath($path), $stream, $options);

        if ($written !== true) {
            throw new RuntimeException('The media object could not be written.');
        }
    }

    public function readStream(string $disk, string $path): mixed
    {
        $stream = Storage::disk($disk)->readStream($this->safePath($path));

        if (! is_resource($stream)) {
            throw new RuntimeException('The media object stream could not be opened.');
        }

        return $stream;
    }

    public function checksum(string $disk, string $path): string
    {
        $stream = $this->readStream($disk, $path);
        $context = hash_init('sha256');

        try {
            hash_update_stream($context, $stream);
        } finally {
            fclose($stream);
        }

        return hash_final($context);
    }

    public function exists(string $disk, string $path): bool
    {
        return Storage::disk($disk)->exists($this->safePath($path));
    }

    public function delete(string $disk, string $path): void
    {
        Storage::disk($disk)->delete($this->safePath($path));
    }

    public function response(
        string $disk,
        string $path,
        ?string $name = null,
        array $headers = [],
        string $disposition = 'inline',
    ): StreamedResponse {
        return Storage::disk($disk)->response(
            $this->safePath($path),
            $name,
            $headers,
            $disposition,
        );
    }

    private function safePath(string $path): string
    {
        $path = trim($path, '/');

        if (
            $path === ''
            || str_contains($path, "\0")
            || str_contains($path, '\\')
            || collect(explode('/', $path))->contains(
                static fn (string $segment): bool => $segment === '' || $segment === '.' || $segment === '..'
            )
        ) {
            throw new InvalidArgumentException('The media object path is invalid.');
        }

        return $path;
    }
}
