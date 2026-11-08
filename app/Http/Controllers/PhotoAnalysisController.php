<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzePhoto;
use App\Models\PhotoAnalysis;
use App\Models\PhotoRecord;
use App\Services\Analysis\CrackDetector;
use Illuminate\Support\Facades\Gate;

class PhotoAnalysisController extends Controller
{
    public function __construct(private readonly CrackDetector $detector) {}

    // READ — the photo with its latest AI result, or the explained option to request one
    public function show(PhotoRecord $record)
    {
        Gate::authorize('view', $record);

        return view('records.analysis', [
            'record' => $record,
            'point' => $record->monitoringPoint,
            'analysis' => $record->latestAnalysis,
            'detector' => $this->detector,
        ]);
    }

    // CREATE — runs only when the owner asks; nothing is sent to the provider before that
    public function store(PhotoRecord $record)
    {
        Gate::authorize('analyze', $record);

        $latest = $record->latestAnalysis;

        if ($latest && ! $latest->canRetry()) {
            return redirect()->route('records.analysis.show', $record);
        }

        $configured = $this->detector->isConfigured();

        // Asking again while nothing is set up changes nothing, so no further record is added
        if (! $configured && $latest?->status === PhotoAnalysis::NOT_CONFIGURED) {
            return redirect()->route('records.analysis.show', $record);
        }

        $analysis = (new PhotoAnalysis)->forceFill([
            'photo_record_id' => $record->id,
            'user_id' => $record->user_id,
            'status' => $configured ? PhotoAnalysis::PENDING : PhotoAnalysis::NOT_CONFIGURED,
            'provider' => $this->detector->name(),
            'model_id' => $this->detector->modelId(),
            'confidence_threshold' => $this->detector->confidenceThreshold(),
            'requested_at' => now(),
            'completed_at' => $configured ? null : now(),
        ]);
        $analysis->save();

        if ($configured) {
            AnalyzePhoto::dispatch($analysis);
        }

        return redirect()->route('records.analysis.show', $record);
    }

    // READ — a small status check for the waiting page
    public function status(PhotoRecord $record)
    {
        Gate::authorize('view', $record);

        return response()->json(['status' => $record->latestAnalysis?->status]);
    }
}
