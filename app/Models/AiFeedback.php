<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A resident's own rating of one AI result, with an optional note. It is kept apart from the model's wording and from
// the resident's photo notes, and it is never sent to a provider or used to train a model.
class AiFeedback extends Model
{
    use HasUlids;

    public const RATINGS = ['useful' => 'Useful', 'incorrect' => 'Incorrect', 'uncertain' => 'Not sure'];

    public const MAX_NOTE = 500;

    protected $table = 'ai_feedback';

    // Set by the server after validation, never filled straight from a request
    protected $fillable = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photoAnalysis(): BelongsTo
    {
        return $this->belongsTo(PhotoAnalysis::class);
    }

    public function photoComparison(): BelongsTo
    {
        return $this->belongsTo(PhotoComparison::class);
    }
}
