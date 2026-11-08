<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoComparison extends Model
{
    use HasUlids;

    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';

    // Every field is set by the server; nothing comes from user input
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'attempts' => 'integer',
            'requested_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function monitoringPoint(): BelongsTo
    {
        return $this->belongsTo(MonitoringPoint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function earlier(): BelongsTo
    {
        return $this->belongsTo(PhotoRecord::class, 'earlier_record_id');
    }

    public function later(): BelongsTo
    {
        return $this->belongsTo(PhotoRecord::class, 'later_record_id');
    }

    public function isWaiting(): bool
    {
        return in_array($this->status, [self::PENDING, self::PROCESSING], true);
    }

    /** @return list<string> */
    public function visibleDifferences(): array
    {
        return $this->result['visible'] ?? [];
    }

    /** @return list<string> */
    public function photoDifferences(): array
    {
        return $this->result['photo'] ?? [];
    }

    // The result as plain text the resident can copy, or edit into their own note
    public function asText(): string
    {
        $lines = [];

        foreach (['Possible visible differences' => $this->visibleDifferences(), 'Possible photo differences' => $this->photoDifferences()] as $heading => $items) {
            if ($items) {
                $lines[] = $heading.': '.implode(' ', $items);
            }
        }

        return implode("\n", $lines);
    }

    // What the resident can do next, by safe error code
    public function failureMessage(): string
    {
        return match ($this->error_code) {
            'invalid_credentials', 'model_not_found', 'provider_quota' => 'The AI service didn’t accept the request. The person who runs RISE needs to check its settings.',
            'rate_limited' => 'The AI service is busy. Try again in a few minutes.',
            'provider_unavailable', 'provider_unreachable', 'provider_timeout', 'worker_timeout' => 'The AI service couldn’t be reached or didn’t answer in time. Try again later.',
            'photo_missing' => 'One of these photos couldn’t be found, so nothing was sent.',
            'invalid_response' => 'The AI service sent back something RISE couldn’t read. Try again later.',
            default => 'Something went wrong. Try again.',
        };
    }
}
