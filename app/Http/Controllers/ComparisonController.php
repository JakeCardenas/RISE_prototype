<?php

namespace App\Http\Controllers;

use App\Models\MonitoringPoint;
use App\Models\PhotoComparison;
use App\Models\PhotoRecord;
use App\Services\Comparison\PhotoComparer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class ComparisonController extends Controller
{
    // READ — two photos of the same spot, earlier one first, with any AI comparison the resident asked for
    public function __invoke(Request $request, MonitoringPoint $point, PhotoComparer $comparer)
    {
        Gate::authorize('view', $point);

        $records = $point->photoRecords()->oldest('submitted_at')->oldest('id')->get();

        if ($records->count() < 2) {
            return view('points.compare', ['point' => $point, 'records' => $records]);
        }

        $first = $this->find($records, $request->query('first'));
        $second = $this->find($records, $request->query('second'));

        // By default compare the oldest and newest photos, never the same photo twice
        $first ??= $records->first(fn (PhotoRecord $record) => ! $record->is($second));
        $second ??= $records->last(fn (PhotoRecord $record) => ! $record->is($first));

        [$earlier, $later] = $first->submitted_at->lte($second->submitted_at) ? [$first, $second] : [$second, $first];

        return view('points.compare', [
            'point' => $point,
            'records' => $records,
            'earlier' => $earlier,
            'later' => $later,
            'samePhoto' => $first->is($second),
            'comparer' => $comparer,
            // The latest AI comparison of this exact pair that the resident hasn't dismissed
            'aiComparison' => PhotoComparison::query()
                ->where('earlier_record_id', $earlier->id)->where('later_record_id', $later->id)
                ->whereNull('dismissed_at')->latest('requested_at')->latest('id')->first(),
        ]);
    }

    // Only records from this point can be chosen; any other ID is treated as not found
    private function find(Collection $records, mixed $id): ?PhotoRecord
    {
        if (! is_string($id) || $id === '') {
            return null;
        }

        return $records->firstWhere('id', $id) ?? abort(404);
    }
}
