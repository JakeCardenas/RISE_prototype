<?php

namespace App\Services\Comparison;

// Short, filtered sentences only: possible visible differences, and possible differences in how the photos were taken
final class ComparisonResult
{
    /**
     * @param  list<string>  $visible
     * @param  list<string>  $photo
     */
    public function __construct(
        public readonly array $visible,
        public readonly array $photo,
        public readonly ?string $modelVersion,
    ) {}
}
