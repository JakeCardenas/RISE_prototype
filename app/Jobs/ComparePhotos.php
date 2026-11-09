<?php

namespace App\Jobs;

use App\Models\PhotoComparison;
use App\Models\PhotoRecord;
use App\Services\Analysis\AnalysisFailed;
use App\Services\Comparison\PhotoComparer;
use App\Services\Photos\PhotoProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

// Sends two resident-chosen photos of one spot to the configured comparer, outside the web request
class ComparePhotos implements ShouldQueue
{
    use Queueable;

    // The provider call already retries rate limits and server errors a bounded number of times
    public int $tries = 1;

    // Below the database queue's 90-second retry window, so a slow call is never run twice
    public int $timeout = 75;

    public function __construct(public PhotoComparison $comparison) {}

    public function handle(PhotoComparer $comparer, PhotoProcessor $photos): void
    {
        $comparison = $this->comparison;

        if ($comparison->status !== PhotoComparison::PENDING) {
            return;
        }

        if (! $comparer->isConfigured()) {
            $this->fail($comparison, 'not_configured');

            return;
        }

        $comparison->forceFill([
            'status' => PhotoComparison::PROCESSING,
            'started_at' => now(),
            'sent_at' => null,
            'attempts' => $comparison->attempts + 1,
        ])->save();
        $started = microtime(true);

        try {
            // Each copy is re-encoded, resized and stripped of every embedded detail, including location, before it
            // leaves RISE; the display copy is used when there is one, so a HEIC original is never read for this
            $copy = function (?PhotoRecord $record) use ($photos, $comparer) {
                $bytes = $record ? Storage::disk($record->image_disk)->get($record->display_path ?? $record->image_path) : null;

                return $photos->analysisCopy($bytes ?? throw new AnalysisFailed('photo_missing'), $comparer->maxImageSide());
            };

            $result = $comparer->compare($copy($comparison->earlier), $copy($comparison->later), fn () => $comparison->forceFill(['sent_at' => now()])->save());

            $comparison->forceFill([
                'status' => PhotoComparison::COMPLETED,
                'result' => array_filter([
                    'visible' => $result->visible,
                    'photo' => $result->photo,
                    'comparability' => $result->comparability,
                    'limitations' => $result->comparability ? $result->limitations : null,
                ], fn ($value) => $value !== null),
                'model_version' => $result->modelVersion,
                'completed_at' => now(),
            ])->save();
            $this->logOutcome('info', $comparison, $started);
        } catch (AnalysisFailed $e) {
            $this->fail($comparison, $e->errorCode);
            $this->logOutcome('warning', $comparison, $started, $e->httpStatus);
        } catch (Throwable $e) {
            // Only the exception type is logged; messages can contain provider URLs and keys
            Log::warning('RISE photo comparison failed unexpectedly.', ['comparison' => $comparison->id, 'exception' => $e::class]);
            $this->fail($comparison, 'unexpected_error');
        }
    }

    // Reached when the worker stops the job, for example after the timeout
    public function failed(?Throwable $exception): void
    {
        $this->fail($this->comparison, 'worker_timeout');
        $this->logOutcome('warning', $this->comparison, null);
    }

    // Safe operator diagnostics only: provider, model, outcome, duration and HTTP status; never keys, headers, photos
    // or anything the provider sent back
    private function logOutcome(string $level, PhotoComparison $comparison, ?float $started, ?int $httpStatus = null): void
    {
        Log::log($level, 'RISE photo comparison '.($comparison->status === PhotoComparison::COMPLETED ? 'completed.' : 'failed.'), array_filter([
            'comparison' => $comparison->id,
            'provider' => $comparison->provider,
            'model' => $comparison->model_id,
            'error_code' => $comparison->error_code,
            'http_status' => $httpStatus,
            'duration_ms' => $started ? (int) round((microtime(true) - $started) * 1000) : null,
            'sent' => $comparison->sent_at !== null,
        ], fn ($value) => $value !== null));
    }

    private function fail(PhotoComparison $comparison, string $errorCode): void
    {
        $comparison->forceFill([
            'status' => PhotoComparison::FAILED,
            'error_code' => $errorCode,
            'completed_at' => now(),
        ])->save();
    }
}
