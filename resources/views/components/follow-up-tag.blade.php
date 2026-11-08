@props(['date', 'today'])

@php
    // Only ever about the date the resident chose: orange marks today, as it marks "now" elsewhere; a missed date is
    // dark rather than red, because RISE doesn't judge how urgent a crack is
    $status = \App\Support\FollowUps::status($date, $today);
@endphp

<span {{ $attributes->class(['tag gap-1', 'tag-accent' => $status === 'today', 'tag-past' => $status === 'past']) }}>
    <x-icon name="calendar" class="size-3.5" /> {{ \App\Support\FollowUps::label($date, $today) }}
</span>
