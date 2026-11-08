<?php

namespace App\Services\Analysis;

use RuntimeException;

// Carries only a safe error code; provider messages, URLs and keys never travel with it
class AnalysisFailed extends RuntimeException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct("Photo analysis failed: {$errorCode}");
    }
}
