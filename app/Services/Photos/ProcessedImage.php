<?php

namespace App\Services\Photos;

// A JPEG made from a resident's photo, held in memory
final class ProcessedImage
{
    public function __construct(
        public readonly string $bytes,
        public readonly int $width,
        public readonly int $height,
    ) {}
}
