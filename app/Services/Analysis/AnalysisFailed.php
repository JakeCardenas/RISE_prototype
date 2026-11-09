<?php

namespace App\Services\Analysis;

use RuntimeException;

// Carries only a safe error code, and the provider's HTTP status when there was one; provider messages, URLs and keys
// never travel with it
class AnalysisFailed extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct("Photo analysis failed: {$errorCode}");
    }
}
