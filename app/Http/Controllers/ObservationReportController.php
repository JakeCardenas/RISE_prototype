<?php

namespace App\Http\Controllers;

use App\Models\MonitoringPoint;
use App\Models\PhotoAnalysis;
use App\Support\Observations;
use Illuminate\Support\Facades\Gate;

class ObservationReportController extends Controller
{
    // READ — one point's photos in date order, one part at a time, laid out to print or save as PDF from the browser
    public function __invoke(MonitoringPoint $point)
    {
        Gate::authorize('view', $point);

        $records = $point->photoRecords()
            // Only finished analyses are real model output; pending, failed and unconfigured attempts are left out
            ->with(['analyses' => fn ($query) => $query->where('status', PhotoAnalysis::COMPLETED)->latest('completed_at')])
            ->oldest('submitted_at')->oldest('id')
            ->paginate(Observations::PER_REPORT_PART, pageName: 'part');

        // A part number past the end goes to the last part rather than an empty report
        if ($records->isEmpty() && $records->currentPage() > 1) {
            return redirect()->route('points.report', [$point, 'part' => $records->lastPage()]);
        }

        return view('points.report', [
            'point' => $point,
            'records' => $records,
            'baseline' => $point->photoRecords()->oldest('submitted_at')->oldest('id')->first(),
            'latest' => $point->photoRecords()->latest('submitted_at')->latest('id')->first(),
        ]);
    }
}
