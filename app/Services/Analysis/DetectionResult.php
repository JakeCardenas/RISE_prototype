<?php

namespace App\Services\Analysis;

// Normalized model output: region coordinates are fractions (0–1) of the analysed image's width and height. A provider
// that describes the photo in words adds an Observation; one that only marks regions leaves it out.
final class DetectionResult
{
    /**
     * @param  array{width: int, height: int}  $image
     * @param  list<array{label: string, score: float, box: list<float>, polygon: list<list<float>>|null}>  $regions
     */
    public function __construct(
        public readonly array $image,
        public readonly array $regions,
        public readonly ?string $modelVersion,
        public readonly ?string $requestId,
        public readonly ?Observation $observation = null,
    ) {}
}
