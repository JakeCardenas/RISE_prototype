<?php

namespace App\Http\Controllers;

use App\Models\AiFeedback;
use App\Models\PhotoAnalysis;
use App\Models\PhotoComparison;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

// A resident's own rating of a finished AI result. One rating per result, which they can change; it is never sent to a
// provider or used to train a model.
class AiFeedbackController extends Controller
{
    // UPDATE — the rating of a single-photo result
    public function analysis(Request $request, PhotoAnalysis $analysis)
    {
        Gate::authorize('update', $analysis);
        abort_unless($analysis->status === PhotoAnalysis::COMPLETED, 404);

        $this->save($request, 'photo_analysis_id', $analysis->id);

        return redirect(route('records.analysis.show', $analysis->photo_record_id).'#feedback')
            ->with('success', 'Your feedback is saved with this result.');
    }

    // UPDATE — the rating of a two-photo comparison
    public function comparison(Request $request, PhotoComparison $comparison)
    {
        Gate::authorize('update', $comparison);
        abort_unless($comparison->status === PhotoComparison::COMPLETED, 404);

        $this->save($request, 'photo_comparison_id', $comparison->id);

        return redirect(route('points.compare', [$comparison->monitoring_point_id, 'first' => $comparison->earlier_record_id, 'second' => $comparison->later_record_id]).'#ai-feedback')
            ->with('success', 'Your feedback is saved with this comparison.');
    }

    private function save(Request $request, string $subject, string $id): void
    {
        $data = $request->validate([
            'rating' => ['required', Rule::in(array_keys(AiFeedback::RATINGS))],
            'feedback_note' => ['nullable', 'string', 'max:'.AiFeedback::MAX_NOTE],
        ], [
            'rating.required' => 'Choose useful, incorrect or not sure.',
            'feedback_note.max' => 'Keep the note under '.AiFeedback::MAX_NOTE.' characters.',
        ]);

        $feedback = AiFeedback::query()->where('user_id', $request->user()->id)->where($subject, $id)->first()
            ?? (new AiFeedback)->forceFill(['user_id' => $request->user()->id, $subject => $id]);

        $feedback->forceFill(['rating' => $data['rating'], 'note' => filled($data['feedback_note'] ?? null) ? trim($data['feedback_note']) : null])->save();
    }
}
