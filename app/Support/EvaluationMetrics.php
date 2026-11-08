<?php

namespace App\Support;

use App\Models\EvaluationPrediction;

// Scores one evaluation run against its human labels. The positive class is a human "crack-like" label, predicted by
// the finding "possible_feature". Images a person labelled "uncertain" are left out and counted; a model answer of
// "uncertain" is an abstention, reported as coverage rather than counted as right or wrong. Location is scored only for
// providers that return boxes, by intersection over union (IoU) with the boxes the labeller drew.
final class EvaluationMetrics
{
    public const IOU_THRESHOLD = 0.5;

    // Below this many decided images, figures are shown with a warning that they can't be relied on
    public const SMALL_SAMPLE = 30;

    public const FINDINGS = ['possible_feature' => 'Possible feature', 'no_clear_feature' => 'No clear feature', 'uncertain' => 'Uncertain'];

    /** @param  iterable<EvaluationPrediction>  $predictions */
    public static function summarize(iterable $predictions, bool $measuresLocation): array
    {
        $counts = array_fill_keys(['total', 'waiting', 'failed', 'completed', 'human_uncertain', 'abstained', 'tp', 'fp', 'fn', 'tn'], 0);
        $matrix = array_fill_keys(['crack_like', 'not_crack_like', 'uncertain'], array_fill_keys(array_keys(self::FINDINGS), 0));
        $location = ['regions' => 0, 'matched' => 0, 'boxes' => 0, 'best' => []];

        foreach ($predictions as $prediction) {
            $counts['total']++;

            if ($prediction->isWaiting()) {
                $counts['waiting']++;

                continue;
            }

            if ($prediction->status !== EvaluationPrediction::COMPLETED || ! isset(self::FINDINGS[$prediction->finding])) {
                $counts['failed']++;

                continue;
            }

            $counts['completed']++;
            $label = array_key_exists($prediction->snapshot['label'] ?? '', $matrix) ? $prediction->snapshot['label'] : 'uncertain';
            $matrix[$label][$prediction->finding]++;

            if ($label === 'uncertain') {
                $counts['human_uncertain']++;

                continue;
            }

            if ($prediction->finding === 'uncertain') {
                $counts['abstained']++;

                continue;
            }

            $predicted = $prediction->finding === 'possible_feature';
            $counts[$label === 'crack_like' ? ($predicted ? 'tp' : 'fn') : ($predicted ? 'fp' : 'tn')]++;

            if ($measuresLocation) {
                self::locate($location, $label === 'crack_like' ? ($prediction->snapshot['regions'] ?? []) : [], $predicted ? ($prediction->regions ?? []) : []);
            }
        }

        $decided = $counts['tp'] + $counts['fp'] + $counts['fn'] + $counts['tn'];
        $precision = self::ratio($counts['tp'], $counts['tp'] + $counts['fp']);
        $recall = self::ratio($counts['tp'], $counts['tp'] + $counts['fn']);

        return [
            ...$counts,
            'matrix' => $matrix,
            'decided' => $decided,
            'coverage' => self::ratio($decided, $decided + $counts['abstained']),
            'precision' => $precision,
            'precision_interval' => self::wilson($counts['tp'], $counts['tp'] + $counts['fp']),
            'recall' => $recall,
            'recall_interval' => self::wilson($counts['tp'], $counts['tp'] + $counts['fn']),
            'f1' => $precision !== null && $recall !== null && $precision + $recall > 0 ? 2 * $precision * $recall / ($precision + $recall) : null,
            'small_sample' => $decided < self::SMALL_SAMPLE,
            'location' => $measuresLocation ? [
                'regions' => $location['regions'],
                'matched' => $location['matched'],
                'boxes' => $location['boxes'],
                'region_recall' => self::ratio($location['matched'], $location['regions']),
                'box_precision' => self::ratio($location['matched'], $location['boxes']),
                'mean_best_iou' => $location['best'] ? array_sum($location['best']) / count($location['best']) : null,
            ] : null,
        ];
    }

    /**
     * The same summary for each value of one condition, or for each model version the provider reported
     *
     * @param  iterable<EvaluationPrediction>  $predictions
     * @return array<string, array>
     */
    public static function grouped(iterable $predictions, string $by, bool $measuresLocation): array
    {
        $groups = [];

        foreach ($predictions as $prediction) {
            $key = $by === 'model_version' ? ($prediction->model_version ?? 'not reported') : ($prediction->snapshot[$by] ?? 'not recorded');
            $groups[$key][] = $prediction;
        }

        ksort($groups);

        return array_map(fn (array $group) => self::summarize($group, $measuresLocation), $groups);
    }

    // Intersection over union of two [x, y, width, height] boxes in fractions of the image
    public static function iou(array $a, array $b): float
    {
        $width = max(0, min($a[0] + $a[2], $b[0] + $b[2]) - max($a[0], $b[0]));
        $height = max(0, min($a[1] + $a[3], $b[1] + $b[3]) - max($a[1], $b[1]));
        $overlap = $width * $height;
        $union = $a[2] * $a[3] + $b[2] * $b[3] - $overlap;

        return $union > 0 ? $overlap / $union : 0.0;
    }

    // Each human box is matched to at most one predicted box, best overlaps first; a match needs IoU of at least 0.5
    private static function locate(array &$location, array $human, array $predicted): void
    {
        $location['regions'] += count($human);
        $location['boxes'] += count($predicted);

        foreach ($human as $box) {
            $location['best'][] = $predicted ? max(array_map(fn ($guess) => self::iou($box, $guess), $predicted)) : 0.0;
        }

        $pairs = [];

        foreach ($human as $h => $box) {
            foreach ($predicted as $p => $guess) {
                if (($iou = self::iou($box, $guess)) >= self::IOU_THRESHOLD) {
                    $pairs[] = [$iou, $h, $p];
                }
            }
        }

        rsort($pairs);
        $used = ['h' => [], 'p' => []];

        foreach ($pairs as [, $h, $p]) {
            if (! isset($used['h'][$h]) && ! isset($used['p'][$p])) {
                $used['h'][$h] = $used['p'][$p] = true;
                $location['matched']++;
            }
        }
    }

    private static function ratio(int $part, int $whole): ?float
    {
        return $whole > 0 ? $part / $whole : null;
    }

    // A 95% Wilson score interval, which stays sensible for small samples and for proportions near 0 or 1
    private static function wilson(int $successes, int $n): ?array
    {
        if ($n === 0) {
            return null;
        }

        $z = 1.96;
        $p = $successes / $n;
        $denominator = 1 + $z ** 2 / $n;
        $centre = ($p + $z ** 2 / (2 * $n)) / $denominator;
        $half = $z * sqrt($p * (1 - $p) / $n + $z ** 2 / (4 * $n ** 2)) / $denominator;

        return [max(0.0, $centre - $half), min(1.0, $centre + $half)];
    }
}
