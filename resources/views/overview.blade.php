@use('App\Support\FollowUps')
@use('App\Support\HomeAreas')
@use('App\Support\Observations')

@php
    $step = $nextStep['kind'];
    $stepPoint = $nextStep['point'] ?? null;
    $stepLatest = $stepPoint?->latestRecord;
    $areaCount = $areas->keys()->reject(fn ($area) => $area === HomeAreas::UNSORTED)->count();
    $photoAlt = fn ($record, $point, $position) => Observations::label($position).' photo of '.$point->label.', saved '.$record->submitted_at->format('j M Y');
@endphp

<x-layout title="Overview">
    {{-- A compact welcome; the figures are counted from the resident's own records and the dates they chose --}}
    <header class="mb-6 sm:mb-8">
        <h1 class="page-title">Welcome, {{ $firstName }}</h1>
        @if ($points->isEmpty())
            <p class="mt-2 text-[1.0625rem] text-muted">Keep a private, dated photo record of cracks at home.</p>
        @else
            <dl class="mt-5 grid max-w-xl grid-cols-3 gap-4 sm:gap-8">
                @foreach (array_filter([
                    [$points->count(), trans_choice('{1} monitoring point|[2,*] monitoring points', $points->count())],
                    [$photoCount, trans_choice('{1} photo saved|[0,*] photos saved', $photoCount)],
                    $scheduledCount
                        ? [$dueCount, trans_choice('{1} follow-up due|[0,*] follow-ups due', $dueCount)]
                        : ($readyToCompare ? [$readyToCompare, 'ready to compare'] : null),
                ]) as [$value, $label])
                    <div class="flex min-w-0 flex-col-reverse border-l border-line pl-3 first:border-l-0 first:pl-0 sm:pl-6">
                        <dt class="mt-1.5 text-sm leading-snug text-muted">{{ $label }}</dt>
                        <dd class="text-[1.75rem] leading-none font-semibold tracking-[-0.03em] text-heading tabular-nums sm:text-[2rem]">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </header>

    {{-- The one step most worth taking now, chosen from the resident's own records --}}
    <section aria-labelledby="next-step-title" @class(['sheet grid overflow-hidden', 'md:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]' => $step !== 'start', 'lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]' => $step === 'start'])>
        {{-- The picture: the photo to match or compare, or an empty frame where the first photo will go --}}
        @if ($step === 'start')
            <div class="grid-paper flex items-end justify-center bg-well/50 px-6 pt-8 sm:px-12 lg:pt-14" aria-hidden="true">
                <x-house-sketch class="max-w-xs text-heading/75 [--sketch-accent:var(--color-primary)] lg:max-w-md" />
            </div>
        @elseif ($step === 'baseline')
            <a href="{{ route('records.create', ['point' => $stepPoint]) }}" class="hidden items-center justify-center bg-well p-5 sm:p-6 md:flex" tabindex="-1" aria-hidden="true">
                <span class="viewfinder flex aspect-[4/3] w-full max-w-xs flex-col items-center justify-center gap-2 rounded-photo bg-surface text-sm font-semibold text-primary ring-1 ring-line [--vf-color:var(--color-brand-bright)] [--vf-gap:-0.5rem] [--vf-length:0.875rem]">
                    <x-icon name="camera" class="size-7" /> Baseline photo
                </span>
            </a>
        @elseif ($step === 'compare')
            <div class="grid grid-cols-2 items-center gap-3 bg-well p-3 [--photo-max:11rem] sm:p-4 md:[--photo-max:17rem]">
                @foreach ([[$nextStep['baseline'], 1], [$stepLatest, $stepPoint->photo_records_count]] as [$record, $position])
                    <figure class="min-w-0">
                        <x-fitted-photo :record="$record" :alt="$photoAlt($record, $stepPoint, $position)" loading="eager" />
                        <figcaption class="mt-2 text-center text-xs font-semibold text-muted">{{ Observations::label($position) }}</figcaption>
                    </figure>
                @endforeach
            </div>
        @else
            <figure class="flex flex-col justify-center bg-well p-3 [--photo-max:13rem] sm:p-4 md:[--photo-max:21rem]">
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
                    <p class="mt-2.5 max-w-prose">
                        A monitoring point is one crack or surface you want to keep an eye on. Its first photo becomes the baseline;
                        later photos from the same place are follow-ups you can compare with it.
                    </p>
                    <ol class="mt-6 space-y-3.5">
                        @foreach ([
                            ['Name the spot', 'And choose its room, such as Bedroom or Kitchen.'],
                            ['Take a baseline photo', 'From your phone’s camera, or from your computer.'],
                            ['Come back for follow-ups', 'Plan a date if you like. RISE shows your last photo so you can match the framing.'],
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
                        <a href="{{ route('points.create') }}" class="btn btn-primary"><x-icon name="plus" /> Create your first point</a>
                        <p class="meta mt-3">No address or location is needed.</p>
                    </div>
                    @break

                @case('baseline')
                    <h2 id="next-step-title" class="mt-2 text-[1.5rem] leading-tight font-semibold tracking-[-0.025em] break-words text-heading sm:text-[1.75rem]">
                        Add a baseline photo of {{ $stepPoint->label }}
                    </h2>
                    <p class="mt-2.5 max-w-prose text-muted">This spot has no photos yet. Its first photo becomes the reference that later photos are compared with.</p>
                    @break

                @case('due')
                    <h2 id="next-step-title" class="mt-2 text-[1.5rem] leading-tight font-semibold tracking-[-0.025em] break-words text-heading sm:text-[1.75rem]">
                        Follow-up for {{ $stepPoint->label }}
                    </h2>
                    <p class="mt-2.5 flex flex-wrap items-center gap-x-2 gap-y-1.5 text-muted">
                        <x-follow-up-tag :date="$stepPoint->follow_up_on" :today="$today" />
                        <span>You planned it for <x-calendar-date :date="$stepPoint->follow_up_on" format="calendarshort" class="font-semibold text-ink" />.</span>
                    </p>
                    <p class="mt-2 max-w-prose text-muted">Stand where you stood for your last photo, saved <x-local-time :time="$stepLatest->submitted_at" format="date" />, and match its angle and distance.</p>
                    @break

                @case('follow-up')
                    <h2 id="next-step-title" class="mt-2 text-[1.5rem] leading-tight font-semibold tracking-[-0.025em] break-words text-heading sm:text-[1.75rem]">
                        Take the first follow-up of {{ $stepPoint->label }}
                    </h2>
                    <p class="mt-2.5 max-w-prose text-muted">
                        Its baseline was saved <x-local-time :time="$stepLatest->submitted_at" format="date" />. Return to the same spot; RISE shows this photo so you can match the view.
                    </p>
                    @break

                @case('compare')
                    <h2 id="next-step-title" class="mt-2 text-[1.5rem] leading-tight font-semibold tracking-[-0.025em] break-words text-heading sm:text-[1.75rem]">
                        Compare {{ $stepPoint->label }}
                    </h2>
                    <p class="mt-2.5 max-w-prose text-muted">
                        Your latest photo, {{ Observations::label($stepPoint->photo_records_count) }}, was saved
                        {{ Observations::span($nextStep['baseline']->submitted_at, $stepLatest->submitted_at) }} after the baseline.
                        View them side by side or with the slider.
                    </p>
                    @break
            @endswitch

            @if ($stepPoint)
                {{-- Where the point is, and what the resident has planned for it --}}
                @if ($stepPoint->area || ($step !== 'due' && $stepPoint->follow_up_on))
                    <p class="meta mt-3 flex flex-wrap items-center gap-x-2 gap-y-1">
                        @if ($stepPoint->area)
                            <span class="inline-flex items-center gap-1.5"><x-icon name="home" class="size-4" /> {{ $stepPoint->area }}</span>
                        @endif
                        @if ($step !== 'due' && $stepPoint->follow_up_on)
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
                        <a href="{{ route('points.show', $stepPoint) }}" class="btn btn-secondary">Open point</a>
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
                <h2 class="section-title">Good spots to monitor</h2>
                <ul class="mt-3 space-y-2">
                    @foreach (['Bedroom wall beside the window', 'Kitchen ceiling corner', 'Living room wall by the door frame', 'Outside wall near the gate'] as $example)
                        <li class="flex items-start gap-2.5"><x-icon name="check-circle" class="mt-0.5 size-4 text-primary" /> {{ $example }}</li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h2 class="section-title">For photos you can compare</h2>
                <ul class="mt-3 list-disc space-y-2 pl-5 marker:text-primary">
                    <li>Stand in the same place, at the same distance, each time.</li>
                    <li>Hold the camera level and point it straight at the surface.</li>
                    <li>Use even daylight and avoid flash glare.</li>
                </ul>
                <p class="mt-6 flex items-start gap-2 text-sm text-muted">
                    <x-icon name="lock" class="mt-0.5 size-4 text-primary" />
                    Your photos are private to your account. Nothing is sent for AI analysis unless you ask.
                </p>
            </div>
        </section>
    @else
        {{-- Follow-ups the resident planned: only points they gave a date, and only once that day is near or has passed --}}
        @if ($reminders->isNotEmpty())
            <section id="follow-ups" aria-labelledby="follow-ups-heading" class="mt-10 scroll-mt-28">
                <div class="section-head">
                    <h2 id="follow-ups-heading" class="section-title">Planned follow-ups</h2>
                    <p class="meta">Dates you chose</p>
                </div>
                <ul role="list" class="sheet mt-4 divide-y divide-line px-4 sm:px-6">
                    @foreach ($reminders as $point)
                        <li class="flex flex-wrap items-center gap-x-6 gap-y-3 py-4">
                            <div class="min-w-0 flex-1 basis-60">
                                <p class="flex flex-wrap items-center gap-2">
                                    <x-follow-up-tag :date="$point->follow_up_on" :today="$today" />
                                    <x-calendar-date :date="$point->follow_up_on" format="calendarshort" class="meta" />
                                </p>
                                <h3 class="mt-1.5 font-semibold break-words text-heading">
                                    <a href="{{ route('points.show', $point) }}" class="hover:underline">{{ $point->label }}</a>
                                </h3>
                                <p class="meta">
                                    {{ $point->area ? $point->area.' · ' : '' }}{{ trans_choice('{0} No photos yet|{1} 1 photo|[2,*] :count photos', $point->photo_records_count) }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <a href="{{ route('records.create', ['point' => $point]) }}" class="btn btn-secondary min-h-10 px-4 text-sm">
                                    <x-icon name="camera" class="size-4" /> {{ $point->photo_records_count ? 'Add follow-up photo' : 'Add baseline photo' }}
                                </a>
                                <a href="{{ route('points.show', $point) }}" class="btn btn-quiet min-h-10 px-4 text-sm">Open <span class="sr-only">{{ $point->label }}</span></a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @elseif ($scheduledCount === 0 && $photoCount > 0)
            <p class="mt-5 flex items-start gap-2 text-sm text-muted">
                <x-icon name="calendar" class="mt-0.5 size-4 shrink-0 text-primary" />
                Plan a follow-up date on any point’s page, and it will appear here as the day gets close.
            </p>
        @endif

        {{-- The latest photos across all points, whole and uncropped, with where each sits in its point's history --}}
        @if ($recent->isNotEmpty())
            <section aria-labelledby="recent-heading" class="mt-12">
                <div class="section-head">
                    <h2 id="recent-heading" class="section-title">Recent observations</h2>
                    <p class="meta">Newest first</p>
                </div>
                <ol role="list" class="strip mt-4 -mx-4 scroll-px-4 px-4 sm:mx-0 sm:scroll-px-0 sm:px-0 md:grid md:grid-cols-3 md:gap-4 md:overflow-visible md:pb-0">
                    @foreach ($recent as ['record' => $record, 'point' => $point, 'position' => $position])
                        <li class="w-[15.5rem] shrink-0 md:w-auto">
                            <a href="{{ Observations::entryUrl($point, $record, $position) }}" class="frame-link sheet block h-full overflow-hidden rounded-sheet">
                                <span class="flex h-44 items-center justify-center bg-well p-2.5 [--photo-max:9.75rem]">
                                    <x-fitted-photo :record="$record" :alt="$photoAlt($record, $point, $position)" />
                                </span>
                                <span class="block p-4">
                                    <span class="flex flex-wrap items-center gap-x-1.5 text-sm">
                                        <span class="font-semibold text-heading">{{ Observations::label($position) }}</span>
                                        <span class="text-muted">· <x-local-time :time="$record->submitted_at" format="date" /></span>
                                    </span>
                                    <span class="mt-1 block font-semibold break-words text-heading">{{ $point->label }}</span>
                                    <span class="meta mt-0.5 block">
                                        {{ $position > 1 ? Str::ucfirst(Observations::span($point->photo_records_min_submitted_at, $record->submitted_at)).' after the baseline' : 'First photo of this spot' }}{{ $point->area ? ' · '.$point->area : '' }}
                                    </span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        {{-- Points by room or area, so any spot is a tap away --}}
        <section id="areas" aria-labelledby="areas-heading" class="mt-12 scroll-mt-28">
            <div class="section-head">
                <h2 id="areas-heading" class="section-title">My home areas</h2>
                <a href="{{ route('points.create') }}" class="btn btn-quiet -mr-3 -mb-1.5 text-sm">
                    <x-icon name="plus" class="size-4" /> New point
                </a>
            </div>

            @if ($areas->count() > 1)
                <nav aria-label="Jump to an area" class="mt-3 flex flex-wrap gap-2">
                    @foreach ($areas as $area => $group)
                        <a href="#{{ HomeAreas::anchor($area) }}" class="glass-thin inline-flex min-h-10 items-center gap-2 rounded-full px-3.5 text-sm font-semibold text-ink">
                            {{ $area }} <span class="text-muted tabular-nums">{{ $group->count() }}</span>
                        </a>
                    @endforeach
                </nav>
            @elseif ($areaCount === 0)
                <p class="meta mt-1">Choose a room for each point when you edit it, and they’ll be grouped here.</p>
            @endif

            <div class="mt-4 grid items-start gap-4 lg:grid-cols-2">
                @foreach ($areas as $area => $group)
                    <section id="{{ HomeAreas::anchor($area) }}" aria-labelledby="{{ HomeAreas::anchor($area) }}-title" class="sheet scroll-mt-28 overflow-hidden">
                        <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3 sm:px-5">
                            <h3 id="{{ HomeAreas::anchor($area) }}-title" class="flex items-center gap-2 font-semibold text-heading">
                                <x-icon name="home" class="size-4 text-primary" /> {{ $area }}
                            </h3>
                            <p class="meta">{{ trans_choice('{1} 1 point|[2,*] :count points', $group->count()) }}</p>
                        </div>
                        <ul role="list" class="divide-y divide-line">
                            @foreach ($group as $point)
                                <li>
                                    <a href="{{ route('points.show', $point) }}" class="flex items-center gap-4 px-4 py-3.5 hover:bg-well/60 sm:px-5">
                                        <span class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-well-strong text-primary">
                                            @if ($point->latestRecord)
                                                <x-record-photo :record="$point->latestRecord" :alt="''" class="size-full object-contain" />
                                            @else
                                                <x-icon name="camera" class="size-5" />
                                            @endif
                                        </span>
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
                                            @if ($point->follow_up_on)
                                                <span class="mt-1.5 flex flex-wrap items-center gap-2 text-sm">
                                                    @if (FollowUps::status($point->follow_up_on, $today) === 'later')
                                                        <span class="meta inline-flex items-center gap-1.5"><x-icon name="calendar" class="size-4" /> Follow-up <x-calendar-date :date="$point->follow_up_on" format="calendarshort" /></span>
                                                    @else
                                                        <x-follow-up-tag :date="$point->follow_up_on" :today="$today" />
                                                    @endif
                                                </span>
                                            @endif
                                        </span>
                                        <x-icon name="chevron-right" class="size-4 shrink-0 text-muted" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        @unless ($area === HomeAreas::UNSORTED)
                            <a href="{{ route('points.create', ['area' => $area]) }}" class="flex min-h-11 items-center gap-2 border-t border-line px-4 text-sm font-semibold text-primary hover:bg-well/60 sm:px-5">
                                <x-icon name="plus" class="size-4" /> Add a point in {{ $area }}
                            </a>
                        @endunless
                    </section>
                @endforeach
            </div>
        </section>
    @endif
</x-layout>
