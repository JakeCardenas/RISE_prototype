@use('App\Support\AiWording')
@use('App\Support\Observations')

@php
    // $records is one part of the history; $baseline and $latest describe the whole record
    $total = $records->total();
    $complete = $records->lastPage() === 1;
    $part = $records->currentPage();
    $first = $records->getCollection()->first();
    $last = $records->getCollection()->last();
    $covered = $latest && ! $latest->is($baseline) ? Observations::span($baseline->submitted_at, $latest->submitted_at) : null;
    $sameDay = $first && $first->submitted_at->isSameDay($last->submitted_at);
    $hasFileModified = $records->getCollection()->contains(fn ($record) => $record->file_modified_at);
    $partUrl = fn (int $number) => route('points.report', [$point, 'part' => $number]);
    $range = fn (int $from, int $to) => $from === $to ? "photo {$from}" : "photos {$from}–{$to}";
@endphp

{{-- A document, not an app screen: no navigation, and laid out to print or save as PDF from the browser --}}
<x-layout :title="'Observation report · '.$point->label" bare>
    <div class="min-h-dvh px-4 pt-[max(1.5rem,env(safe-area-inset-top))] pb-12 sm:px-8 sm:pt-10 print:min-h-0 print:p-0">
        {{-- The toolbar floats on glass while the report scrolls; it never prints --}}
        <div class="glass glass-dense sticky top-3 z-20 mx-auto mb-6 flex max-w-[52rem] flex-wrap items-center justify-between gap-x-6 gap-y-2 rounded-[1.75rem] py-1.5 pr-1.5 pl-2 print:hidden">
            <a href="{{ route('points.show', $point) }}" class="inline-flex min-h-11 min-w-0 flex-1 items-center gap-1 rounded-full pr-3 pl-2 text-sm font-semibold text-primary hover:bg-white/12">
                <x-icon name="chevron-left" class="size-[1.125rem]" /> <span class="truncate">Back to {{ $point->label }}</span>
            </a>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <p class="meta" data-print-fallback>To print or save as PDF, use your browser’s Print command.</p>
                <p class="meta max-md:hidden" data-print-hint hidden>Choose “Save as PDF” in the print dialog to keep a copy.</p>
                <button type="button" class="btn btn-primary" data-print hidden><x-icon name="printer" /> Print or save as PDF</button>
            </div>

            @unless ($complete)
                {{-- A long history comes in parts; each part prints on its own --}}
                <nav aria-label="Report parts" class="flex basis-full flex-wrap items-center gap-2 border-t border-ink/10 px-1.5 pt-2 pb-1">
                    <span class="mr-2 text-sm font-semibold text-heading">Part {{ $part }} of {{ $records->lastPage() }}</span>
                    @foreach (range(1, $records->lastPage()) as $number)
                        <a href="{{ $partUrl($number) }}" @if ($number === $part) aria-current="page" @endif
                            @class(['btn min-h-11 px-3 text-sm', 'btn-primary' => $number === $part, 'btn-secondary' => $number !== $part])>
                            <span class="sr-only">Part</span> {{ $number }}
                            <span class="font-normal">· {{ $range(($number - 1) * $records->perPage() + 1, min($number * $records->perPage(), $total)) }}</span>
                        </a>
                    @endforeach
                </nav>
            @endunless
        </div>

        <article class="report-sheet surface mx-auto max-w-[52rem] rounded-sheet bg-surface px-5 py-8 shadow-sheet ring-1 ring-line sm:px-12 sm:py-12 print:max-w-none print:rounded-none print:p-0 print:shadow-none print:ring-0">
            <header>
                <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-b-2 border-brand pb-4">
                    <span class="flex items-center gap-2.5 text-heading">
                        <x-rise-mark class="size-8" />
                        <span class="text-lg leading-none font-bold tracking-[0.16em]">RISE</span>
                    </span>
                    <span class="text-sm font-semibold text-heading">
                        Observation report{{ $complete ? '' : ' · part '.$part.' of '.$records->lastPage() }}
                    </span>
                </div>

                <h1 class="mt-7 text-[1.875rem] leading-tight font-semibold tracking-[-0.025em] break-words text-heading sm:text-[2.25rem]">{{ $point->label }}</h1>
                @if ($point->surface_description)
                    <p class="mt-2 max-w-prose text-[1.0625rem] break-words text-muted">{{ $point->surface_description }}</p>
                @endif

                @if ($records->isNotEmpty())
                    <dl class="title-block mt-6 grid-cols-2 sm:grid-cols-4 print:grid-cols-4">
                        <div class="col-span-2">
                            <dt>In this report</dt>
                            <dd>
                                @if ($complete)
                                    {{ trans_choice('{1} The only photo of this spot|[2,*] All :count photos of this spot', $total) }}
                                @else
                                    Part {{ $part }} of {{ $records->lastPage() }}: {{ $range($records->firstItem(), $records->lastItem()) }} of {{ $total }}
                                @endif
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt>{{ $sameDay ? 'Saved on' : 'Saved between' }}</dt>
                            <dd>
                                <x-local-time :time="$first->submitted_at" format="date" />
                                @unless ($sameDay)
                                    and <x-local-time :time="$last->submitted_at" format="date" />
                                @endunless
                            </dd>
                        </div>
                        <div>
                            <dt>Baseline saved</dt>
                            <dd><x-local-time :time="$baseline->submitted_at" format="date" /></dd>
                        </div>
                        <div>
                            <dt>Latest saved</dt>
                            <dd><x-local-time :time="$latest->submitted_at" format="date" /></dd>
                        </div>
                        <div class="col-span-2">
                            <dt>Whole record</dt>
                            <dd>
                                {{ trans_choice('{1} 1 photo|[2,*] :count photos', $total) }}
                                <span class="font-medium text-muted">({{ trans_choice('{0} baseline only|{1} 1 follow-up|[2,*] :count follow-ups', $total - 1) }}{{ $covered ? ', over '.$covered : '' }})</span>
                            </dd>
                        </div>
                    </dl>
                @endif
                <p class="meta mt-3">Report prepared <x-local-time :time="now()" format="stamped" /></p>
            </header>

            <section aria-labelledby="about-heading" class="mt-9">
                <h2 id="about-heading" class="section-title">About this report</h2>
                <p class="mt-2 max-w-prose">
                    @if ($complete)
                        This report lists {{ $total === 1 ? 'the only photo' : 'all '.$total.' photos' }} saved in RISE for this spot, oldest first.
                    @else
                        This is part {{ $part }} of {{ $records->lastPage() }}. It lists {{ $range($records->firstItem(), $records->lastItem()) }}
                        of the {{ $total }} saved in RISE for this spot, oldest first; the other parts list the rest.
                    @endif
                    The first photo is the baseline; later photos are follow-ups the resident took at the same spot.
                </p>
                <p class="mt-2 max-w-prose">
                    “Saved to RISE” is when RISE received each photo. “Photo taken” comes from the photo file’s own details when it
                    has them, uses the camera’s clock and hasn’t been verified.
                    @if ($hasFileModified)
                        “File modified”, shown for photos saved before RISE read those details, is when the file was last changed,
                        which can be when it was exported or copied rather than taken.
                    @endif
                </p>
            </section>

            <section aria-labelledby="photos-heading" class="mt-9">
                <h2 id="photos-heading" class="section-title">Photos</h2>

                @if ($records->isEmpty())
                    <p class="mt-3 text-muted">No photos have been saved for this spot yet.</p>
                @endif

                <ol class="mt-4">
                    @foreach ($records as $record)
                        @php($analysis = $record->analyses->first())
                        @php($position = $records->firstItem() + $loop->index)
                        <li class="report-entry grid gap-x-7 gap-y-4 border-t border-line py-6 sm:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] print:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
                            <x-record-photo :record="$record" loading="eager"
                                :alt="Observations::label($position).' photo of '.$point->label.', saved '.$record->submitted_at->format('j M Y')"
                                class="h-auto max-h-[22rem] w-auto max-w-full rounded-photo ring-1 ring-line print:max-h-[88mm]" />

                            <div class="min-w-0">
                                <h3 class="text-lg font-semibold tracking-[-0.01em] text-heading">{{ Observations::label($position) }}</h3>
                                <dl class="mt-2 grid grid-cols-[7.5rem_minmax(0,1fr)] gap-x-3 gap-y-1 text-sm">
                                    <x-photo-dates :record="$record" stamped />
                                    @if ($record->display_reason === 'converted_from_heic')
                                        <dt class="text-muted">File</dt>
                                        <dd>Converted from HEIC to JPEG for viewing; the original file is kept privately</dd>
                                    @endif
                                </dl>

                                <h4 class="mt-4 text-sm font-semibold text-muted">Resident’s note</h4>
                                <p class="mt-0.5 break-words whitespace-pre-line">{{ $record->note ?: 'No note.' }}</p>

                                {{-- Only a finished analysis the resident asked for; nothing is shown when there isn't one --}}
                                @if ($analysis)
                                    <div class="mt-4 rounded-control border border-line p-3.5 text-sm">
                                        <h4 class="flex items-center gap-2 font-semibold">
                                            <span class="size-3 shrink-0 rounded-[3px] border-2 border-mark bg-mark/20" aria-hidden="true"></span>
                                            AI image check, requested by the resident
                                        </h4>
                                        <p class="mt-1.5">
                                            @if ($analysis->hasWrittenResult())
                                                {{-- A written result (Gemini) marks nothing, so it is reported by its finding --}}
                                                @php($finding = $analysis->observation()?->finding)
                                                {{ $finding ? AiWording::finding($finding) : 'The stored result couldn’t be checked, so it isn’t shown.' }}
                                                @if (in_array($finding, ['possible_feature', 'no_clear_feature'], true))
                                                    {{ AiWording::findingNote($finding) }}
                                                @endif
                                            @else
                                                {{ $analysis->region_count > 0
                                                    ? trans_choice('{1} The model marked one possible crack-like area for the resident to review.|[2,*] The model marked :count possible crack-like areas for the resident to review.', $analysis->region_count)
                                                    : 'The model did not mark an area in this photo. It can still miss cracks.' }}
                                            @endif
                                        </p>
                                        <p class="mt-1.5 text-muted">
                                            Model {{ $analysis->model_version ?? $analysis->model_id }} · {{ $analysis->providerLabel() }} ·
                                            analyzed <x-local-time :time="$analysis->completed_at" format="date" />
                                        </p>
                                        <p class="mt-1.5 text-muted">
                                            AI image results can be wrong. They do not identify a crack’s cause or determine whether a building is safe.
                                            Contact a qualified professional for an assessment.
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>

                @unless ($complete)
                    <p class="border-t border-line pt-4 text-sm text-muted">
                        This part holds {{ $range($records->firstItem(), $records->lastItem()) }} of {{ $total }}; the other parts hold the rest.
                        @if ($records->hasMorePages())
                            <a href="{{ $records->nextPageUrl() }}" class="link print:hidden">Continue to part {{ $part + 1 }}</a>
                        @endif
                    </p>
                @endunless
            </section>

            <footer class="mt-4 border-t-2 border-brand pt-5 text-sm">
                <p class="font-semibold text-ink">
                    RISE is for photo documentation and comparison. It is not a structural diagnosis or a safety certification.
                </p>
                <p class="mt-1.5 max-w-prose text-muted">
                    Photos taken at different times can differ in light, angle and distance, and that can look like change. If you’re
                    worried about a crack, ask a qualified professional to inspect it.
                </p>
            </footer>
        </article>
    </div>
</x-layout>
