@props(['date', 'format' => 'calendar'])

{{-- A follow-up date: a day on the resident's calendar, so it is shown in their date format but never shifted between time zones --}}
<time datetime="{{ $date->toDateString() }}" data-datetime="{{ $date->toDateString() }}T00:00:00Z" data-local-time="{{ $format }}" {{ $attributes }}>{{ match ($format) {
    'calendarshort' => $date->format('D j M'),
    'calendarlong' => $date->format('l j F Y'),
    default => $date->format('j M Y'),
} }}</time>