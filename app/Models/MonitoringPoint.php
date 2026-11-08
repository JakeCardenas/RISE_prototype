<?php

namespace App\Models;

use Database\Factories\MonitoringPointFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MonitoringPoint extends Model
{
    /** @use HasFactory<MonitoringPointFactory> */
    use HasFactory, HasUlids;

    // Fields that may be filled from user input; the owner is always set by the server
    protected $fillable = ['label', 'area', 'surface_description', 'follow_up_on'];

    // follow_up_on is a calendar date the resident chose, with no time or time zone of its own
    protected function casts(): array
    {
        return [
            'follow_up_on' => 'date:Y-m-d',
        ];
    }

    protected static function booted(): void
    {
        // Delete records one by one so each private photo file is removed too
        static::deleting(function (MonitoringPoint $point) {
            $point->photoRecords()->get()->each->delete();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photoRecords(): HasMany
    {
        return $this->hasMany(PhotoRecord::class);
    }

    public function latestRecord(): HasOne
    {
        return $this->hasOne(PhotoRecord::class)->latestOfMany('submitted_at');
    }
}
