<?php

namespace App\Modules\Media\Application\Data;

final readonly class MediaLocation
{
    public function __construct(
        public string $disk,
        public string $path,
    ) {}
}
