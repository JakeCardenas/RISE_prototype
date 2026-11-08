@props(['time', 'format' => 'datetime'])

{{-- Rendered in UTC; resources/js/rise/local-time.js switches it to the viewer's time zone --}}
<time datetime="{{ $time->toIso8601String() }}" data-local-time="{{ $format }}" {{ $attributes }}>{{ match ($format) {
    'date' => $time->format('j M Y'),
    'short' => $time->format('j M'),
    'month' => $time->format('F Y'),
    'day' => $time->format('d'),
    'monthyear' => $time->format('M Y'),
    'weekdaytime' => $time->format('D H:i').' UTC',
    'stamped' => $time->format('j M Y, H:i').' UTC',
    default => $time->format('j M Y, H:i').' UTC',
} }}</time>