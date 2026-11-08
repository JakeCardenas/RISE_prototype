<?php

namespace App\Services\Analysis;

use App\Support\CautiousText;

// RISE's own result for one photo: a bounded finding, short filtered descriptions, what limits the photo, and one next
// step from a fixed list. Every field is checked here, whatever a model returned, and again whenever a stored result
// is read back.
final class Observation
{
    public const FINDINGS = ['possible_feature', 'no_clear_feature', 'uncertain'];

    public const LIMITATIONS = ['blurry', 'too_dark', 'too_bright', 'glare', 'shadows', 'too_far', 'too_close', 'angled', 'obstructed', 'low_detail'];

    // With any of these, a photo can't support "nothing clear was found"
    public const SEVERE_LIMITATIONS = ['blurry', 'too_dark', 'glare', 'obstructed', 'low_detail'];

    public const NEXT_STEPS = ['retake_photo', 'compare_with_baseline', 'add_note', 'keep_monitoring', 'consult_professional_if_concerned'];

    public const MAX_OBSERVATIONS = 4;

    public const MAX_LENGTH = 220;

    /**
     * @param  list<string>  $observations
     * @param  list<string>  $limitations
     */
    public function __construct(
        public readonly string $finding,
        public readonly ?string $summary,
        public readonly array $observations,
        public readonly array $limitations,
        public readonly string $nextStep,
    ) {}

    // From a model's JSON answer or a stored result; null when a required field is missing or outside the fixed lists
    public static function fromAnswer(array $answer): ?self
    {
        $finding = $answer['finding'] ?? null;
        $nextStep = $answer['next_step'] ?? null;
        $limitations = $answer['quality_limitations'] ?? null;

        if (! in_array($finding, self::FINDINGS, true) || ! in_array($nextStep, self::NEXT_STEPS, true) || ! is_array($limitations)) {
            return null;
        }

        $limitations = array_values(array_unique(array_filter($limitations, fn ($value) => in_array($value, self::LIMITATIONS, true))));

        // "Nothing clear" from a photo that hides the surface becomes "can't tell", and the resident is asked to retake it
        if ($finding === 'no_clear_feature' && array_intersect($limitations, self::SEVERE_LIMITATIONS)) {
            [$finding, $nextStep] = ['uncertain', 'retake_photo'];
        }

        return new self(
            finding: $finding,
            summary: CautiousText::sentence($answer['summary'] ?? null, self::MAX_LENGTH),
            observations: CautiousText::sentences($answer['observations'] ?? [], self::MAX_OBSERVATIONS, self::MAX_LENGTH),
            limitations: $limitations,
            nextStep: $nextStep,
        );
    }

    // A region detector only says whether it marked anything, so that is all its result says
    public static function fromRegions(array $regions): self
    {
        return $regions
            ? new self('possible_feature', null, [], [], 'compare_with_baseline')
            : new self('no_clear_feature', null, [], [], 'keep_monitoring');
    }

    // Stored under the same names the model answers with, so a stored result is checked the same way
    public function toArray(): array
    {
        return [
            'finding' => $this->finding,
            'summary' => $this->summary,
            'observations' => $this->observations,
            'quality_limitations' => $this->limitations,
            'next_step' => $this->nextStep,
        ];
    }
}
