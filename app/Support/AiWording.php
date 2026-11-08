<?php

namespace App\Support;

// RISE's own fixed wording for each value in an AI result. The model only picks values; residents read these.
final class AiWording
{
    public static function finding(string $finding): string
    {
        return match ($finding) {
            'possible_feature' => 'The model describes a possible crack-like feature.',
            'no_clear_feature' => 'The model didn’t find a clear crack-like feature.',
            default => 'The model couldn’t tell from this photo.',
        };
    }

    public static function findingNote(string $finding): string
    {
        return match ($finding) {
            'possible_feature' => 'It describes something that may be crack-like. It can’t confirm a crack, and it doesn’t mark where it is.',
            'no_clear_feature' => '“No clear feature found” doesn’t prove there’s no crack. Small or faint cracks can be missed, especially in some light.',
            default => 'The photo doesn’t let the model tell either way. That says nothing about whether there is a crack.',
        };
    }

    public static function limitation(string $limitation): string
    {
        return match ($limitation) {
            'blurry' => 'The photo looks blurry, so fine lines may not show.',
            'too_dark' => 'The photo is dark, so details may be hidden.',
            'too_bright' => 'The photo is very bright or washed out, so faint lines may not show.',
            'glare' => 'Glare or a reflection hides part of the surface.',
            'shadows' => 'Shadows may hide lines, or look like them.',
            'too_far' => 'The surface is far from the camera, so small details may be lost.',
            'too_close' => 'The photo is very close, so the area around the spot isn’t visible.',
            'angled' => 'The photo is taken at a steep angle, which can change how lines look.',
            'obstructed' => 'Something covers part of the surface.',
            default => 'The photo has too little detail to describe the surface well.',
        };
    }

    public static function nextStep(string $step, bool $isBaseline): string
    {
        return match ($step) {
            'retake_photo' => 'Take another photo in even light, holding the camera steady and square to the surface.',
            'compare_with_baseline' => $isBaseline
                ? 'Take a follow-up photo later from the same place, and compare it with this baseline.'
                : 'Compare this photo with your baseline, side by side.',
            'add_note' => 'Write down what you see in person, such as where a line starts and ends, in your note for this photo.',
            'keep_monitoring' => 'Keep photographing this spot from the same place over time.',
            default => 'If you’re concerned about this spot, ask a qualified building professional to look at it in person.',
        };
    }

    public static function comparability(string $comparability): string
    {
        return match ($comparability) {
            'comparable' => 'The model found these photos similar enough in angle, distance and light to point out differences.',
            'limited' => 'The model noted differences in how these photos were taken, so some differences are hard to judge.',
            default => 'The model couldn’t compare these photos reliably, so it lists no differences on the surface.',
        };
    }

    public static function comparisonLimit(string $limit): string
    {
        return match ($limit) {
            'different_angle' => 'They’re taken from different angles.',
            'different_distance' => 'They’re taken from different distances.',
            'different_framing' => 'They’re framed differently.',
            'different_lighting' => 'The light is different.',
            'blurry' => 'At least one photo looks blurry.',
            'obstructed' => 'Something covers part of the surface in at least one photo.',
            default => 'They may show different areas.',
        };
    }
}
