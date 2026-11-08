<?php

namespace App\Services\Gemini;

// Gemini's JSON answer, decoded but not yet checked against RISE's own rules, with what Gemini reported about the call
final class GeminiAnswer
{
    /** @param  array<string, mixed>  $data */
    public function __construct(
        public readonly array $data,
        public readonly ?string $modelVersion,
        public readonly ?string $requestId,
    ) {}
}
