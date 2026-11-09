@props([
    'statusUrl',
    'stage',
    'elapsed' => 0,
    'provider',
    'requestedAt',
    'heading' => 'Analyzing photo…',
    'headingId' => null,
    'level' => 2,
    'subject' => 'the photo',
    'stillWaiting' => false,
])

@php
    // The real stages, as the server reports them; the spinner and bar only show that work is going on
    $steps = [
        'queued' => ['Waiting for the RISE queue', 'Request received. Waiting for the RISE queue.'],
        'sending' => ['Sending '.$subject.' to '.$provider, 'Sending '.$subject.' to '.$provider.'.'],
        'waiting' => ['Waiting for '.$provider.'’s result', Str::ucfirst($subject).' '.($subject === 'the photo' ? 'has' : 'have').' been sent. Waiting for '.$provider.'’s result.'],
    ];
    $current = array_search($stage, array_keys($steps), true);
    $current = $current === false ? 0 : $current;
    $clock = fn (int $seconds) => $seconds >= 3600 ? gmdate('G:i:s', $seconds) : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
@endphp

{{-- A live view of one AI request: updated by analysis-status.js, and complete without it --}}
<div {{ $attributes }} data-analysis-waiting data-status-url="{{ $statusUrl }}" data-stage="{{ $stage }}" data-elapsed="{{ $elapsed }}">
    <div class="flex items-center gap-3">
        <span class="ai-spinner" aria-hidden="true"></span>
        <h{{ $level }} @if ($headingId) id="{{ $headingId }}" @endif @class(['section-title' => $level === 2, 'font-semibold text-heading' => $level !== 2])>{{ $heading }}</h{{ $level }}>
    </div>

    <div class="ai-bar mt-5" role="progressbar" aria-label="{{ Str::before($heading, '…') }}" aria-valuetext="{{ array_values($steps)[$current][0] }}" data-progress></div>

    <ol class="ai-steps mt-5" aria-label="Progress">
        <li data-step-state="done">
            <span class="ai-step-mark" aria-hidden="true"><x-icon name="check-circle" class="is-done size-[1.125rem] text-success" /></span>
            <span>Request received <x-local-time :time="$requestedAt" class="meta ml-1" /></span>
        </li>
        @foreach ($steps as $key => [$label, $sentence])
            @php($state = $loop->index < $current ? 'done' : ($loop->index === $current ? 'current' : 'todo'))
            <li data-step="{{ $key }}" data-step-state="{{ $state }}" data-announce="{{ $sentence }}">
                <span class="ai-step-mark" aria-hidden="true">
                    <x-icon name="check-circle" class="is-done size-[1.125rem] text-success" />
                    <span class="is-current ai-spinner ai-spinner-sm"></span>
                    <span class="is-todo size-2.5 rounded-full border-2 border-line-strong"></span>
                </span>
                <span><span data-step-label>{{ $label }}</span><span class="sr-only" data-step-sr>{{ ['done' => ', done', 'current' => ', in progress', 'todo' => ''][$state] }}</span></span>
            </li>
        @endforeach
    </ol>

    <p class="mt-5 text-sm">
        <span class="font-semibold text-ink">Elapsed</span>
        <span class="tabular-nums" data-elapsed-text>{{ $clock((int) $elapsed) }}</span>
    </p>
    <p class="mt-1.5 text-sm text-muted">This usually takes under a minute. You can leave this page and come back later; the result will be saved here.</p>

    {{-- Announced when the stage changes; the ticking clock isn't --}}
    <p class="sr-only" role="status" data-stage-announcer>{{ array_values($steps)[$current][1] }}</p>

    <div class="mt-4 rounded-control border border-line bg-canvas px-3.5 py-3 text-sm" data-poll-problem hidden>
        <p>
            <strong class="font-semibold text-ink">Couldn’t refresh the status.</strong>
            <span data-poll-detail>This page will keep trying. The analysis itself may still be running.</span>
        </p>
        <button type="button" class="btn btn-quiet mt-1 -ml-2" data-poll-now><x-icon name="refresh" class="size-4" /> Check now</button>
    </div>

    <p class="mt-4 text-sm" data-still-waiting @unless ($stillWaiting) hidden @endunless>
        Still waiting in the queue. It may not be running; the person who runs RISE can start it with
        <code class="rounded bg-well px-1 py-0.5 text-[0.8125rem]">php artisan queue:work</code>.
    </p>

    {{ $slot }}
</div>
