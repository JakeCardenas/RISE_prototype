<?php

namespace App\Models;

use App\Services\Analysis\AnalysisProviders;
use App\Services\Analysis\Observation;
use Database\Factories\PhotoAnalysisFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PhotoAnalysis extends Model
{
    /** @use HasFactory<PhotoAnalysisFactory> */
    use HasFactory, HasUlids;

    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const NOT_CONFIGURED = 'not_configured';

    // Failures that can clear up by themselves, so trying again later can help
    private const TRANSIENT_ERRORS = ['rate_limited', 'provider_unavailable', 'provider_unreachable', 'provider_timeout', 'worker_timeout', 'invalid_response', 'unexpected_error'];

    // Every field is set by the server; nothing comes from user input
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'confidence_threshold' => 'float',
            'region_count' => 'integer',
            'attempts' => 'integer',
            'requested_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function photoRecord(): BelongsTo
    {
        return $this->belongsTo(PhotoRecord::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // The resident's own rating of this result, kept apart from the model's wording
    public function feedback(): HasOne
    {
        return $this->hasOne(AiFeedback::class);
    }

    public function isWaiting(): bool
    {
        return in_array($this->status, [self::PENDING, self::PROCESSING], true);
    }

    public function canRetry(): bool
    {
        return in_array($this->status, [self::FAILED, self::NOT_CONFIGURED], true);
    }

    // unconfigured, queued, processing, completed, timed_out, retryable (may work later) or failed (needs a settings change)
    public function displayState(): string
    {
        return match (true) {
            $this->status === self::NOT_CONFIGURED => 'unconfigured',
            $this->status === self::PENDING => 'queued',
            $this->status === self::PROCESSING => 'processing',
            $this->status === self::COMPLETED => 'completed',
            in_array($this->error_code, ['provider_timeout', 'worker_timeout'], true) => 'timed_out',
            in_array($this->error_code, self::TRANSIENT_ERRORS, true) => 'retryable',
            default => 'failed',
        };
    }

    // RISE's bounded result, checked again on every read. Results saved before written results existed come from a
    // region detector and are derived from its regions; null means a stored result failed the check.
    public function observation(): ?Observation
    {
        if ($this->status !== self::COMPLETED) {
            return null;
        }

        $stored = $this->result['observation'] ?? null;

        return is_array($stored) ? Observation::fromAnswer($stored) : Observation::fromRegions($this->regions());
    }

    public function providerLabel(): string
    {
        return AnalysisProviders::label($this->provider);
    }

    /** @return list<array{label: string, score: float, box: list<float>, polygon: list<list<float>>|null}> */
    public function regions(): array
    {
        return $this->result['regions'] ?? [];
    }

    // What the resident can do next, by safe error code
    public function failureMessage(): string
    {
        return match ($this->error_code) {
            'invalid_credentials' => 'The analysis provider didn’t accept RISE’s credentials. The person who runs RISE needs to check the API key.',
            'model_not_found' => 'The configured model couldn’t be found. The person who runs RISE needs to check the model ID.',
            'provider_quota' => 'The analysis provider’s usage limit has been reached. The person who runs RISE needs to check the account.',
            'rate_limited' => 'The analysis provider is busy right now. Try again in a few minutes.',
            'provider_unavailable', 'provider_unreachable', 'provider_timeout', 'worker_timeout' => 'The analysis provider couldn’t be reached or didn’t answer in time. Try again later.',
            'unsupported_image', 'image_too_large' => 'The provider couldn’t analyse this photo. Try again, or analyse a different photo of this spot.',
            'invalid_response' => 'The provider sent back a result RISE couldn’t read or check, so none is shown. Try again later.',
            'invalid_request' => 'The analysis provider didn’t accept the request. The person who runs RISE needs to check its settings, such as the model ID.',
            'photo_missing' => 'This photo’s file couldn’t be found, so nothing was sent.',
            default => 'Something went wrong while analysing this photo. Try again.',
        };
    }
}
