@use('App\Support\FollowUps')
@use('App\Support\Observations')

@php
    $items = collect($records->items());
    $hasFollowUp = $latest && $baseline && ! $latest->is($baseline);
    $apart = $hasFollowUp
        ? Observations::span($baseline->submitted_at, $latest->submitted_at)
        : null;
    $addUrl = route('records.create', ['point' => $point]);
    $today = FollowUps::today();
    $followUpStatus = FollowUps::status($point->follow_up_on, $today);
@endphp

<x-layout :title="$point->label">
    <x-page-header :title="$point->label" :description="$point->surface_description" :back="route('overview')" back-label="Overview">
        <x-slot:meta>
            <p class="meta mt-3 flex flex-wrap items-center gap-x-2">
                @if ($point->area)
                    <span class="inline-flex items-center gap-1.5"><x-icon name="home" class="size-4" /> {{ $point->area }}</span>
                    <span aria-hidden="true">·</span>
                @endif
                <span>{{ trans_choice('{0} No photos yet|{1} 1 photo|[2,*] :count photos', $recordCount) }}</span>
                <span aria-hidden="true">·</span>
                <a href="{{ route('points.edit', $point) }}" class="link inline-flex min-h-11 items-center gap-1">
                    <x-icon name="pencil" class="size-4" /> Edit spot
                </a>
            </p>
        </x-slot:meta>
    </x-page-header>

    @if (! $baseline)
        {{-- No photo yet: explain the passport and start it with a baseline --}}
        <section aria-labelledby="passport-title" class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="sheet grid grid-cols-2 gap-3 p-4 sm:gap-5 sm:p-6" aria-hidden="true">
                @foreach (['Baseline', 'Follow-up 1'] as $slot)
                    <div>
                        <div @class([
                            'viewfinder flex aspect-[4/3] flex-col items-center justify-center gap-2 rounded-photo bg-canvas text-center ring-1 ring-line [--vf-gap:-0.5rem] [--vf-length:0.875rem]',
                            '[--vf-color:var(--color-primary)] text-primary' => $loop->first,
                            '[--vf-color:var(--color-line-strong)] text-muted' => ! $loop->first,
                        ])>
                            <x-icon :name="$loop->first ? 'camera' : 'image'" class="size-6" />
                        </div>
                        <p class="mt-2.5 text-sm font-semibold text-heading">{{ $slot }}</p>
                        <p class="meta">{{ $loop->first ? 'Your first photo of this spot' : 'Taken later, from the same place' }}</p>
                    </div>
                @endforeach
            </div>

            <aside aria-labelledby="passport-title" class="rail rounded-sheet bg-rail p-5 text-rail-text shadow-sheet sm:p-6">
                <h2 id="passport-title" class="text-lg font-semibold">Observation passport</h2>
                <p class="mt-1.5 text-sm text-rail-muted">
                    Your photo record of this spot. The first photo is the baseline; later photos from the same place are follow-ups.
                </p>
                <ol class="mt-5 space-y-3 border-t border-white/10 pt-5 text-sm">
                    @foreach (['Take a baseline photo', 'Come back for follow-ups', 'Compare them and print a report'] as $step)
                        <li class="flex items-center gap-3">
                            <span class="step-number size-6 border-rail-accent text-xs text-rail-accent">{{ $loop->iteration }}</span> {{ $step }}
                        </li>
                    @endforeach
                </ol>
                <a href="{{ $addUrl }}" class="btn btn-on-rail mt-6 w-full"><x-icon name="camera" /> Add baseline photo</a>
            </aside>
        </section>
    @else
        <section aria-labelledby="pair-heading" class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            {{-- The baseline beside the latest photo: the whole record at a glance --}}
            <div class="sheet overflow-hidden">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 border-b border-line px-4 py-3 sm:px-5">
                    <h2 id="pair-heading" class="font-semibold text-heading">{{ $hasFollowUp ? 'Baseline and latest photo' : 'Baseline photo' }}</h2>
                    @if ($apart)
                        <p class="meta">Saved {{ $apart }} apart</p>
                    @endif
                </div>

                <div class="grid grid-cols-2">
                    <figure class="flex min-w-0 flex-col border-r border-line p-3 sm:p-4">
                        <a href="{{ Observations::entryUrl($point, $baseline, 1) }}" class="frame-link flex flex-1 items-center">
                            <x-record-photo :record="$baseline" loading="eager"
                                :alt="'Baseline photo of '.$point->label.', saved '.$baseline->submitted_at->format('j M Y')"
                                class="print h-auto max-h-[26rem] w-full object-contain" />
                        </a>
                        <figcaption class="mt-3">
                            <span class="flex items-center gap-1.5 text-sm font-semibold text-heading"><x-icon name="flag" class="size-4" /> Baseline</span>
                            <span class="meta block">Saved <x-local-time :time="$baseline->submitted_at" format="date" /></span>
                        </figcaption>
                    </figure>

                    @if ($hasFollowUp)
                        <figure class="flex min-w-0 flex-col p-3 sm:p-4">
                            <a href="{{ Observations::entryUrl($point, $latest, $recordCount) }}" class="frame-link flex flex-1 items-center">
                                <x-record-photo :record="$latest" loading="eager"
                                    :alt="'Latest photo of '.$point->label.', saved '.$latest->submitted_at->format('j M Y')"
                                    class="print h-auto max-h-[26rem] w-full object-contain" />
                            </a>
                            <figcaption class="mt-3">
                                <span class="flex flex-wrap items-center gap-x-2 text-sm font-semibold text-heading">
                                    {{ Observations::label($recordCount) }} <span class="tag tag-accent">Latest</span>
                                </span>
                                <span class="meta block">Saved <x-local-time :time="$latest->submitted_at" format="date" /></span>
                            </figcaption>
                        </figure>
                    @else
                        {{-- Only the baseline so far: an honest empty frame where the first follow-up will go --}}
                        <div class="flex min-w-0 flex-col p-3 sm:p-4">
                            <a href="{{ $addUrl }}" @if ($baseline->viewWidth() && $baseline->viewHeight()) style="aspect-ratio: {{ $baseline->viewWidth() }} / {{ $baseline->viewHeight() }}" @endif
                                class="viewfinder flex max-h-[26rem] w-full flex-1 flex-col items-center justify-center gap-2 rounded-photo bg-canvas px-3 text-center text-sm font-semibold text-primary ring-1 ring-line [--vf-color:var(--color-primary)] [--vf-gap:-0.5rem] [--vf-length:0.875rem] hover:bg-well">
                                <x-icon name="camera" class="size-6" /> Add follow-up photo
                            </a>
                            <p class="mt-3 text-sm font-semibold text-heading">Follow-up 1</p>
                            <p class="meta">Not taken yet</p>
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-t border-line px-4 py-3 sm:px-5">
                    @if ($hasFollowUp)
                        <p class="max-w-md text-sm text-muted">Changes in light, angle or distance can look like change, so look closely before deciding anything.</p>
                        <a href="{{ route('points.compare', [$point, 'first' => $baseline->id, 'second' => $latest->id]) }}" class="btn btn-secondary">
                            <x-icon name="split" class="size-4" /> Compare before and after
                        </a>
                    @else
                        <p class="text-sm text-muted">You can compare once this spot has a follow-up photo.</p>
                    @endif
                </div>
            </div>

            {{-- The passport: what this record holds, and the next step --}}
            <aside aria-labelledby="passport-title" class="rail rounded-sheet bg-rail p-5 text-rail-text shadow-sheet sm:p-6">
                <h2 id="passport-title" class="text-lg font-semibold">Observation passport</h2>
                <p class="mt-1.5 text-sm text-rail-muted">One baseline photo, then follow-ups from the same place.</p>

                <dl class="mt-5 grid grid-cols-2 gap-x-4 gap-y-3 border-t border-white/10 pt-5 text-sm">
                    <div>
                        <dt class="text-rail-muted">Baseline</dt>
                        <dd class="mt-0.5 font-semibold tabular-nums"><x-local-time :time="$baseline->submitted_at" format="date" /></dd>
                    </div>
                    <div>
                        <dt class="text-rail-muted">Follow-ups</dt>
                        <dd class="mt-0.5 font-semibold tabular-nums">{{ $recordCount - 1 }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-rail-muted">Latest photo saved</dt>
                        <dd class="mt-0.5 font-semibold tabular-nums">
                            <x-local-time :time="$latest->submitted_at" format="date" /> <span class="font-normal text-rail-muted">({{ $latest->submitted_at->diffForHumans() }})</span>
                        </dd>
                    </div>
                </dl>

                <div id="follow-up" class="mt-5 scroll-mt-28 border-t border-white/10 pt-5">
                    <p class="font-semibold">Next: {{ Str::lower(Observations::label($recordCount + 1)) }}</p>

                    {{-- The date the resident plans to come back, if they chose one; RISE never sets or suggests it --}}
                    <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                        @if ($point->follow_up_on)
                            <span class="text-rail-muted">Planned for</span>
                            <x-calendar-date :date="$point->follow_up_on" format="calendarshort" class="font-semibold" />
                            @if (in_array($followUpStatus, ['past', 'today', 'soon'], true))
                                <x-follow-up-tag :date="$point->follow_up_on" :today="$today" />
                            @endif
                        @else
                            <span class="text-rail-muted">No follow-up date planned</span>
                        @endif
                    </p>

                    <details class="mt-1" @if ($errors->has('follow_up_on')) open @endif>
                        <summary class="inline-flex min-h-11 items-center gap-1.5 rounded-control text-sm font-semibold text-rail-accent">
                            <x-icon name="calendar" class="size-4" /> {{ $point->follow_up_on ? 'Change the date' : 'Plan a follow-up date' }}
                        </summary>
                        <div class="mt-1 space-y-3 rounded-2xl bg-rail-soft p-3.5">
                            <form method="POST" action="{{ route('points.follow-up.update', $point) }}" class="flex flex-wrap gap-2">
                                @csrf
                                @method('PATCH')
                                @foreach (FollowUps::QUICK_PICKS as $days => $text)
                                    <button type="submit" name="in_days" value="{{ $days }}" class="btn min-h-10 bg-white/12 px-3.5 text-sm hover:bg-white/20">{{ $text }}</button>
                                @endforeach
                            </form>
                            <form method="POST" action="{{ route('points.follow-up.update', $point) }}" class="flex flex-wrap items-end gap-2">
                                @csrf
                                @method('PATCH')
                                <div class="min-w-0 flex-1 basis-40">
                                    <label for="follow_up_on" class="mb-1 block text-xs font-semibold text-rail-muted">Or choose a day</label>
                                    <input type="date" id="follow_up_on" name="follow_up_on" required value="{{ $point->follow_up_on?->toDateString() }}" min="{{ $today->toDateString() }}"
                                        class="field-control min-h-10 py-1.5" @error('follow_up_on') aria-invalid="true" aria-describedby="follow_up_on-error" @enderror>
                                </div>
                                <button type="submit" class="btn btn-on-rail min-h-10 px-4 text-sm">Save date</button>
                            </form>
                            @error('follow_up_on')
                                <p id="follow_up_on-error" class="text-sm font-semibold text-rail-accent">{{ $message }}</p>
                            @enderror
                            @if ($point->follow_up_on)
                                <form method="POST" action="{{ route('points.follow-up.destroy', $point) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="min-h-11 text-sm font-semibold text-rail-muted underline underline-offset-2 hover:text-rail-text">Clear the date</button>
                                </form>
                            @endif
                            <p class="text-xs text-rail-muted">It appears on your Overview as the day gets close. RISE doesn’t send emails or notifications.</p>
                        </div>
                    </details>

                    <p class="mt-3 text-sm text-rail-muted">Go back to the same place. RISE shows your last photo so you can match the view.</p>
                    <a href="{{ $addUrl }}" class="btn btn-on-rail mt-4 w-full"><x-icon name="camera" /> Add follow-up photo</a>
                    <a href="{{ route('points.report', $point) }}" class="mt-2 flex min-h-11 items-center justify-center gap-2 rounded-control text-sm font-semibold hover:bg-rail-soft">
                        <x-icon name="printer" class="size-4" /> Print or save report
                    </a>
                    @if ($recordCount > Observations::PER_REPORT_PART)
                        <p class="mt-1 text-center text-xs text-rail-muted">Prints in parts of {{ Observations::PER_REPORT_PART }} photos</p>
                    @endif
                </div>
            </aside>
        </section>

        {{-- Every photo in date order, starting from the baseline --}}
        <section aria-labelledby="timeline-heading" class="mt-14">
            <div class="section-head">
                <h2 id="timeline-heading" class="section-title">Observation timeline</h2>
                <p class="meta">Oldest first · “Saved” is when RISE got each photo</p>
            </div>

            <ol role="list" class="sheet mt-4 px-3 sm:px-6 [&>li:last-child_article]:border-b-0">
                @foreach ($items as $record)
                    @php
                        $position = $records->firstItem() + $loop->index;
                        $isFirst = $position === 1;
                        $isLast = $position === $recordCount;
                    @endphp
                    <li id="record-{{ $record->id }}" class="grid scroll-mt-24 grid-cols-[2.25rem_minmax(0,1fr)] gap-x-3 sm:grid-cols-[2.75rem_minmax(0,1fr)] sm:gap-x-6">
                        {{-- The spine and this entry's marker: a flag for the baseline, a number for each follow-up --}}
                        <div class="relative flex justify-center" aria-hidden="true">
                            <span @class(['absolute w-px bg-line-strong', 'top-0' => ! $isFirst, 'top-9' => $isFirst, 'bottom-0' => ! $isLast, 'h-9' => $isLast && ! $isFirst, 'hidden' => $isFirst && $isLast])></span>
                            @if ($isFirst)
                                <span class="relative mt-6 flex size-8 items-center justify-center rounded-control bg-brand text-white sm:size-9"><x-icon name="flag" class="size-4" /></span>
                            @else
                                <span class="relative mt-6 flex size-8 items-center justify-center rounded-full border-2 border-brand bg-canvas text-sm font-bold text-heading tabular-nums sm:size-9">{{ $position - 1 }}</span>
                            @endif
                        </div>

                        <article aria-labelledby="record-{{ $record->id }}-title"
                            class="grid min-w-0 gap-x-7 gap-y-4 border-b border-line py-6 md:grid-cols-[minmax(0,17rem)_minmax(0,1fr)]">
                            <header class="md:col-start-2 md:row-start-1">
                                <h3 id="record-{{ $record->id }}-title" class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-lg font-semibold tracking-[-0.01em] text-heading">
                                    {{ Observations::label($position) }}
                                    @if ($isLast && ! $isFirst)
                                        <span class="tag tag-accent">Latest</span>
                                    @endif
                                </h3>
                                <p class="meta mt-0.5">
                                    @if ($isFirst)
                                        The reference photo for this spot
                                    @else
                                        {{ Observations::span($baseline->submitted_at, $record->submitted_at) }} after the baseline
                                    @endif
                                </p>
                            </header>

                            <a href="{{ route('records.photo', $record) }}" target="_blank" rel="noopener" class="frame-link w-fit max-w-full self-start md:col-start-1 md:row-span-2 md:row-start-1">
                                <x-record-photo :record="$record" :alt="Observations::label($position).' photo of '.$point->label.', saved '.$record->submitted_at->format('j M Y')"
                                    class="print h-auto max-h-64 w-auto max-w-full" />
                                <span class="sr-only">(opens full size in a new tab)</span>
                            </a>

                            <div class="min-w-0 md:col-start-2">
                                <dl class="grid gap-x-4 gap-y-1 text-sm max-sm:[&>dt:not(:first-child)]:mt-1.5 sm:grid-cols-[8rem_minmax(0,1fr)]">
                                    <x-photo-dates :record="$record" />
                                </dl>
                                @if ($record->display_reason === 'converted_from_heic')
                                    <p class="meta mt-2">Converted from HEIC to JPEG for viewing. The original file is kept privately.</p>
                                @endif
                                @if ($record->note)
                                    <p class="mt-3 max-w-prose break-words whitespace-pre-line">{{ $record->note }}</p>
                                @endif
                                @if ($record->latestAnalysis)
                                    <x-analysis-summary :analysis="$record->latestAnalysis" class="mt-3" />
                                @endif

                                <div class="mt-3 -ml-3 flex flex-wrap items-center gap-x-1 gap-y-2">
                                    @if ($recordCount >= 2)
                                        <a href="{{ route('points.compare', [$point, 'first' => $baseline->id, 'second' => $isFirst ? $latest->id : $record->id]) }}" class="btn btn-quiet">
                                            <x-icon name="split" class="size-4" /> {{ $isFirst ? 'Compare with latest' : 'Compare with baseline' }}
                                        </a>
                                    @endif
                                    <a href="{{ route('records.analysis.show', $record) }}" class="btn btn-quiet">
                                        <x-icon name="scan" class="size-4" /> {{ $record->latestAnalysis ? 'View AI result' : 'Analyze with AI' }}
                                    </a>
                                    <x-delete-confirm :action="route('records.destroy', $record)" label="Delete…" confirm="Delete photo"
                                        question="Delete this photo and any AI result for it permanently? This can’t be undone." class="max-sm:basis-full" />
                                </div>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ol>

            @if ($records->hasPages())
                <nav class="mt-8 flex flex-wrap items-center justify-between gap-3" aria-label="Timeline pages">
                    @if ($records->previousPageUrl())
                        <a href="{{ $records->previousPageUrl() }}#timeline-heading" class="btn btn-secondary"><x-icon name="arrow-left" class="size-4" /> Earlier photos</a>
                    @else
                        <span></span>
                    @endif
                    <p class="meta">Page {{ $records->currentPage() }} of {{ $records->lastPage() }}</p>
                    @if ($records->nextPageUrl())
                        <a href="{{ $records->nextPageUrl() }}#timeline-heading" class="btn btn-secondary">Later photos <x-icon name="arrow-right" class="size-4" /></a>
                    @else
                        <span></span>
                    @endif
                </nav>
            @endif
        </section>
    @endif
</x-layout>
