<?php

namespace App\Support;

use App\Models\MonitoringPoint;
use App\Models\PhotoRecord;
use Carbon\CarbonInterface;

// The observation passport: a point's first photo is its baseline, every later photo is a numbered follow-up.
// Nothing is stored for this; it follows from the order in which photos were saved.
class Observations
{
    // Photos per page in a monitoring point's history
    public const PER_PAGE = 12;

    // Photos per part of a printable report, so a long history prints as several bounded documents
    public const PER_REPORT_PART = 20;

    public static function label(int $position): string
    {
        return $position <= 1 ? 'Baseline' : 'Follow-up '.($position - 1);
    }

    // Where a photo sits in its point's history, counting the baseline as 1 (same order as the history page)
    public static function position(PhotoRecord $record): int
    {
        return PhotoRecord::query()
            ->where('monitoring_point_id', $record->monitoring_point_id)
            ->where(fn ($query) => $query->where('submitted_at', '<', $record->submitted_at)
                ->orWhere(fn ($query) => $query->where('submitted_at', $record->submitted_at)->where('id', '<=', $record->id)))
            ->count();
    }

    // The time between two photos, in days or longer, since follow-ups are compared over days rather than minutes
    public static function span(CarbonInterface $from, CarbonInterface $to): string
    {
        return $from->diffInDays($to, true) < 1
            ? 'less than a day'
            : $from->diffForHumans($to, ['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2, 'minimumUnit' => 'day']);
    }

    // A link to one photo on the page of the history that holds it
    public static function entryUrl(MonitoringPoint|string $point, PhotoRecord $record, int $position): string
    {
        $page = intdiv(max($position, 1) - 1, self::PER_PAGE) + 1;

        return route('points.show', array_filter(['point' => $point, 'page' => $page > 1 ? $page : null])).'#record-'.$record->id;
    }
}
