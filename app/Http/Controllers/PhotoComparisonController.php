<?php

namespace App\Http\Controllers;

use App\Jobs\ComparePhotos;
use App\Models\MonitoringPoint;
use App\Models\PhotoComparison;
use App\Models\PhotoRecord;
use App\Services\Comparison\PhotoComparer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

// The optional AI comparison of two photos of one spot. Nothing is sent before the resident asks, and the result is
// never saved as their own note.
class PhotoComparisonController extends Controller
{
    public function __construct(private readonly PhotoComparer $comparer) {}

    // CREATE — for two different photos of the resident's own spot, and only once the provider is set up
    public function store(Request $request, MonitoringPoint $point)
    {
        Gate::authorize('view', $point);

        $data = $request->validate([
            'earlier' => ['required', 'string'],
            'later' => ['required', 'string', 'different:earlier'],
        ]);

        // Only photos of this spot can be chosen; any other ID is treated as not found
        $records = $point->photoRecords()->whereIn('id', [$data['earlier'], $data['later']])
            ->oldest('submitted_at')->oldest('id')->get();
        abort_unless($records->count() === 2, 404);
        [$earlier, $later] = $records->all();

        $back = route('points.compare', [$point, 'first' => $earlier->id, 'second' => $later->id]).'#ai-comparison';

        if (! $this->comparer->isConfigured()) {
            return redirect($back)->with('info', 'AI comparison isn’t set up on this RISE server, so nothing was sent.');
        }

        // One request per pair at a time, so a double click can't start two comparisons or two provider charges
        Cache::lock('rise-comparison-request:'.$earlier->id.':'.$later->id, 10)->get(fn () => $this->request($point, $earlier, $later));

        return redirect($back);
    }

    private function request(MonitoringPoint $point, PhotoRecord $earlier, PhotoRecord $later): void
    {
        $waiting = PhotoComparison::query()
            ->where('earlier_record_id', $earlier->id)->where('later_record_id', $later->id)
            ->whereIn('status', [PhotoComparison::PENDING, PhotoComparison::PROCESSING])
            ->exists();

        if (! $waiting) {
            $comparison = (new PhotoComparison)->forceFill([
                'monitoring_point_id' => $point->id,
                'user_id' => $point->user_id,
                'earlier_record_id' => $earlier->id,
                'later_record_id' => $later->id,
                'status' => PhotoComparison::PENDING,
                'provider' => $this->comparer->name(),
                'model_id' => $this->comparer->modelId(),
                'requested_at' => now(),
            ]);
            $comparison->save();

            ComparePhotos::dispatch($comparison);
        }
    }

    // READ — a small status check for the waiting panel: the status, the real stage, and seconds since the request
    public function status(PhotoComparison $comparison)
    {
        Gate::authorize('view', $comparison);

        return response()->json([
            'status' => $comparison->status,
            'stage' => $comparison->stage(),
            'elapsed' => $comparison->elapsedSeconds(),
        ])->header('Cache-Control', 'no-store');
    }

    // UPDATE — hide the result; the photos and the resident's notes are unchanged, and it can be run again
    public function dismiss(PhotoComparison $comparison)
    {
        Gate::authorize('update', $comparison);

        $comparison->forceFill(['dismissed_at' => now()])->save();

        return redirect(route('points.compare', [$comparison->monitoring_point_id, 'first' => $comparison->earlier_record_id, 'second' => $comparison->later_record_id]).'#ai-comparison')
            ->with('success', 'AI comparison dismissed.');
    }
}
