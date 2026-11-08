<?php

namespace App\Jobs;

use App\Models\PhotoAnalysis;
use App\Services\Analysis\AnalysisFailed;
use App\Services\Analysis\CrackDetector;
use App\Services\Photos\PhotoProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

// Sends one resident-requested photo to the configured detector, outside the web request
class AnalyzePhoto implements ShouldQueue
{
    use Queueable;

    // The provider call already retries rate limits and server errors a bounded number of times
    public int $tries = 1;

    // Below the database queue's 90-second retry window, so a slow call is never run twice
    public int $timeout = 75;

    public function __construct(public PhotoAnalysis $analysis) {}

    public function handle(CrackDetector $detector, PhotoProcessor $photos): void
    {
        $analysis = $this->analysis;

        if ($analysis->status !== PhotoAnalysis::PENDING) {
            return;
        }

        if (! $detector->isConfigured()) {
            $analysis->forceFill(['status' => PhotoAnalysis::NOT_CONFIGURED, 'completed_at' => now()])->save();

            return;
        }

        $analysis->forceFill([
            'status' => PhotoAnalysis::PROCESSING,
            'started_at' => now(),
            'attempts' => $analysis->attempts + 1,
        ])->save();

        try {
            $record = $analysis->photoRecord;

            // The display copy (when there is one) is already upright and free of location data
            $bytes = Storage::disk($record->image_disk)->get($record->display_path ?? $record->image_path)
                ?? throw new AnalysisFailed('photo_missing');

            $result = $detector->detect($photos->analysisCopy($bytes, $detector->maxImageSide()));

            $analysis->forceFill([
                'status' => PhotoAnalysis::COMPLETED,
                'result' => ['image' => $result->image, 'regions' => $result->regions],
                'region_count' => count($result->regions),
                'model_version' => $result->modelVersion,
                'provider_request_id' => $result->requestId,
                'completed_at' => now(),
            ])->save();
        } catch (AnalysisFailed $e) {
            $this->fail($analysis, $e->errorCode);
        } catch (Throwable $e) {
            // Only the exception type is logged; messages can contain provider URLs and keys
            Log::warning('RISE photo analysis failed unexpectedly.', ['analysis' => $analysis->id, 'exception' => $e::class]);
            $this->fail($analysis, 'unexpected_error');
        }
    }

    // Reached when the worker stops the job, for example after the timeout
    public function failed(?Throwable $exception): void
    {
        $this->fail($this->analysis, 'worker_timeout');
    }

    private function fail(PhotoAnalysis $analysis, string $errorCode): void
    {
        $analysis->forceFill([
            'status' => PhotoAnalysis::FAILED,
            'error_code' => $errorCode,
            'completed_at' => now(),
        ])->save();
    }
}
