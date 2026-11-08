<?php

namespace App\Http\Controllers;

use App\Models\MonitoringPoint;
use App\Support\FollowUps;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

// A point's next follow-up date, set by the resident from the point's passport. RISE shows it on the Overview
// as the day gets close; it never sends notifications and never sets a date by itself.
class FollowUpController extends Controller
{
    // UPDATE — set the date from a quick choice ("In 1 month", counted from the resident's own today) or the date picker
    public function update(Request $request, MonitoringPoint $point)
    {
        Gate::authorize('update', $point);

        $data = $request->validate([
            'in_days' => ['nullable', 'integer', Rule::in(array_keys(FollowUps::QUICK_PICKS))],
            'follow_up_on' => ['required_without:in_days', 'nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2099-12-31'],
        ], [
            'follow_up_on.*' => 'Choose a follow-up date from the calendar.',
        ]);

        $date = isset($data['in_days'])
            ? FollowUps::today($request)->addDays((int) $data['in_days'])
            : CarbonImmutable::createFromFormat('!Y-m-d', $data['follow_up_on']);

        $point->update(['follow_up_on' => $date->toDateString()]);

        return redirect(route('points.show', $point).'#follow-up')
            ->with('success', 'Next follow-up set for '.$date->format('l j F Y').'. It will appear on your Overview as the day gets close.');
    }

    // DELETE — clear the date; the point and its photos are unchanged
    public function destroy(MonitoringPoint $point)
    {
        Gate::authorize('update', $point);

        $point->update(['follow_up_on' => null]);

        return redirect(route('points.show', $point).'#follow-up')->with('success', 'Follow-up date cleared.');
    }
}
