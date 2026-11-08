<?php

namespace App\Services\Comparison;

use App\Services\Analysis\AnalysisFailed;
use App\Services\Photos\ProcessedImage;

// The boundary between RISE and whichever vision model describes differences between two photos of one spot.
// Off until configured (see AppServiceProvider and README.md); RISE's crack-area provider analyses one photo at a time.
interface PhotoComparer
{
    // The provider's name as residents see it before anything is sent
    public function name(): string;

    public function modelId(): ?string;

    public function isConfigured(): bool;

    // The longest side, in pixels, of each copy sent
    public function maxImageSide(): int;

    /** @throws AnalysisFailed */
    public function compare(ProcessedImage $earlier, ProcessedImage $later): ComparisonResult;
}
