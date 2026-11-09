<?php

namespace App\Services\Analysis;

use App\Services\Photos\ProcessedImage;
use Closure;

// The boundary between RISE and whichever computer-vision provider marks crack-like areas.
// Swap the implementation (see AppServiceProvider) to use a model trained on RISE's own images.
interface CrackDetector
{
    public function name(): string;

    public function modelId(): ?string;

    public function confidenceThreshold(): ?float;

    public function isConfigured(): bool;

    /** @return list<string> The environment settings still needed before it can run, by name; never their values */
    public function missingSettings(): array;

    // The longest side, in pixels, of the copy sent for analysis
    public function maxImageSide(): int;

    /**
     * @param  Closure|null  $sent  Called once the photo has been fully sent, while the provider works on it
     *
     * @throws AnalysisFailed
     */
    public function detect(ProcessedImage $image, ?Closure $sent = null): DetectionResult;
}
