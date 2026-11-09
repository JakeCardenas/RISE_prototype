<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzePhoto;
use App\Models\PhotoAnalysis;
use App\Models\PhotoRecord;
use App\Services\Analysis\CrackDetector;
use Illuminate\Support\Facades\Cache;
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

        // One request per photo at a time, so a double click can't start two analyses or two provider charges
        Cache::lock('rise-analysis-request:'.$record->id, 10)->get(fn () => $this->request($record));

        return redirect()->route('records.analysis.show', $record);
    }

    private function request(PhotoRecord $record): void
    {
        $latest = $record->latestAnalysis()->first();

        if ($latest && ! $latest->canRetry()) {
            return;
        }

        $configured = $this->detector->isConfigured();

        // Asking again while nothing is set up changes nothing, so no further record is added
        if (! $configured && $latest?->status === PhotoAnalysis::NOT_CONFIGURED) {
            return;
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
    }

    // READ — a small status check for the waiting page: the status, the real stage, and seconds since the request
    public function status(PhotoRecord $record)
    {
        Gate::authorize('view', $record);

        $analysis = $record->latestAnalysis;

        return response()->json([
            'status' => $analysis?->status,
            'stage' => $analysis?->stage(),
            'elapsed' => $analysis?->elapsedSeconds(),
        ])->header('Cache-Control', 'no-store');
    }
}
