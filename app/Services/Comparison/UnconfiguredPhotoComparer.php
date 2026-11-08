<?php

namespace App\Services\Comparison;

use App\Services\Analysis\AnalysisFailed;
use App\Services\Photos\ProcessedImage;

// Used until the person who runs RISE configures a provider: the feature stays unavailable and nothing is sent
class UnconfiguredPhotoComparer implements PhotoComparer
{
    public function name(): string
    {
        return '';
    }

    public function modelId(): ?string
    {
        return null;
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function maxImageSide(): int
    {
        return 1024;
    }

    public function compare(ProcessedImage $earlier, ProcessedImage $later): ComparisonResult
    {
        throw new AnalysisFailed('not_configured');
    }
}
