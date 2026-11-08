<?php

namespace App\Services\Analysis;

use App\Services\Gemini\GeminiClient;

// The single-photo providers RISE can use, by the name stored with each analysis. Residents get the one chosen with
// RISE_ANALYSIS_PROVIDER; the evaluation workspace can run any that is set up.
class AnalysisProviders
{
    public const NAMES = ['gemini', 'roboflow'];

    public function make(string $name): ?CrackDetector
    {
        return match ($name) {
            'gemini' => new GeminiPhotoObserver(new GeminiClient(config('services.gemini'))),
            'roboflow' => new RoboflowCrackDetector(config('services.roboflow')),
            default => null,
        };
    }

    // RISE_ANALYSIS_PROVIDER when it names a provider; otherwise Gemini once it is set up, and Roboflow before that
    public function default(): CrackDetector
    {
        $chosen = $this->make((string) config('rise.analysis_provider'));
        $gemini = $this->make('gemini');

        return $chosen ?? ($gemini->isConfigured() ? $gemini : $this->make('roboflow'));
    }

    /** @return array<string, CrackDetector> */
    public function configured(): array
    {
        return collect(self::NAMES)
            ->mapWithKeys(fn (string $name) => [$name => $this->make($name)])
            ->filter(fn (CrackDetector $detector) => $detector->isConfigured())
            ->all();
    }

    // The provider's name as residents and researchers see it
    public static function label(?string $name): string
    {
        return match ($name) {
            'gemini' => 'Google Gemini',
            'roboflow' => 'Roboflow',
            default => str((string) $name)->headline()->toString(),
        };
    }
}
