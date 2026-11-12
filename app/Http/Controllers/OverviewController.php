<?php

namespace App\Http\Controllers;

use App\Models\MonitoringPoint;
use App\Models\PhotoRecord;
use App\Support\FollowUps;
use App\Support\HomeAreas;
use App\Support\NextStep;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OverviewController extends Controller
{
    // How many recent photos the overview shows
    private const RECENT = 6;

    // READ — the resident's next useful step, their follow-up dates, recent photos and points by area.
    // Every number and suggestion comes from their own saved records and the dates they chose.
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $today = FollowUps::today($request);

        // Most recently photographed first; points without photos by when they were added
        $points = NextStep::points($user);

        // Follow-up dates the resident chose, earliest first; a follow-up needs a baseline, so spots without a photo are left out
        $planned = $points
            ->filter(fn (MonitoringPoint $point) => $point->follow_up_on && $point->photo_records_count > 0)
            ->sortBy(fn (MonitoringPoint $point) => $point->follow_up_on->toDateString())
            ->values();
        $reminders = $planned->filter(fn (MonitoringPoint $point) => FollowUps::status($point->follow_up_on, $today) !== 'later')->values();
        $due = $reminders->filter(fn (MonitoringPoint $point) => FollowUps::daysUntil($point->follow_up_on, $today) <= 0)->values();
        $upcoming = $planned->filter(fn (MonitoringPoint $point) => FollowUps::daysUntil($point->follow_up_on, $today) > 0)->values();

        $nextStep = NextStep::choose($points, $due);

        return view('overview', [
            'firstName' => Str::of($user->name)->trim()->before(' ')->toString(),
            'today' => $today,
            'points' => $points,
            'areas' => HomeAreas::group($points),
            'photoCount' => $points->sum('photo_records_count'),
            'readyToCompare' => $points->where('photo_records_count', '>=', 2)->count(),
            // Kept apart, so a date that has passed is never counted as due today
            'pastDueCount' => $due->filter(fn (MonitoringPoint $point) => FollowUps::daysUntil($point->follow_up_on, $today) < 0)->count(),
            'dueTodayCount' => $due->filter(fn (MonitoringPoint $point) => FollowUps::daysUntil($point->follow_up_on, $today) === 0)->count(),
            'planned' => $planned,
            'upcomingCount' => $upcoming->count(),
            'nextPlanned' => $upcoming->first()?->follow_up_on,
            'nextStep' => $nextStep,
            // The featured point isn't listed again below it
            'reminders' => $reminders->reject(fn (MonitoringPoint $point) => $point->is($nextStep['point'] ?? null))->values(),
            'recent' => $this->recentPhotos($user, $points),
        ]);
    }

    // The latest photos across all points, each with its place in its point's history. Any newer photo of the same
    // point is also in this list, so a photo's position is its point's photo count minus the newer ones above it.
    private function recentPhotos($user, Collection $points): Collection
    {
        $newer = [];

        return $user->photoRecords()->latest('submitted_at')->latest('id')->limit(self::RECENT)->get()
            ->map(function (PhotoRecord $record) use ($points, &$newer) {
                $point = $points->firstWhere('id', $record->monitoring_point_id);
                $above = $newer[$point->id] ?? 0;
                $newer[$point->id] = $above + 1;

                return ['record' => $record, 'point' => $point, 'position' => $point->photo_records_count - $above];
            });
    }
}
