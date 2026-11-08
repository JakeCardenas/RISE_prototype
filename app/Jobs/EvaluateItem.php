<?php

namespace App\Jobs;

use App\Models\EvaluationPrediction;
use App\Services\Analysis\AnalysisFailed;
use App\Services\Analysis\AnalysisProviders;
use App\Services\Analysis\Observation;
use App\Services\Photos\PhotoProcessor;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

// Sends one research image in an evaluation run to the run's provider, outside the web request. Nothing about the
// image's label or conditions is sent: only a re-encoded copy of the image and the provider's fixed instructions.
class EvaluateItem implements ShouldQueue
{
    use Queueable;

    // Below the database queue's 90-second retry window, so a slow call is never run twice
    public int $timeout = 75;

    public function __construct(public EvaluationPrediction $prediction) {}

    // Spaced out so a large run stays inside the provider's rate limits; jobs over the limit wait in the queue
    public function middleware(): array
    {
        return [new RateLimited('evaluation-provider')];
    }

    // Waiting for the rate limit isn't a failure, so attempts are bounded by time instead of by count
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(12);
    }

    public function handle(AnalysisProviders $providers, PhotoProcessor $photos): void
    {
        $prediction = $this->prediction;

        // A job stopped part-way (for example by the time limit) is recorded as such, never sent twice
        if ($prediction->status === EvaluationPrediction::PROCESSING) {
            $this->fail($prediction, 'worker_timeout');

            return;
        }

        if ($prediction->status !== EvaluationPrediction::PENDING) {
            return;
        }

        // Every image in a run is scored by the same provider and model, or not at all
        $run = $prediction->run;
        $detector = $providers->make($run->provider);

        if (! $detector?->isConfigured() || $detector->modelId() !== $run->model_id) {
            $this->fail($prediction, 'provider_changed');

            return;
        }

        $prediction->forceFill(['status' => EvaluationPrediction::PROCESSING])->save();

        try {
            $item = $prediction->item;
            $bytes = Storage::disk($item->image_disk)->get($item->image_path) ?? throw new AnalysisFailed('photo_missing');
            $result = $detector->detect($photos->analysisCopy($bytes, $detector->maxImageSide()));

            $prediction->forceFill([
                'status' => EvaluationPrediction::COMPLETED,
                'finding' => ($result->observation ?? Observation::fromRegions($result->regions))->finding,
                'regions' => array_column($result->regions, 'box'),
                'model_version' => $result->modelVersion,
                'completed_at' => now(),
            ])->save();
        } catch (AnalysisFailed $e) {
            $this->fail($prediction, $e->errorCode);
        } catch (Throwable $e) {
            // Only the exception type is logged; messages can contain provider URLs and keys
            Log::warning('RISE evaluation item failed unexpectedly.', ['prediction' => $prediction->id, 'exception' => $e::class]);
            $this->fail($prediction, 'unexpected_error');
        }
    }

    // Reached when the worker gives up on the job, for example after the timeout
    public function failed(?Throwable $exception): void
    {
        $this->fail($this->prediction, 'worker_timeout');
    }

    private function fail(EvaluationPrediction $prediction, string $errorCode): void
    {
        $prediction->forceFill([
            'status' => EvaluationPrediction::FAILED,
            'error_code' => $errorCode,
            'completed_at' => now(),
        ])->save();
    }
}
