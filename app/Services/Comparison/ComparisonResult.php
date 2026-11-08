<?php

namespace App\Services\Comparison;

// Short, filtered sentences only: possible visible differences, and possible differences in how the photos were taken.
// A provider that judges whether the two photos can be compared at all also says so, with its reasons.
final class ComparisonResult
{
    /**
     * @param  list<string>  $visible
     * @param  list<string>  $photo
     * @param  list<string>  $limitations
     */
    public function __construct(
        public readonly array $visible,
        public readonly array $photo,
        public readonly ?string $modelVersion,
        public readonly ?string $comparability = null,
        public readonly array $limitations = [],
    ) {}
}
