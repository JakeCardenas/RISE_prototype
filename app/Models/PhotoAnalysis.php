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

    // Failures that can clear up by themselves, so trying again later can help; the rest need a settings change or
    // a different photo
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
            'sent_at' => 'datetime',
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

    // Where a waiting request is: in RISE's queue, being sent to the provider, or sent and waiting for its answer
    public function stage(): string
    {
        return match ($this->status) {
            self::PENDING => 'queued',
            self::PROCESSING => $this->sent_at ? 'waiting' : 'sending',
            default => $this->status,
        };
    }

    // Seconds since the request, by the server's clock
    public function elapsedSeconds(): int
    {
        return max(0, (int) $this->requested_at->diffInSeconds(now()));
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

    // A provider that describes the photo in words (Gemini) leaves a written result; a region detector leaves marks
    public function hasWrittenResult(): bool
    {
        return $this->status === self::COMPLETED && isset($this->result['observation']);
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

    // What happened, in a few words, under the failure heading
    public function failureTitle(): string
    {
        $provider = $this->providerLabel();

        return match ($this->error_code) {
            'provider_timeout' => "No answer from {$provider} in time",
            'provider_unreachable' => "Couldn’t connect to {$provider}",
            'provider_unavailable' => "{$provider} had a temporary problem",
            'rate_limited' => "{$provider} is busy",
            'provider_quota' => 'Usage limit reached',
            'invalid_credentials' => 'API key not accepted',
            'model_not_found' => 'AI model not found',
            'invalid_request' => 'Request not accepted',
            'unsupported_image', 'image_too_large' => 'Photo couldn’t be read',
            'invalid_response' => 'Result couldn’t be checked',
            'worker_timeout' => 'The RISE queue stopped the job',
            'photo_missing' => 'Photo file missing',
            default => 'Something went wrong',
        };
    }

    // What happened and what the resident can do next, by safe error code; settings are named in the logs, not here
    public function failureMessage(): string
    {
        $provider = $this->providerLabel();
        $seconds = (int) config($this->provider === 'gemini' ? 'services.gemini.timeout' : 'services.roboflow.timeout');

        return match ($this->error_code) {
            'provider_timeout' => "{$provider} didn’t finish in time (RISE waits up to {$seconds} seconds), so the photo wasn’t analyzed. Try again later.",
            'provider_unreachable' => "This RISE server couldn’t connect to {$provider}, so the photo wasn’t analyzed. Try again later.",
            'provider_unavailable' => "{$provider} reported a temporary problem. Try again in a few minutes.",
            'rate_limited' => "{$provider} is handling too many requests right now. Try again in a few minutes.",
            'provider_quota' => "This server’s {$provider} account has reached its usage limit. The person who runs RISE needs to check the account; trying again won’t help until then.",
            'invalid_credentials' => "{$provider} didn’t accept this server’s API key. The person who runs RISE needs to check the key.",
            'model_not_found' => "{$provider} couldn’t find the AI model this server is set to use. The person who runs RISE needs to check the model name.",
            'invalid_request' => "{$provider} didn’t accept the request’s settings. The person who runs RISE needs to check them.",
            'unsupported_image', 'image_too_large' => "{$provider} couldn’t read this photo. Try analyzing a different photo of this spot.",
            'invalid_response' => "{$provider} sent back a result RISE couldn’t check, so none is shown. Try again later.",
            'worker_timeout' => 'The RISE queue didn’t finish this job in time. Try again; if it keeps happening, the person who runs RISE should check the queue worker.',
            'photo_missing' => 'This photo’s file couldn’t be found, so nothing was sent.',
            default => 'Something went wrong on this server while analyzing the photo. Try again.',
        };
    }
}
