<?php

namespace App\Models;

use Database\Factories\PhotoAnalysisFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoAnalysis extends Model
{
    /** @use HasFactory<PhotoAnalysisFactory> */
    use HasFactory, HasUlids;

    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const NOT_CONFIGURED = 'not_configured';

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

    public function isWaiting(): bool
    {
        return in_array($this->status, [self::PENDING, self::PROCESSING], true);
    }

    public function canRetry(): bool
    {
        return in_array($this->status, [self::FAILED, self::NOT_CONFIGURED], true);
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
            'provider_unavailable', 'provider_unreachable', 'provider_timeout' => 'The analysis provider couldn’t be reached or didn’t answer in time. Try again later.',
            'unsupported_image', 'image_too_large' => 'The provider couldn’t analyse this photo. Try again, or analyse a different photo of this spot.',
            'invalid_response' => 'The provider sent back a result RISE couldn’t read. Try again later.',
            default => 'Something went wrong while analysing this photo. Try again.',
        };
    }
}
