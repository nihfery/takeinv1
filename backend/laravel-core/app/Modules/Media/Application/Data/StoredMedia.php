<?php

namespace App\Modules\Media\Application\Data;

use App\Modules\Media\Domain\MediaVisibility;

final readonly class StoredMedia
{
    public function __construct(
        public string $disk,
        public string $path,
        public MediaVisibility $visibility,
        public string $checksum,
        public int $size,
        public ?string $mimeType,
    ) {}
}
