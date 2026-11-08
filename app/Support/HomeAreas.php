<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// The rooms and parts of a home a resident can file monitoring points under. Only a room or area name is kept;
// RISE never asks for an address, a unit number or a location.
class HomeAreas
{
    public const PRESETS = ['Bedroom', 'Living room', 'Kitchen', 'Hallway', 'Bathroom', 'Exterior'];

    // The heading for points the resident hasn't filed under a room or area, shown once some other spot has one
    public const UNSORTED = 'Other spots';

    // Groups points by area: the preset rooms in their usual order, then the resident's own names, then unsorted points
    public static function group(Collection $points): Collection
    {
        $presets = array_map(self::key(...), self::PRESETS);

        return $points
            ->groupBy(fn ($point) => self::key($point->area))
            ->sortBy(function (Collection $group, string $key) use ($presets) {
                $preset = array_search($key, $presets, true);

                return match (true) {
                    $key === '' => '2',
                    $preset !== false => sprintf('0%02d', $preset),
                    default => '1'.$key,
                };
            }, SORT_STRING) // as text: compared as numbers, "002" and "2" tie and unsorted points could land mid-list
            ->mapWithKeys(fn (Collection $group, string $key) => [$key === '' ? self::UNSORTED : self::name($group->first()->area) => $group->values()]);
    }

    // An anchor for an area's group on the overview
    public static function anchor(string $area): string
    {
        return 'area-'.(Str::slug($area) ?: 'other');
    }

    // Presets are matched whatever their letter case, so "kitchen" files under "Kitchen"
    public static function name(?string $area): ?string
    {
        $area = trim((string) $area);

        if ($area === '') {
            return null;
        }

        foreach (self::PRESETS as $preset) {
            if (self::key($preset) === self::key($area)) {
                return $preset;
            }
        }

        return $area;
    }

    private static function key(?string $area): string
    {
        return Str::lower(trim((string) $area));
    }
}
