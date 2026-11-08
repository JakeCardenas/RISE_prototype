<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
