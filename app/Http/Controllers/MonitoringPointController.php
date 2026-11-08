<?php

namespace App\Http\Controllers;

use App\Http\Requests\MonitoringPointRequest;
use App\Models\MonitoringPoint;
use App\Support\Observations;
use Illuminate\Support\Facades\Gate;

class MonitoringPointController extends Controller
{
    // CREATE — show the form
    public function create()
    {
        return view('points.create');
    }

    // CREATE — validate and save, then go straight to adding the first photo
    public function store(MonitoringPointRequest $request)
    {
        $point = $request->user()->monitoringPoints()->create($request->pointData());

        return redirect()->route('records.create', ['point' => $point])
            ->with('success', 'Monitoring point created. Now add its first photo.');
    }

    // READ — the point's observation passport: baseline and latest photo, then the whole history in date order
    public function show(MonitoringPoint $point)
    {
        Gate::authorize('view', $point);

        $records = $point->photoRecords()->with('latestAnalysis')->oldest('submitted_at')->oldest('id')->paginate(Observations::PER_PAGE);

        return view('points.show', [
            'point' => $point,
            'records' => $records,
            'recordCount' => $records->total(),
            'baseline' => $point->photoRecords()->oldest('submitted_at')->oldest('id')->first(),
            'latest' => $point->photoRecords()->latest('submitted_at')->latest('id')->first(),
        ]);
    }

    // UPDATE — show the form, pre-filled
    public function edit(MonitoringPoint $point)
    {
        Gate::authorize('update', $point);

        return view('points.edit', [
            'point' => $point,
            'recordCount' => $point->photoRecords()->count(),
        ]);
    }

    // UPDATE — validate and save changes (the form request checks ownership)
    public function update(MonitoringPointRequest $request, MonitoringPoint $point)
    {
        $point->update($request->pointData());

        return redirect()->route('points.show', $point)->with('success', 'Monitoring point updated.');
    }

    // DELETE — remove the point, its records and their photo files
    public function destroy(MonitoringPoint $point)
    {
        Gate::authorize('delete', $point);

        $point->delete();

        return redirect()->route('overview')->with('success', 'Monitoring point and its photos deleted.');
    }
}
