@use('App\Support\Observations')

@php
    // Records come oldest first, so a record's place in the list is its place in the passport
    $labelFor = fn ($record) => Observations::label($records->search(fn ($candidate) => $candidate->is($record)) + 1);
@endphp

<x-layout title="Compare photos">
    <x-page-header title="Compare photos" :description="$point->label" :back="route('points.show', $point)" :back-label="$point->label" />

    @if ($records->count() < 2)
        <section aria-labelledby="need-more-heading" class="sheet flex flex-col items-center px-6 py-14 text-center sm:px-10">
            {{-- Two frames: the photo that exists, and the empty one still to take --}}
            <div class="flex items-center gap-3" aria-hidden="true">
                <span class="drop-card size-16 -rotate-3"><x-icon name="image" class="size-7" /></span>
                <span class="viewfinder flex size-16 items-center justify-center rounded-photo bg-surface text-primary ring-1 ring-line [--vf-color:var(--color-primary)] [--vf-gap:-0.375rem] [--vf-length:0.625rem]">
                    <x-icon name="plus" />
                </span>
            </div>
            <h2 id="need-more-heading" class="mt-6 text-lg font-semibold text-heading">Add another photo to compare</h2>
            <p class="mx-auto mt-1 max-w-sm text-muted">
                @if ($records->isEmpty())
                    You need a baseline photo and at least one follow-up of this spot.
                @else
                    You need a follow-up photo, taken from the same place as the baseline.
                @endif
            </p>
            <a href="{{ route('records.create', ['point' => $point]) }}" class="btn btn-primary mt-6">
                <x-icon name="camera" /> {{ $records->isEmpty() ? 'Add baseline photo' : 'Add follow-up photo' }}
            </a>
        </section>
    @else
        {{-- The comparison controls float on glass; the photos below stay on solid white --}}
        <form method="GET" action="{{ route('points.compare', $point) }}"
            class="glass grid items-end gap-x-4 gap-y-4 rounded-glass p-4 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:p-5 lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_auto]">
            @foreach (['first' => ['Earlier photo', $earlier], 'second' => ['Later photo', $later]] as $field => [$label, $chosen])
                <div class="min-w-0">
                    <label for="{{ $field }}" class="field-label">{{ $label }}</label>
                    <select id="{{ $field }}" name="{{ $field }}" class="field-control">
                        @foreach ($records as $record)
                            @php($excerpt = $record->note ? ' · '.Str::limit($record->note, 32) : '')
                            <option value="{{ $record->id }}" @selected($record->is($chosen))
                                data-local-time="datetime" data-datetime="{{ $record->submitted_at->toIso8601String() }}"
                                data-prefix="{{ Observations::label($loop->iteration) }} · " data-suffix="{{ $excerpt }}">
                                {{ Observations::label($loop->iteration) }} · {{ $record->submitted_at->format('j M Y, H:i') }} UTC{{ $excerpt }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if ($loop->first)
                    <x-icon name="arrow-right" class="mb-3 hidden text-muted sm:block" />
                @endif
            @endforeach

            <button type="submit" class="btn btn-primary sm:col-span-3 sm:justify-self-start lg:col-span-1">Compare</button>
        </form>

        @if ($samePhoto)
            <x-alert type="error" class="mt-8">You chose the same photo twice. Choose two different photos to compare.</x-alert>
        @else
            {{-- The time between the two photos, drawn as a dimension line between their dates --}}
            <div class="mt-10 grid grid-cols-2 items-center gap-x-4 gap-y-4 px-1 sm:grid-cols-[auto_minmax(0,1fr)_auto]">
                <p>
                    <span class="block text-sm font-semibold text-muted">{{ $labelFor($earlier) }}</span>
                    <x-local-time :time="$earlier->submitted_at" format="date" class="text-lg font-semibold text-heading tabular-nums" />
                </p>
                <div class="col-span-2 row-start-2 flex items-center sm:col-span-1 sm:col-start-2 sm:row-start-1">
                    <span class="h-3 w-px bg-ink/60" aria-hidden="true"></span>
                    <span class="h-px flex-1 bg-ink/35" aria-hidden="true"></span>
                    <span class="surface mx-2 shrink-0 rounded-full bg-white px-3 py-1 text-sm font-semibold text-heading shadow-[0_1px_2px_rgb(6_16_10/0.25)]">
                        Saved {{ Observations::span($earlier->submitted_at, $later->submitted_at) }} apart
                    </span>
                    <span class="h-px flex-1 bg-ink/35" aria-hidden="true"></span>
                    <span class="h-3 w-px bg-ink/60" aria-hidden="true"></span>
                </div>
                <p class="justify-self-end text-right sm:col-start-3">
                    <span class="block text-sm font-semibold text-muted">{{ $labelFor($later) }}</span>
                    <x-local-time :time="$later->submitted_at" format="date" class="text-lg font-semibold text-heading tabular-nums" />
                </p>
            </div>

            <div class="compare-views mt-8">
                {{-- The slider needs JavaScript, so the switch appears only once the script has run. On phones it fills the
                     width, and "slider" is left to screen readers so each label fits on one line. --}}
                <div class="flex">
                    <fieldset data-compare-switch hidden class="min-w-0 max-sm:flex-1">
                        <legend class="sr-only">View</legend>
                        <div class="segmented segmented-fill glass">
                            <label class="segmented-option"><input type="radio" name="compare-view" value="side" class="sr-only" checked> <x-icon name="columns" class="size-4" /> Side by side</label>
                            <label class="segmented-option"><input type="radio" name="compare-view" value="wipe" class="sr-only"> <x-icon name="split" class="size-4" /> <span>Before/after<span class="max-sm:sr-only"> slider</span></span></label>
                            <label class="segmented-option"><input type="radio" name="compare-view" value="overlay" class="sr-only"> <x-icon name="layers" class="size-4" /> Overlay</label>
                        </div>
                    </fieldset>
                </div>

                <div class="sheet sheet-solid mt-5 grid gap-6 p-4 sm:p-6 md:grid-cols-2" data-compare-side>
                    @foreach (['Earlier' => $earlier, 'Later' => $later] as $position => $record)
                        <figure class="flex min-w-0 flex-col">
                            <figcaption class="mb-3 flex flex-wrap items-baseline justify-between gap-x-3 border-b border-line pb-2">
                                <span class="font-semibold text-heading">{{ $labelFor($record) }} <span class="font-normal text-muted">· {{ Str::lower($position) }}</span></span>
                                <span class="meta">Saved <x-local-time :time="$record->submitted_at" /></span>
                            </figcaption>

                            <div class="flex flex-1 items-center justify-center">
                                <x-record-photo :record="$record" loading="eager"
                                    :alt="$labelFor($record).' photo of '.$point->label.', saved '.$record->submitted_at->format('j M Y')"
                                    class="print h-auto max-h-[65svh] w-full object-contain" />
                            </div>

                            @if ($record->note)
                                <p class="mt-3 text-sm break-words whitespace-pre-line">{{ $record->note }}</p>
                            @endif
                        </figure>
                    @endforeach
                </div>

                @php($ratio = $earlier->viewWidth() && $earlier->viewHeight() ? $earlier->viewWidth() / $earlier->viewHeight() : 4 / 3)
                <div class="mt-5" data-compare-wipe hidden>
                    <div class="sheet sheet-solid p-3 sm:p-5">
                        <div class="wipe mx-auto" data-wipe style="aspect-ratio: {{ round($ratio, 4) }}; width: min(100%, calc(68svh * {{ round($ratio, 4) }}))">
                            <x-record-photo :record="$earlier" :alt="$labelFor($earlier).' photo of '.$point->label.', saved '.$earlier->submitted_at->format('j M Y')" class="" />
                            <div class="wipe-later">
                                <x-record-photo :record="$later" :alt="$labelFor($later).' photo of '.$point->label.', saved '.$later->submitted_at->format('j M Y')" class="" />
                            </div>
                            <span class="wipe-label left-3" aria-hidden="true">{{ $labelFor($earlier) }} · <x-local-time :time="$earlier->submitted_at" format="short" /></span>
                            <span class="wipe-label right-3" aria-hidden="true">{{ $labelFor($later) }} · <x-local-time :time="$later->submitted_at" format="short" /></span>
                            <span class="wipe-divider" aria-hidden="true"></span>
                            <span class="wipe-handle" aria-hidden="true"><x-icon name="chevrons-left-right" /></span>
                            <label for="wipe-position" class="sr-only">Divider between the earlier photo, on the left, and the later photo, on the right</label>
                            <input id="wipe-position" type="range" min="0" max="100" step="1" value="50" class="sr-only" data-wipe-input>
                        </div>
                    </div>
                    <p class="meta mt-3">
                        Drag across the photo, or use the arrow keys, to move the divider. If the photos weren’t taken from the same place,
                        edges won’t line up even where nothing changed.
                    </p>
                </div>

                {{-- The later photo faded over the earlier one; both stay whole and unblurred --}}
                <div class="mt-5" data-compare-overlay hidden>
                    <div class="sheet sheet-solid p-3 sm:p-5">
                        <div class="overlay-view mx-auto" data-overlay style="aspect-ratio: {{ round($ratio, 4) }}; width: min(100%, calc(62svh * {{ round($ratio, 4) }}))">
                            <x-record-photo :record="$earlier" :alt="$labelFor($earlier).' photo of '.$point->label.', saved '.$earlier->submitted_at->format('j M Y')" />
                            <x-record-photo :record="$later" :alt="$labelFor($later).' photo of '.$point->label.', saved '.$later->submitted_at->format('j M Y').', faded over the earlier photo'" class="overlay-later" />
                            <span class="wipe-label left-3" aria-hidden="true">{{ $labelFor($earlier) }} · underneath</span>
                            <span class="wipe-label right-3" aria-hidden="true">{{ $labelFor($later) }} · on top</span>
                        </div>
                        <div class="mx-auto mt-4 max-w-md">
                            <div class="flex items-center justify-between gap-3">
                                <label for="overlay-opacity" class="font-semibold text-ink">{{ $labelFor($later) }} on top</label>
                                <output for="overlay-opacity" class="text-sm font-semibold text-heading tabular-nums" data-overlay-value>50%</output>
                            </div>
                            <input type="range" id="overlay-opacity" min="0" max="100" step="5" value="50" class="mt-2 block h-11 w-full cursor-pointer"
                                aria-describedby="overlay-help" data-overlay-input>
                        </div>
                    </div>
                    <p id="overlay-help" class="meta mt-3">
                        Fades the later photo over the earlier one. RISE doesn’t line the photos up or measure anything, so if they weren’t
                        taken from the same place, edges won’t match even where nothing changed.
                    </p>
                </div>
            </div>

            {{-- What this view can and can't tell you, kept beside the photos --}}
            <div class="surface mt-10 flex items-start gap-3 rounded-2xl bg-well px-4 py-4 text-sm sm:px-5">
                <x-icon name="info" class="mt-0.5 size-4 shrink-0 text-primary" />
                <p class="max-w-prose">
                    This view only shows your photos. It can’t measure a crack, and changes in light, angle or distance can look like change.
                    It isn’t a safety check; if you’re worried, ask a qualified professional. Nothing is sent anywhere unless you choose AI comparison below.
                </p>
            </div>

            @include('points.partials.ai-comparison')
        @endif
    @endif
</x-layout>
