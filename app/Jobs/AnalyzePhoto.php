<?php

namespace App\Jobs;

use App\Models\PhotoAnalysis;
use App\Services\Analysis\AnalysisFailed;
use App\Services\Analysis\AnalysisProviders;
use App\Services\Photos\PhotoProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

// Sends one resident-requested photo to the provider it was requested from, outside the web request
class AnalyzePhoto implements ShouldQueue
{
    use Queueable;

    // The provider call already retries rate limits and server errors a bounded number of times
    public int $tries = 1;

    // Below the database queue's 90-second retry window, so a slow call is never run twice
    public int $timeout = 75;

    public function __construct(public PhotoAnalysis $analysis) {}

    public function handle(AnalysisProviders $providers, PhotoProcessor $photos): void
    {
        $analysis = $this->analysis;

        if ($analysis->status !== PhotoAnalysis::PENDING) {
            return;
        }

        // The provider the resident was told about, even if the server's choice changed while this waited
        $detector = $providers->make($analysis->provider);

        if (! $detector?->isConfigured()) {
            $analysis->forceFill(['status' => PhotoAnalysis::NOT_CONFIGURED, 'completed_at' => now()])->save();

            return;
        }

        $analysis->forceFill([
            'status' => PhotoAnalysis::PROCESSING,
            'started_at' => now(),
            'sent_at' => null,
            'attempts' => $analysis->attempts + 1,
        ])->save();
        $started = microtime(true);

        try {
            $record = $analysis->photoRecord;

            // The display copy (when there is one) is already upright and free of location data
            $bytes = Storage::disk($record->image_disk)->get($record->display_path ?? $record->image_path)
                ?? throw new AnalysisFailed('photo_missing');

            // The waiting page shows "waiting for the result" once the photo has been fully sent
            $result = $detector->detect(
                $photos->analysisCopy($bytes, $detector->maxImageSide()),
                fn () => $analysis->forceFill(['sent_at' => now()])->save(),
            );

            $analysis->forceFill([
                'status' => PhotoAnalysis::COMPLETED,
                // A provider's written result is stored only after Observation has checked it; a region detector's
                // result is derived from its regions when it is shown
                'result' => array_filter([
                    'image' => $result->image,
                    'regions' => $result->regions,
                    'observation' => $result->observation?->toArray(),
                ], fn ($value) => $value !== null),
                'region_count' => count($result->regions),
                'model_version' => $result->modelVersion,
                'provider_request_id' => $result->requestId,
                'completed_at' => now(),
            ])->save();
            $this->logOutcome('info', $analysis, $started);
        } catch (AnalysisFailed $e) {
            $this->fail($analysis, $e->errorCode);
            $this->logOutcome('warning', $analysis, $started, $e->httpStatus);
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
        $this->logOutcome('warning', $this->analysis, null);
    }

    // Safe operator diagnostics only: provider, model, outcome, duration and HTTP status; never keys, headers, photos
    // or anything the provider sent back
    private function logOutcome(string $level, PhotoAnalysis $analysis, ?float $started, ?int $httpStatus = null): void
    {
        Log::log($level, 'RISE photo analysis '.($analysis->status === PhotoAnalysis::COMPLETED ? 'completed.' : 'failed.'), array_filter([
            'analysis' => $analysis->id,
            'provider' => $analysis->provider,
            'model' => $analysis->model_id,
            'error_code' => $analysis->error_code,
            'http_status' => $httpStatus,
            'duration_ms' => $started ? (int) round((microtime(true) - $started) * 1000) : null,
            'sent' => $analysis->sent_at !== null,
        ], fn ($value) => $value !== null));
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
