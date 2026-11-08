@use('App\Support\FollowUps')
@use('App\Support\HomeAreas')
@use('App\Support\Observations')

@php
    $step = $nextStep['kind'];
    $stepPoint = $nextStep['point'] ?? null;
    $stepLatest = $stepPoint?->latestRecord;
    $areaCount = $areas->keys()->reject(fn ($area) => $area === HomeAreas::UNSORTED)->count();
    $photoAlt = fn ($record, $point, $position) => Observations::label($position).' photo of '.$point->label.', saved '.$record->submitted_at->format('j M Y');

    // The next-step card offers "Plan a follow-up date" itself in these cases, so the general hint below stays away
    $offersPlanning = $stepPoint && in_array($step, ['follow-up', 'compare'], true) && ! $stepPoint->follow_up_on;

    // Photos already in the next-step card aren't shown again under Recent photos
    $featured = collect([$stepLatest?->id, ($nextStep['baseline'] ?? null)?->id])->filter();
    $recent = $recent->reject(fn ($item) => $featured->contains($item['record']->id))->values();

    // A few figures, each counted from the resident's own records and the dates they chose
    $figures = collect([
        [$points->count(), trans_choice('{1} spot|[0,*] spots', $points->count())],
        [$photoCount, trans_choice('{1} photo|[0,*] photos', $photoCount)],
    ]);
    if ($pastDueCount) {
        $figures->push([$pastDueCount, 'past due']);
    }
    if ($dueTodayCount) {
        $figures->push([$dueTodayCount, 'due today']);
    }
    if (! $pastDueCount && ! $dueTodayCount && $nextPlanned) {
        $figures->push(['date' => $nextPlanned, 'label' => $upcomingCount > 1 ? "next follow-up · {$upcomingCount} planned" : 'next follow-up']);
    } elseif (! $pastDueCount && ! $dueTodayCount && $readyToCompare) {
        $figures->push([$readyToCompare, 'ready to compare']);
    }
@endphp

<x-layout title="Overview">
    <header class="mb-6 sm:mb-8">
        <h1 class="page-title">Welcome, {{ $firstName }}</h1>
        @if ($points->isEmpty())
            <p class="mt-2 text-[1.0625rem] text-muted">Track cracks over time, one photo at a time.</p>
        @else
            <dl class="mt-5 flex flex-wrap gap-x-8 gap-y-4">
                @foreach ($figures as $figure)
                    <div class="flex min-w-0 flex-col-reverse">
                        <dt class="mt-1 text-sm leading-snug text-muted">{{ $figure['label'] ?? $figure[1] }}</dt>
                        <dd class="leading-none font-semibold tracking-[-0.03em] text-heading tabular-nums">
                            @isset($figure['date'])
                                <x-calendar-date :date="$figure['date']" format="calendarshort" class="text-[1.375rem] sm:text-[1.625rem]" />
                            @else
                                <span class="text-[1.75rem] sm:text-[2rem]">{{ $figure[0] }}</span>
                            @endisset
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </header>

    {{-- The one step most worth taking now, chosen from the resident's own records --}}
    <section aria-labelledby="next-step-title" @class(['sheet grid overflow-hidden', 'md:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]' => $step !== 'start', 'lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]' => $step === 'start'])>
        {{-- The picture: the photo to match or compare; before there is one, a drawing of a spot framed on a house wall, never an empty frame --}}
        @if ($step === 'start')
            <div class="grid-paper hidden items-end justify-center bg-well/50 px-6 pt-8 sm:flex sm:px-12 lg:pt-14" aria-hidden="true">
                <x-house-sketch class="max-w-xs text-heading/75 [--sketch-accent:var(--color-primary)] lg:max-w-md" />
            </div>
        @elseif ($step === 'baseline')
            <div class="grid-paper hidden items-end justify-center bg-well/50 px-8 pt-8 md:flex lg:px-12" aria-hidden="true">
                <x-house-sketch class="max-w-xs text-heading/75 [--sketch-accent:var(--color-primary)]" />
            </div>
        @elseif ($step === 'compare')
            <div class="grid grid-cols-2 items-center gap-3 bg-well p-3 [--photo-max:10rem] sm:p-4 md:[--photo-max:17rem]">
                @foreach ([[$nextStep['baseline'], 1], [$stepLatest, $stepPoint->photo_records_count]] as [$record, $position])
                    <figure class="min-w-0">
                        <x-fitted-photo :record="$record" :alt="$photoAlt($record, $stepPoint, $position)" loading="eager" />
                        <figcaption class="mt-2 text-center text-xs font-semibold text-muted">{{ Observations::label($position) }}</figcaption>
                    </figure>
                @endforeach
            </div>
        @else
            <figure class="flex flex-col justify-center bg-well p-3 [--photo-max:12rem] sm:p-4 md:[--photo-max:21rem]">
                <x-fitted-photo :record="$stepLatest" :alt="$photoAlt($stepLatest, $stepPoint, $stepPoint->photo_records_count)" loading="eager" />
                <figcaption class="mt-2 text-center text-xs font-semibold text-muted">Your last photo of this spot</figcaption>
            </figure>
        @endif

        <div class="flex min-w-0 flex-col justify-center p-5 sm:p-7 lg:p-9">
            <p class="flex items-center gap-2 text-sm font-semibold text-muted">
                <span class="size-2 rounded-full bg-accent" aria-hidden="true"></span> Next step
            </p>

            @switch($step)
                @case('start')
                    <h2 id="next-step-title" class="mt-2 text-[1.625rem] leading-tight font-semibold tracking-[-0.025em] text-heading sm:text-[1.875rem]">Start with one spot</h2>
                    <p class="mt-2.5 max-w-prose text-muted">Pick one crack or surface to watch. Its first photo is the baseline; later photos are follow-ups you compare with it.</p>
                    <ol class="mt-6 space-y-3.5">
                        @foreach ([
                            ['Name the spot', 'And pick its room.'],
                            ['Take a baseline photo', 'With your phone or computer.'],
                            ['Come back for follow-ups', 'From the same place. You can plan a date.'],
                        ] as [$title, $text])
                            <li class="flex gap-3.5">
                                <span class="step-number">{{ $loop->iteration }}</span>
                                <div>
                                    <p class="font-semibold">{{ $title }}</p>
                                    <p class="text-sm text-muted">{{ $text }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <div class="mt-7">
                        <a href="{{ route('points.create') }}" class="btn btn-primary"><x-icon name="plus" /> Create your first spot</a>
                        <p class="meta mt-3">No address or location needed.</p>
                    </div>
                    @break

                @case('baseline')
                    <h2 id="next-step-title" class="mt-2 text-[1.5rem] leading-tight font-semibold tracking-[-0.025em] break-words text-heading sm:text-[1.75rem]">
                        Add a baseline photo of {{ $stepPoint->label }}
                    </h2>
                    <p class="mt-2.5 max-w-prose text-muted">This first photo is the one later photos are compared with.</p>
                    @break

                @case('due')
                    <h2 id="next-step-title" class="mt-2 text-[1.5rem] leading-tight font-semibold tracking-[-0.025em] break-words text-heading sm:text-[1.75rem]">
                        Follow-up for {{ $stepPoint->label }}
                    </h2>
                    <p class="mt-2.5 flex flex-wrap items-center gap-x-2 gap-y-1.5 text-muted">
                        <x-follow-up-tag :date="$stepPoint->follow_up_on" :today="$today" />
                        <span>You planned it for <x-calendar-date :date="$stepPoint->follow_up_on" format="calendarshort" class="font-semibold text-ink" />.</span>
                    </p>
                    <p class="mt-2 max-w-prose text-muted">Stand where you took your last photo, on <x-local-time :time="$stepLatest->submitted_at" format="date" />, and match its angle and distance.</p>
                    @break

                @case('follow-up')
                    <h2 id="next-step-title" class="mt-2 text-[1.5rem] leading-tight font-semibold tracking-[-0.025em] break-words text-heading sm:text-[1.75rem]">
                        Take the first follow-up of {{ $stepPoint->label }}
                    </h2>
                    <p class="mt-2.5 max-w-prose text-muted">
                        Its baseline is from <x-local-time :time="$stepLatest->submitted_at" format="date" />. RISE shows that photo so you can match the view.
                    </p>
                    @break

                @case('compare')
                    <h2 id="next-step-title" class="mt-2 text-[1.5rem] leading-tight font-semibold tracking-[-0.025em] break-words text-heading sm:text-[1.75rem]">
                        Compare {{ $stepPoint->label }}
                    </h2>
                    <p class="mt-2.5 max-w-prose text-muted">
                        Your latest photo, {{ Observations::label($stepPoint->photo_records_count) }}, was saved
                        {{ Observations::span($nextStep['baseline']->submitted_at, $stepLatest->submitted_at) }} after the baseline.
                    </p>
                    @break
            @endswitch

            @if ($stepPoint)
                {{-- Where the spot is, and what the resident has planned for it --}}
                @if ($stepPoint->area || ($step !== 'due' && $stepPoint->follow_up_on && $stepPoint->photo_records_count))
                    <p class="meta mt-3 flex flex-wrap items-center gap-x-2 gap-y-1">
                        @if ($stepPoint->area)
                            <span class="inline-flex items-center gap-1.5"><x-icon name="home" class="size-4" /> {{ $stepPoint->area }}</span>
                        @endif
                        @if ($step !== 'due' && $stepPoint->follow_up_on && $stepPoint->photo_records_count)
                            @if ($stepPoint->area) <span aria-hidden="true">·</span> @endif
                            <span class="inline-flex items-center gap-1.5"><x-icon name="calendar" class="size-4" /> Follow-up planned for <x-calendar-date :date="$stepPoint->follow_up_on" format="calendarshort" /></span>
                        @endif
                    </p>
                @endif

                <div class="mt-6 flex flex-wrap gap-2">
                    @if ($step === 'compare')
                        <a href="{{ route('points.compare', [$stepPoint, 'first' => $nextStep['baseline']->id, 'second' => $stepLatest->id]) }}" class="btn btn-primary">
                            <x-icon name="split" /> Compare photos
                        </a>
                        <a href="{{ route('records.create', ['point' => $stepPoint]) }}" class="btn btn-secondary"><x-icon name="camera" class="size-4" /> Add follow-up photo</a>
                    @else
                        <a href="{{ route('records.create', ['point' => $stepPoint]) }}" class="btn btn-primary">
                            <x-icon name="camera" /> {{ $step === 'baseline' ? 'Add baseline photo' : 'Add follow-up photo' }}
                        </a>
                        <a href="{{ route('points.show', $stepPoint) }}" class="btn btn-secondary">Open spot</a>
                    @endif
                </div>

                @if ($step === 'due' && $nextStep['moreDue'])
                    <a href="#follow-ups" class="link mt-4 inline-flex min-h-11 items-center text-sm">
                        {{ trans_choice('{1} 1 more follow-up is due|[2,*] :count more follow-ups are due', $nextStep['moreDue']) }}
                    </a>
                @elseif (in_array($step, ['follow-up', 'compare'], true) && ! $stepPoint->follow_up_on)
                    <a href="{{ route('points.show', $stepPoint) }}#follow-up" class="link mt-4 inline-flex min-h-11 items-center gap-1.5 text-sm">
                        <x-icon name="calendar" class="size-4" /> Plan a follow-up date
                    </a>
                @endif
            @endif
        </div>
    </section>

    @if ($points->isEmpty())
        <section aria-label="Tips" class="mt-12 grid gap-x-12 gap-y-8 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <div>
                <h2 class="section-title">Good spots to watch</h2>
                <ul class="mt-3 space-y-2">
                    @foreach (['Bedroom wall beside the window', 'Kitchen ceiling corner', 'Living room wall by the door frame', 'Outside wall near the gate'] as $example)
                        <li class="flex items-start gap-2.5"><x-icon name="check-circle" class="mt-0.5 size-4 text-primary" /> {{ $example }}</li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h2 class="section-title">For photos you can compare</h2>
                <ul class="mt-3 list-disc space-y-2 pl-5 marker:text-primary">
                    <li>Stand in the same place, at the same distance.</li>
                    <li>Hold the camera level, facing the surface.</li>
                    <li>Use even daylight and no flash.</li>
                </ul>
                <p class="mt-6 flex items-start gap-2 text-sm text-muted">
                    <x-icon name="lock" class="mt-0.5 size-4 text-primary" />
                    Your photos are private. Nothing goes to an AI service unless you ask.
                </p>
            </div>
        </section>
    @else
        {{-- Follow-ups the resident planned: only spots they gave a date, and only once that day is near or has passed --}}
        @if ($reminders->isNotEmpty())
            <section id="follow-ups" aria-labelledby="follow-ups-heading" class="mt-10 scroll-mt-28">
                <div class="section-head">
                    <h2 id="follow-ups-heading" class="section-title">Planned follow-ups</h2>
                    <p class="meta">Dates you chose</p>
                </div>
                <ul role="list" class="sheet mt-4 divide-y divide-line px-4 sm:px-6">
                    @foreach ($reminders as $point)
                        <li class="flex flex-wrap items-center gap-x-6 gap-y-2.5 py-3.5">
                            <div class="min-w-0 flex-1 basis-60">
                                <p class="flex flex-wrap items-center gap-2">
                                    <x-follow-up-tag :date="$point->follow_up_on" :today="$today" />
                                    <x-calendar-date :date="$point->follow_up_on" format="calendarshort" class="meta" />
                                </p>
                                <h3 class="mt-1 font-semibold break-words text-heading">
                                    <a href="{{ route('points.show', $point) }}" class="hover:underline">{{ $point->label }}</a>
                                </h3>
                                @if ($point->area)
                                    <p class="meta">{{ $point->area }}</p>
                                @endif
                            </div>
                            <a href="{{ route('records.create', ['point' => $point]) }}" class="btn btn-secondary min-h-10 px-4 text-sm">
                                <x-icon name="camera" class="size-4" /> Add follow-up <span class="sr-only">of {{ $point->label }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @elseif ($planned->isEmpty() && $photoCount > 0 && ! $offersPlanning)
            <p class="mt-5 flex items-start gap-2 text-sm text-muted">
                <x-icon name="calendar" class="mt-0.5 size-4 shrink-0 text-primary" />
                Plan a follow-up date on a spot’s page, and it shows here when the day is close.
            </p>
        @endif

        {{-- Other recent photos, small and whole: the next step above stays the main thing to do --}}
        @if ($recent->isNotEmpty())
            <section aria-labelledby="recent-heading" class="mt-10">
                <div class="section-head">
                    <h2 id="recent-heading" class="section-title">Recent photos</h2>
                    <p class="meta">Newest first</p>
                </div>
                <ol role="list" class="strip mt-4 -mx-4 scroll-px-4 px-4 sm:mx-0 sm:scroll-px-0 sm:px-0 md:grid md:grid-cols-3 md:gap-3 md:overflow-visible md:pb-0 lg:grid-cols-6">
                    @foreach ($recent as ['record' => $record, 'point' => $point, 'position' => $position])
                        <li class="w-36 shrink-0 md:w-auto">
                            <a href="{{ Observations::entryUrl($point, $record, $position) }}" class="frame-link sheet block h-full overflow-hidden rounded-[1.125rem]">
                                <span class="flex h-28 items-center justify-center bg-well p-2 [--photo-max:6rem]">
                                    <x-fitted-photo :record="$record" :alt="$photoAlt($record, $point, $position)" />
                                </span>
                                <span class="block px-3 py-2.5">
                                    <span class="block truncate text-sm font-semibold text-heading">{{ $point->label }}</span>
                                    <span class="block truncate text-xs text-muted">{{ Observations::label($position) }} · <x-local-time :time="$record->submitted_at" format="short" /></span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        {{-- Every spot, a tap away. Once the resident has chosen rooms, spots are grouped by room; any without one
             gather under "Other spots". Until then they are one plain list, with a note on how grouping works. --}}
        <section id="spots" aria-labelledby="spots-heading" class="mt-10 scroll-mt-28">
            <div class="section-head">
                <h2 id="spots-heading" class="section-title">Your spots</h2>
                <a href="{{ route('points.create') }}" class="btn btn-quiet -mr-3 -mb-1.5 text-sm">
                    <x-icon name="plus" class="size-4" /> New spot
                </a>
            </div>

            @if ($areaCount === 0)
                <p class="meta mt-1">Choose a room when you edit a spot, and your spots are grouped by room here.</p>
                <div class="mt-4 grid items-start gap-3 lg:grid-cols-2">
                    <ul role="list" class="sheet divide-y divide-line overflow-hidden">
                        @foreach ($areas->first() as $point)
                            <li><x-spot-row :point="$point" :today="$today" /></li>
                        @endforeach
                    </ul>
                </div>
            @else
                @if ($areas->count() > 1)
                    <nav aria-label="Jump to a room" class="mt-3 flex flex-wrap gap-2">
                        @foreach ($areas as $area => $group)
                            <a href="#{{ HomeAreas::anchor($area) }}" class="glass-thin inline-flex min-h-10 items-center gap-2 rounded-full px-3.5 text-sm font-semibold text-ink">
                                {{ $area }} <span class="text-muted tabular-nums">{{ $group->count() }}</span>
                            </a>
                        @endforeach
                    </nav>
                @endif

                <div class="mt-4 grid items-start gap-3 lg:grid-cols-2">
                    @foreach ($areas as $area => $group)
                        @php($unsorted = $area === HomeAreas::UNSORTED)
                        <section id="{{ HomeAreas::anchor($area) }}" aria-labelledby="{{ HomeAreas::anchor($area) }}-title" class="sheet scroll-mt-28 overflow-hidden">
                            <div class="flex min-h-12 items-center justify-between gap-3 border-b border-line py-1.5 pr-2 pl-4 sm:pl-5">
                                <h3 id="{{ HomeAreas::anchor($area) }}-title" class="flex items-center gap-2 font-semibold text-heading">
                                    <x-icon :name="$unsorted ? 'list' : 'home'" class="size-4 text-primary" /> {{ $area }}
                                    <span class="text-sm font-normal text-muted tabular-nums">{{ $group->count() }}</span>
                                </h3>
                                @if ($unsorted)
                                    <p class="meta pr-3">No room chosen</p>
                                @else
                                    <a href="{{ route('points.create', ['area' => $area]) }}" class="btn btn-quiet min-h-10 px-3 text-sm">
                                        <x-icon name="plus" class="size-4" /> Add <span class="sr-only">a spot in {{ $area }}</span>
                                    </a>
                                @endif
                            </div>
                            <ul role="list" class="divide-y divide-line">
                                @foreach ($group as $point)
                                    <li><x-spot-row :point="$point" :today="$today" /></li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</x-layout>
