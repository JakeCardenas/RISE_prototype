<?php

namespace App\Services\Analysis;

use App\Services\Gemini\GeminiClient;
use App\Services\Photos\ProcessedImage;

// Google Gemini looks at one photo and gives RISE's bounded result (see Observation). It isn't asked where anything is,
// so RISE never draws an outline or box for it, and it gives no score. See README.md for setup.
class GeminiPhotoObserver implements CrackDetector
{
    // What the model is asked for, and what it must never say; CautiousText filters whatever it says anyway
    private const INSTRUCTIONS = <<<'TEXT'
        You look at one photo of a wall, ceiling or floor inside a home. A resident takes photos like this to keep a dated record of the same spot over time.
        Describe only what is visible, in plain, cautious words such as "appears", "may" or "looks like".
        summary: one short sentence about what the photo shows, such as the kind of surface and any line or mark on it.
        observations: up to 4 short sentences about what you can see on the surface: where a line or mark is, which way it runs, and whether it looks continuous or branched. Call a line "crack-like", or say it "may be a crack"; never say that it is a crack. Use an empty list if there is nothing to describe.
        quality_limitations: anything about the photo that limits what can be seen, from the listed values. Use an empty list if nothing does.
        finding: "possible_feature" if you can see a line or mark that may be crack-like; "no_clear_feature" if the surface is clearly visible and you see nothing crack-like; "uncertain" if the photo doesn't let you tell.
        next_step: "retake_photo" if the photo should be taken again more clearly; "compare_with_baseline" to compare it with the resident's earlier photos of this spot; "add_note" to write down what they see in person; "keep_monitoring" to keep photographing this spot over time; "consult_professional_if_concerned" if a qualified building professional could look at it should the resident be concerned.
        Never estimate a size, width, length or depth. Never name a cause. Never judge condition, severity, risk or urgency, and never call anything good, bad, normal, safe or unsafe. Never suggest repairs, replacement or inspections. Never say how sure you are. Keep each sentence under 25 words.
        TEXT;

    public function __construct(private readonly GeminiClient $client) {}

    public function name(): string
    {
        return 'gemini';
    }

    public function modelId(): ?string
    {
        return $this->client->modelId();
    }

    // Gemini gives no calibrated score, and RISE doesn't ask it for one
    public function confidenceThreshold(): ?float
    {
        return null;
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function maxImageSide(): int
    {
        return $this->client->maxImageSide();
    }

    public function detect(ProcessedImage $image): DetectionResult
    {
        $answer = $this->client->answer([self::INSTRUCTIONS, $image], self::schema());

        return new DetectionResult(
            image: ['width' => $image->width, 'height' => $image->height],
            regions: [],
            modelVersion: $answer->modelVersion,
            requestId: $answer->requestId,
            observation: Observation::fromAnswer($answer->data) ?? throw new AnalysisFailed('invalid_response'),
        );
    }

    // Answers come back in this order, so the model describes the photo before it gives a finding
    private static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'observations' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => Observation::MAX_OBSERVATIONS],
                'quality_limitations' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => Observation::LIMITATIONS], 'maxItems' => count(Observation::LIMITATIONS)],
                'finding' => ['type' => 'string', 'enum' => Observation::FINDINGS],
                'next_step' => ['type' => 'string', 'enum' => Observation::NEXT_STEPS],
            ],
            'required' => ['summary', 'observations', 'quality_limitations', 'finding', 'next_step'],
        ];
    }
}
