@use('App\Support\FollowUps')
@props(['point', 'today'])

{{-- One spot in an Overview list: its name, its photos and any follow-up date the resident planned --}}
<a href="{{ route('points.show', $point) }}" {{ $attributes->merge(['class' => 'flex min-h-14 items-center gap-3 px-4 py-2.5 hover:bg-well/60 sm:px-5']) }}>
    <span class="min-w-0 flex-1">
        <span class="block font-semibold break-words text-heading">{{ $point->label }}</span>
        <span class="meta block">
            @if ($point->latestRecord)
                {{ trans_choice('{1} 1 photo|[2,*] :count photos', $point->photo_records_count) }}
                · latest <x-local-time :time="$point->latestRecord->submitted_at" format="short" />
            @else
                No photos yet
            @endif
        </span>
    </span>
    @if ($point->follow_up_on && $point->photo_records_count)
        @if (FollowUps::status($point->follow_up_on, $today) === 'later')
            <span class="meta inline-flex shrink-0 items-center gap-1.5"><x-icon name="calendar" class="size-4" /> <x-calendar-date :date="$point->follow_up_on" format="calendarshort" /></span>
        @else
            <x-follow-up-tag :date="$point->follow_up_on" :today="$today" class="shrink-0" />
        @endif
    @endif
    <x-icon name="chevron-right" class="size-4 shrink-0 text-muted" />
</a>
