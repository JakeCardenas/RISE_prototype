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
            'attempts' => $comparison->attempts + 1,
        ])->save();

        try {
            // Each copy is re-encoded, resized and stripped of every embedded detail, including location, before it
            // leaves RISE; the display copy is used when there is one, so a HEIC original is never read for this
            $copy = function (?PhotoRecord $record) use ($photos, $comparer) {
                $bytes = $record ? Storage::disk($record->image_disk)->get($record->display_path ?? $record->image_path) : null;

                return $photos->analysisCopy($bytes ?? throw new AnalysisFailed('photo_missing'), $comparer->maxImageSide());
            };

            $result = $comparer->compare($copy($comparison->earlier), $copy($comparison->later));

            $comparison->forceFill([
                'status' => PhotoComparison::COMPLETED,
                'result' => ['visible' => $result->visible, 'photo' => $result->photo],
                'model_version' => $result->modelVersion,
                'completed_at' => now(),
            ])->save();
        } catch (AnalysisFailed $e) {
            $this->fail($comparison, $e->errorCode);
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
