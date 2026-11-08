@props(['time', 'size' => 'md'])

{{-- A date stamp: the day large, with the month, year, weekday and time beside it, in the viewer's time zone --}}
<span {{ $attributes->class(['stamp', 'stamp-lg' => $size === 'lg', 'stamp-sm' => $size === 'sm']) }}>
    <x-local-time :time="$time" format="day" class="stamp-day" />
    <span class="stamp-side">
        <x-local-time :time="$time" format="monthyear" class="stamp-month" />
        <x-local-time :time="$time" format="weekdaytime" class="stamp-time" />
    </span>
</span>
