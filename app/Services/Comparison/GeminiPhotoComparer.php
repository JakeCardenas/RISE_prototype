<?php

namespace App\Services\Comparison;

use App\Services\Analysis\AnalysisFailed;
use App\Services\Gemini\GeminiClient;
use App\Services\Photos\ProcessedImage;
use App\Support\CautiousText;

// Google Gemini compares two photos of one spot. It first says whether the photos can be compared at all; when they
// can't, RISE lists no surface differences and explains why instead. See README.md for setup.
class GeminiPhotoComparer implements PhotoComparer
{
    public const COMPARABILITY = ['comparable', 'limited', 'not_comparable'];

    public const LIMITATIONS = ['different_angle', 'different_distance', 'different_framing', 'different_lighting', 'blurry', 'obstructed', 'different_area'];

    private const MAX_ITEMS = 3;

    private const MAX_LENGTH = 200;

    // What the model is asked for, and what it must never say; CautiousText filters whatever it says anyway
    private const INSTRUCTIONS = <<<'TEXT'
        You compare two photos of the same spot on a wall, ceiling or floor inside a home. Photo A is earlier; photo B is later.
        Describe only what you can see, in plain, cautious words such as "appears" or "may".
        photo_differences: up to 3 short sentences about differences in how the photos were taken: camera angle, distance, framing, lighting, shadows or focus.
        comparison_limits: what makes the photos harder to compare, from the listed values. Use an empty list if nothing does.
        comparability: "comparable" if the photos show the same area from a similar angle, distance and light; "limited" if differences in how they were taken make some differences hard to judge; "not_comparable" if they show different areas, or differ so much in angle, distance, light or focus that differences on the surface can't be judged.
        visible_differences: up to 3 short sentences about differences in what is visible on the surface, such as a line or mark seen in one photo but not the other, or one that looks darker or more visible. Use an empty list if you can't see a clear difference, or if comparability is "not_comparable".
        Never say that anything grew, widened, lengthened, spread, worsened or improved. Never estimate a size or measurement. Never name a cause. Never judge condition, severity, risk or urgency, or say whether the home is safe. Never suggest repairs or inspections. Keep each sentence under 25 words.
        TEXT;

    public function __construct(private readonly GeminiClient $client) {}

    public function name(): string
    {
        return 'Google Gemini';
    }

    public function modelId(): ?string
    {
        return $this->client->modelId();
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function maxImageSide(): int
    {
        return $this->client->maxImageSide();
    }

    public function compare(ProcessedImage $earlier, ProcessedImage $later): ComparisonResult
    {
        $answer = $this->client->answer([self::INSTRUCTIONS, 'Photo A (earlier):', $earlier, 'Photo B (later):', $later], self::schema());
        $data = $answer->data;
        $comparability = $data['comparability'] ?? null;
        $limitations = $data['comparison_limits'] ?? null;

        if (! in_array($comparability, self::COMPARABILITY, true) || ! is_array($limitations)) {
            throw new AnalysisFailed('invalid_response');
        }

        $limitations = array_values(array_unique(array_filter($limitations, fn ($value) => in_array($value, self::LIMITATIONS, true))));

        // Photos of different areas can't be compared, whatever else the model said
        if (in_array('different_area', $limitations, true)) {
            $comparability = 'not_comparable';
        }

        return new ComparisonResult(
            // RISE abstains from surface differences when the photos can't be compared
            visible: $comparability === 'not_comparable' ? [] : CautiousText::sentences($data['visible_differences'] ?? [], self::MAX_ITEMS, self::MAX_LENGTH),
            photo: CautiousText::sentences($data['photo_differences'] ?? [], self::MAX_ITEMS, self::MAX_LENGTH),
            modelVersion: $answer->modelVersion,
            comparability: $comparability,
            limitations: $limitations,
        );
    }

    // Answers come back in this order, so the model looks at how the photos were taken before it compares the surface
    private static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'photo_differences' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => self::MAX_ITEMS],
                'comparison_limits' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => self::LIMITATIONS], 'maxItems' => count(self::LIMITATIONS)],
                'comparability' => ['type' => 'string', 'enum' => self::COMPARABILITY],
                'visible_differences' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => self::MAX_ITEMS],
            ],
            'required' => ['photo_differences', 'comparison_limits', 'comparability', 'visible_differences'],
        ];
    }
}
