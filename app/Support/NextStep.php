<?php

namespace App\Support;

use App\Models\MonitoringPoint;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

// The one step most worth taking now, chosen from the resident's own spots and the dates they planned. Used by the
// Overview's "Next step" card and by RISE Help's "What should I do next?". It only ever suggests using RISE: adding a
// photo, planning a follow-up or comparing photos; it never says anything about the building itself.
final class NextStep
{
    // The resident's spots with their latest photo and photo count, most recently photographed first; spots without
    // photos by when they were added
    public static function points(User $user): Collection
    {
        return $user->monitoringPoints()
            ->with('latestRecord')
            ->withCount('photoRecords')
            ->withMin('photoRecords', 'submitted_at')
            ->withCasts(['photo_records_min_submitted_at' => 'datetime'])
            ->get()
            ->sortByDesc(fn (MonitoringPoint $point) => ($point->latestRecord?->submitted_at ?? $point->created_at)->getTimestamp())
            ->values();
    }

    // Follow-ups planned for today or earlier, earliest first; a follow-up needs a baseline, so spots without a photo
    // are left out
    public static function due(Collection $points, CarbonImmutable $today): Collection
    {
        return $points
            ->filter(fn (MonitoringPoint $point) => $point->follow_up_on && $point->photo_records_count > 0
                && FollowUps::daysUntil($point->follow_up_on, $today) <= 0)
            ->sortBy(fn (MonitoringPoint $point) => $point->follow_up_on->toDateString())
            ->values();
    }

    // In order: start; photograph a new spot; a follow-up the resident planned for today or earlier; a first follow-up
    // for a spot that only has its baseline; or else compare the most recently photographed spot
    public static function choose(Collection $points, Collection $due): array
    {
        if ($points->isEmpty()) {
            return ['kind' => 'start'];
        }

        $unphotographed = $points->where('photo_records_count', 0)->sortByDesc('created_at')->first();

        if ($unphotographed) {
            return ['kind' => 'baseline', 'point' => $unphotographed];
        }

        if ($due->isNotEmpty()) {
            return ['kind' => 'due', 'point' => $due->first(), 'moreDue' => $due->count() - 1];
        }

        $baselineOnly = $points->first(fn (MonitoringPoint $point) => $point->photo_records_count === 1);

        if ($baselineOnly) {
            return ['kind' => 'follow-up', 'point' => $baselineOnly];
        }

        $point = $points->first();

        return [
            'kind' => 'compare',
            'point' => $point,
            'baseline' => $point->photoRecords()->oldest('submitted_at')->oldest('id')->first(),
        ];
    }
}
