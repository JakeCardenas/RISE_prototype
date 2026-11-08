<?php

namespace App\Models;

use App\Services\Analysis\AnalysisProviders;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// One provider and model, run once over the labelled held-out images
class EvaluationRun extends Model
{
    use HasUlids;

    // Set by the server, never filled from a request
    protected $fillable = [];

    protected function casts(): array
    {
        return ['item_count' => 'integer'];
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(EvaluationPrediction::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function providerLabel(): string
    {
        return AnalysisProviders::label($this->provider);
    }

    // Only a region detector says where it saw something, so only its runs can be scored on location
    public function measuresLocation(): bool
    {
        return $this->provider === 'roboflow';
    }
}
