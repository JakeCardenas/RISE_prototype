@use('App\Support\FollowUps')

{{-- Felix's answer to "What should I do next?": one step from the resident's own spots, always about using RISE --}}
@php
    $point = $step['point'] ?? null;
    $days = $step['kind'] === 'due' ? FollowUps::daysUntil($point->follow_up_on, $today) : null;
@endphp

@switch($step['kind'])
    @case('start')
        <p>Let’s make your first spot! Pick one crack you’d like to watch and give it a name.</p>
        <div class="helper-actions">
            <a href="{{ route('points.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> New spot</a>
        </div>
        @break

    @case('baseline')
        <p>“{{ $point->label }}” needs its first photo. That one becomes the baseline.</p>
        <div class="helper-actions">
            <a href="{{ route('records.create', ['point' => $point]) }}" class="btn btn-primary"><x-icon name="camera" class="size-4" /> Add photo</a>
        </div>
        @break

    @case('due')
        <p>
            Your follow-up for “{{ $point->label }}” is due{{ $days < 0 ? ' (planned for '.$point->follow_up_on->format('D j M').')' : ' today' }}.
            @if ($step['moreDue'])
                {{ trans_choice('{1} 1 more is due too.|[2,*] :count more are due too.', $step['moreDue']) }}
            @endif
        </p>
        <div class="helper-actions">
            <a href="{{ route('records.create', ['point' => $point]) }}" class="btn btn-primary"><x-icon name="camera" class="size-4" /> Add follow-up</a>
        </div>
        @break

    @case('follow-up')
        <p>“{{ $point->label }}” has its baseline. Next, take a follow-up from the same place.</p>
        <div class="helper-actions">
            <a href="{{ route('records.create', ['point' => $point]) }}" class="btn btn-primary"><x-icon name="camera" class="size-4" /> Add follow-up</a>
            @unless ($point->follow_up_on)
                <a href="{{ route('points.show', $point) }}#follow-up" class="btn btn-secondary"><x-icon name="calendar" class="size-4" /> Plan a date</a>
            @endunless
        </div>
        @break

    @default
        <p>“{{ $point->label }}” has {{ $point->photo_records_count }} photos. Compare the first and latest to see what looks different.</p>
        <div class="helper-actions">
            <a href="{{ route('points.compare', $point) }}" class="btn btn-primary"><x-icon name="split" class="size-4" /> Compare photos</a>
        </div>
@endswitch
