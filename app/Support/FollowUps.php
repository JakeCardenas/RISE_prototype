<?php

namespace App\Support;

use App\Models\MonitoringPoint;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use DateTimeZone;
use Illuminate\Http\Request;

// Follow-up dates a resident sets for their own points. RISE only compares these dates with the resident's calendar;
// it never decides that a crack needs attention, and it sends no email, SMS or push notifications.
class FollowUps
{
    // A date within this many days shows as "Due soon"
    public const SOON_DAYS = 7;

    // Quick choices offered beside the date picker, in days from today
    public const QUICK_PICKS = [7 => 'In 1 week', 30 => 'In 1 month', 90 => 'In 3 months'];

    // The browser reports its time zone in this cookie, so "today" is the resident's own day
    public const TIME_ZONE_COOKIE = 'rise_tz';

    // Today in the resident's time zone; the app's own time zone until the browser has reported one
    public static function today(?Request $request = null): CarbonImmutable
    {
        $zone = (string) ($request ?? request())->cookie(self::TIME_ZONE_COOKIE);
        $zone = in_array($zone, DateTimeZone::listIdentifiers(), true) ? $zone : config('app.timezone');

        return CarbonImmutable::now($zone)->startOfDay();
    }

    // The server's own checks on a date the resident picks (a date picker's minimum is only a hint): the spot needs its
    // first photo before a follow-up can be planned, and the date can't be in the resident's past or more than five
    // years ahead. A date that was already saved may be sent back unchanged, even after it has passed.
    public static function dateRules(?MonitoringPoint $point, CarbonImmutable $today): array
    {
        return ['bail', 'nullable', 'date_format:Y-m-d', function (string $attribute, mixed $value, Closure $fail) use ($point, $today) {
            if (! $point?->photoRecords()->exists()) {
                $fail('Add the first photo of this spot before planning a follow-up.');
            } elseif ($value === $point->follow_up_on?->toDateString()) {
                return;
            } elseif ($value < $today->toDateString()) {
                $fail('Choose today or a later date.');
            } elseif ($value > $today->addYears(5)->toDateString()) {
                $fail('Choose a date within the next five years.');
            }
        }];
    }

    // A planned follow-up is done when a photo is saved once its day has arrived, and only if the spot already had its
    // baseline: the first photo of a spot is its baseline, never a follow-up
    public static function completedBy(MonitoringPoint $point, bool $hadBaseline, CarbonImmutable $today): bool
    {
        return $hadBaseline && in_array(self::status($point->follow_up_on, $today), ['past', 'today'], true);
    }

    // Whole days from today until the date: 0 is today, negative is past
    public static function daysUntil(CarbonInterface $date, CarbonImmutable $today): int
    {
        return (int) CarbonImmutable::parse($today->toDateString())->diffInDays(CarbonImmutable::parse($date->toDateString()), false);
    }

    // 'past', 'today', 'soon' (within SOON_DAYS) or 'later'; null when the resident hasn't set a date
    public static function status(?CarbonInterface $date, CarbonImmutable $today): ?string
    {
        if (! $date) {
            return null;
        }

        $days = self::daysUntil($date, $today);

        return match (true) {
            $days < 0 => 'past',
            $days === 0 => 'today',
            $days <= self::SOON_DAYS => 'soon',
            default => 'later',
        };
    }

    // The short label shown beside a point: "Past due", "Due today", "Due tomorrow", "Due in 4 days"
    public static function label(CarbonInterface $date, CarbonImmutable $today): string
    {
        $days = self::daysUntil($date, $today);

        return match (true) {
            $days < 0 => 'Past due',
            $days === 0 => 'Due today',
            $days === 1 => 'Due tomorrow',
            default => "Due in {$days} days",
        };
    }
}
