<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// A research image in the evaluation workspace, with its human label. Never a resident's RISE record: a researcher adds
// it with the authorization for using it, and only a re-encoded copy without embedded details is kept.
class EvaluationItem extends Model
{
    use HasUlids;

    public const AUTHORIZATIONS = [
        'research_team' => 'Taken by the research team for this study',
        'participant_consent' => 'Shared by a participant who gave specific, written consent for research use',
        'open_licence' => 'Public domain, or openly licensed for this use',
    ];

    public const LABELS = ['crack_like' => 'Crack-like', 'not_crack_like' => 'Not crack-like', 'uncertain' => 'Uncertain'];

    public const BLUR = ['sharp' => 'Sharp', 'slight' => 'Slightly blurred', 'blurred' => 'Blurred'];

    public const LIGHTING = ['even' => 'Even', 'dim' => 'Dim', 'harsh' => 'Harsh or glare', 'shadows' => 'Strong shadows'];

    public const SURFACES = ['painted_wall' => 'Painted wall', 'concrete' => 'Bare concrete or render', 'tile' => 'Tile', 'ceiling' => 'Ceiling', 'floor' => 'Floor', 'other' => 'Other'];

    // Only held-out images are ever sent in a run; development images are for trying wording and never measured
    public const SPLITS = ['held_out' => 'Held out', 'development' => 'Development'];

    public const MAX_REGIONS = 20;

    // Set by the server after validation, never filled straight from a request
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'regions' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'labelled_at' => 'datetime',
        ];
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(EvaluationPrediction::class);
    }

    // EV-0001, EV-0002…: an identifier that says nothing about the image or who added it
    public static function nextCode(): string
    {
        $last = static::query()->where('code', 'like', 'EV-%')->orderByRaw('length(code) desc')->orderByDesc('code')->value('code');

        return 'EV-'.str_pad((string) ((int) substr((string) $last, 3) + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Boxes drawn by the labeller, as [x, y, width, height] fractions of the image; null when the JSON isn't a valid list
     *
     * @return list<list<float>>|null
     */
    public static function regionsFrom(?string $json): ?array
    {
        if (blank($json)) {
            return [];
        }

        $boxes = json_decode($json, true);

        if (! is_array($boxes) || ! array_is_list($boxes) || count($boxes) > self::MAX_REGIONS) {
            return null;
        }

        $clean = [];

        foreach ($boxes as $box) {
            if (! is_array($box) || ! array_is_list($box) || count($box) !== 4 || array_filter($box, fn ($value) => ! is_int($value) && ! is_float($value))) {
                return null;
            }

            [$x, $y, $w, $h] = array_map(fn ($value) => round((float) $value, 5), $box);

            if ($x < 0 || $y < 0 || $w < 0.005 || $h < 0.005 || $x + $w > 1.00001 || $y + $h > 1.00001) {
                return null;
            }

            $clean[] = [$x, $y, $w, $h];
        }

        return $clean;
    }
}
