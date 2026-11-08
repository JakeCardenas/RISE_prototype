<?php

namespace App\Services\Analysis;

use App\Services\Photos\ProcessedImage;

// The boundary between RISE and whichever computer-vision provider marks crack-like areas.
// Swap the implementation (see AppServiceProvider) to use a model trained on RISE's own images.
interface CrackDetector
{
    public function name(): string;

    public function modelId(): ?string;

    public function confidenceThreshold(): ?float;

    public function isConfigured(): bool;

    // The longest side, in pixels, of the copy sent for analysis
    public function maxImageSide(): int;

    /** @throws AnalysisFailed */
    public function detect(ProcessedImage $image): DetectionResult;
}
