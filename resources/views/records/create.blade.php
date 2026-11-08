@use('App\Support\FollowUps')
@use('App\Support\PhotoUploads')
@use('App\Support\Observations')

@php
    // A point's first photo is its baseline; any later photo is a follow-up, guided by the last one
    $mode = $selected ? ($selected->latestRecord ? 'follow-up' : 'baseline') : null;
    $titles = ['follow-up' => 'Add follow-up photo', 'baseline' => 'Add baseline photo'];
    $reference = $selected?->latestRecord;
    $today = FollowUps::today();
    // Saving a photo once the planned day has arrived completes that follow-up, so the form says so beforehand
    $plannedNote = fn ($point) => FollowUps::completedBy($point, (bool) $point->latestRecord, $today)
        ? 'Saving it completes the follow-up you planned for '.$point->follow_up_on->format('D j M').'.'
        : null;
@endphp

<x-layout :title="$titles[$mode] ?? 'Add photo'">
    <x-page-header :title="$titles[$mode] ?? 'Add photo'"
        description="Take it from the same place each time, so your photos are easy to compare."
        :back="$selected ? route('points.show', $selected) : route('overview')"
        :back-label="$selected ? $selected->label : 'Overview'" />

    {{-- Three numbered steps, kept together; on wide screens the full-size framing guide stays beside them --}}
    <form method="POST" action="{{ route('records.store') }}" enctype="multipart/form-data" novalidate
        class="grid gap-x-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] lg:items-start xl:grid-cols-[minmax(0,1fr)_minmax(0,24rem)]"
        data-photo-form data-max-bytes="{{ PhotoUploads::maxBytes() }}" data-max-label="{{ PhotoUploads::maxLabel() }}"
        data-format-label="{{ PhotoUploads::formatLabel() }}" data-heic="{{ PhotoUploads::heicSupported() ? 'true' : 'false' }}">
        @csrf

        {{-- Step 1: which spot the photo belongs to --}}
        <div class="grid grid-cols-[2.25rem_minmax(0,1fr)] gap-x-3 sheet sheet-solid p-5 sm:grid-cols-[2.75rem_minmax(0,1fr)] sm:p-6 lg:col-start-1">
            <span class="step-number mt-0.5" aria-hidden="true">1</span>
            <div class="min-w-0">
                <label for="monitoring_point_id" class="field-label text-[1.0625rem]">Spot</label>
                <select id="monitoring_point_id" name="monitoring_point_id" class="field-control mt-2 max-w-xl" required data-point-select
                    aria-describedby="monitoring_point_id-error monitoring_point_id-mode" @error('monitoring_point_id') aria-invalid="true" @enderror>
                    <option value="" disabled @selected(! $selected)>Choose a spot</option>
                    @foreach ($points as $point)
                        <option value="{{ $point->id }}" @selected($selected?->is($point))
                            data-next-label="{{ Observations::label($point->photo_records_count + 1) }}"
                            @if ($plannedNote($point)) data-planned-note="{{ $plannedNote($point) }}" @endif
                            @if ($point->latestRecord)
                                data-reference-src="{{ route('records.photo', $point->latestRecord) }}"
                                data-reference-date="{{ $point->latestRecord->submitted_at->toIso8601String() }}"
                                data-baseline-src="{{ route('records.photo', $point->baselineRecord ?? $point->latestRecord) }}"
                            @endif>{{ $point->area ? $point->label.' ('.$point->area.')' : $point->label }}</option>
                    @endforeach
                </select>
                <x-field-error for="monitoring_point_id" />
                <p id="monitoring_point_id-mode" class="mt-2 text-sm text-muted" aria-live="polite" data-mode-note>
                    @if ($mode === 'follow-up')
                        This photo will be saved as <strong class="font-semibold text-ink" data-next-label>{{ Observations::label($selected->photo_records_count + 1) }}</strong> for this spot.
                    @elseif ($mode === 'baseline')
                        This spot has no photos yet, so this one becomes its <strong class="font-semibold text-ink">baseline</strong>.
                    @endif
                    @if ($selected && $plannedNote($selected))
                        {{ $plannedNote($selected) }}
                    @endif
                </p>
                <a href="{{ route('points.create') }}" class="link mt-1 inline-flex min-h-11 items-center gap-1.5 text-sm">
                    <x-icon name="plus" class="size-4" /> Add a new spot
                </a>
            </div>
        </div>

        {{-- Wide screens: the full-size framing guide, the point's last photo to line up against --}}
        <aside class="glass hidden rounded-sheet p-6 lg:sticky lg:top-6 lg:col-start-2 lg:row-span-8 lg:row-start-1 lg:block" aria-labelledby="tips-heading">
            <h2 id="tips-heading" class="section-title">Line up the same view</h2>

            <figure class="mt-5" data-reference @unless ($reference) hidden @endunless>
                <div class="viewfinder mx-auto w-fit max-w-full">
                    <img alt="Your last photo of this spot, to help you line up the same view"
                        @if ($reference) src="{{ route('records.photo', $reference) }}" @endif
                        class="print block h-auto max-h-64 w-auto max-w-full shadow-print">
                </div>
                <figcaption class="mt-4 text-sm text-muted">
                    Your last photo of this spot, saved
                    <time class="font-semibold text-ink" datetime="{{ $reference?->submitted_at->toIso8601String() }}">{{ $reference?->submitted_at->format('j M Y') }}</time>.
                    After you choose a new photo, you can fade this one over it to compare the framing by eye.
                </figcaption>
            </figure>

            <p class="mt-4 text-sm text-muted" data-for-mode="baseline" @unless ($mode === 'baseline') hidden @endunless>
                This spot has no photos yet. Choose a view you can find again, with something fixed in the frame, like a window corner or a switch.
            </p>

            <p class="mt-4 text-sm text-muted" data-for-mode="" @if ($mode) hidden @endif>
                Choose a spot to see its last photo here.
            </p>
        </aside>

        {{-- Step 2: the photo itself --}}
        <div class="grid grid-cols-[2.25rem_minmax(0,1fr)] gap-x-3 sheet sheet-solid mt-3 p-5 sm:grid-cols-[2.75rem_minmax(0,1fr)] sm:p-6 lg:col-start-1">
            <span class="step-number mt-0.5" aria-hidden="true">2</span>
            <fieldset class="min-w-0">
                <legend class="field-label text-[1.0625rem]">Photo</legend>

                {{-- Phones and tablets: the last photo as a small guide just above the picker; tap it to see it larger --}}
                <details class="group mt-1 mb-4 rounded-2xl bg-well lg:hidden" data-reference @unless ($reference) hidden @endunless>
                    <summary class="flex min-h-11 items-center gap-3 p-2 pr-3">
                        <span class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-well-strong">
                            <img alt="" @if ($reference) src="{{ route('records.photo', $reference) }}" @endif class="size-full object-contain">
                        </span>
                        <span class="min-w-0 flex-1 text-sm leading-snug">
                            <span class="block font-semibold text-ink">
                                Your last photo · <time datetime="{{ $reference?->submitted_at->toIso8601String() }}" data-format="short">{{ $reference?->submitted_at->format('j M') }}</time>
                            </span>
                            <span class="block text-muted">Match its view. Tap to see it larger.</span>
                        </span>
                        <x-icon name="chevron-down" class="size-4 shrink-0 text-muted transition-transform group-open:rotate-180" />
                    </summary>
                    <div class="px-2 pb-3">
                        <img alt="Your last photo of this spot, to help you line up the same view"
                            @if ($reference) src="{{ route('records.photo', $reference) }}" @endif
                            class="print mx-auto block h-auto max-h-[50svh] w-auto max-w-full">
                        <p class="mt-2 px-1 text-sm text-muted">Stand where you stood for it. After you choose a photo, you can fade this one over it to compare the framing by eye.</p>
                    </div>
                </details>

                {{-- A short checklist for photos that compare fairly; folded on phones so the picker stays near the top --}}
                <details class="group mb-5 rounded-2xl border border-line" data-checklist>
                    <summary class="flex min-h-11 items-center gap-3 px-4 py-2 text-[0.9375rem] font-semibold text-heading">
                        <span class="flex flex-1 items-center gap-2"><x-icon name="check-circle" class="size-4 text-primary" /> Photo checklist</span>
                        <span class="hidden text-sm font-normal text-muted sm:inline">Focus · light · framing · position</span>
                        <x-icon name="chevron-down" class="size-4 shrink-0 text-muted transition-transform group-open:rotate-180" />
                    </summary>
                    <ul class="grid gap-x-6 gap-y-3 border-t border-line px-4 py-4 text-[0.9375rem] sm:grid-cols-2">
                        @foreach ([
                            ['Focus', 'Tap the crack on your screen so it’s sharp, and hold still as you take the photo.'],
                            ['Light', 'Use even daylight where you can. Avoid flash glare and strong shadows across the crack.'],
                            ['Framing', 'Keep the crack near the middle, with a fixed feature such as a window corner or switch in view.'],
                        ] as [$check, $text])
                            <li class="flex items-start gap-2.5"><x-icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-primary" /> <span><strong class="font-semibold">{{ $check }}.</strong> {{ $text }}</span></li>
                        @endforeach
                        <li class="flex items-start gap-2.5">
                            <x-icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-primary" />
                            <span>
                                <strong class="font-semibold">Camera position.</strong>
                                <span data-for-mode="follow-up" @unless ($mode === 'follow-up') hidden @endunless>Return to the same spot. Stand where you stood for your last photo.</span>
                                Hold the camera level, pointed straight at the surface, at a distance you can repeat.
                            </span>
                        </li>
                    </ul>
                    <p class="border-t border-line px-4 py-3 text-sm text-muted">RISE doesn’t check these for you; they help your photos compare fairly by eye.</p>
                </details>

                <p id="photo-help" class="field-hint">
                    {{ PhotoUploads::formatLabel() }}, up to {{ PhotoUploads::maxLabel() }}.
                    @if (PhotoUploads::heicSupported())
                        HEIC photos from a Mac or iPhone are converted to JPEG after upload; the original file is kept privately.
                    @else
                        HEIC photos can’t be converted on this server, so export them as JPEG first.
                    @endif
                </p>

                {{-- Without JavaScript this standard file control is used --}}
                <div data-native-picker>
                    <label for="photo" class="sr-only">Photo file</label>
                    <input type="file" id="photo" name="photo" accept="{{ implode(',', PhotoUploads::acceptedTypes()) }}" required data-photo-input
                        aria-describedby="photo-help photo-error" @error('photo') aria-invalid="true" @enderror
                        class="block w-full text-sm text-muted file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-control file:border file:border-line-strong file:bg-surface file:px-4 file:font-semibold file:text-heading">
                </div>

                {{-- With JavaScript: a drop zone with buttons, so dragging is never the only way in --}}
                <div class="dropzone mt-1" data-dropzone hidden>
                    <span class="drop-card" aria-hidden="true"><x-icon name="upload" class="size-7" /></span>
                    <div>
                        <p class="text-lg font-semibold text-heading">
                            <span class="pointer-coarse:hidden">Drag a photo here</span>
                            <span class="hidden pointer-coarse:inline">Take a photo or choose one</span>
                        </p>
                        <p class="meta mt-1 pointer-coarse:hidden">or choose one from your computer</p>
                    </div>
                    <input type="file" accept="{{ implode(',', PhotoUploads::acceptedTypes()) }}" capture="environment"
                        class="sr-only" tabindex="-1" aria-hidden="true" data-camera-input>
                    <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                        <button type="button" class="btn btn-secondary" aria-describedby="photo-help photo-error" data-camera-button hidden>
                            <x-icon name="camera" /> Take photo
                        </button>
                        <button type="button" class="btn btn-primary" aria-describedby="photo-help photo-error" data-library-button>
                            <x-icon name="image" /> Choose photo
                        </button>
                    </div>
                    <p class="meta">{{ PhotoUploads::formatLabel() }} · up to {{ PhotoUploads::maxLabel() }}</p>
                    <p class="meta hidden max-w-sm pointer-fine:block">On a Mac you can also drag a photo straight from Photos or Finder.</p>
                </div>

                {{-- Review before saving: the whole photo, then the framing check right beneath it --}}
                <section tabindex="-1" aria-labelledby="preview-heading" class="mt-1" data-preview hidden>
                    <h3 id="preview-heading" class="font-semibold text-ink">Review your photo</h3>
                    <p class="text-sm text-muted">Check that the crack or surface is clear and in focus before saving.</p>

                    <div class="mt-4 grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_13rem]">
                        <div class="min-w-0 space-y-3">
                            <div class="board flex justify-center p-4 sm:p-6" data-preview-frame>
                                <div class="viewfinder relative w-fit max-w-full">
                                    <img alt="Preview of the photo you selected" class="print block h-auto max-h-[55svh] w-auto max-w-full shadow-print" data-preview-image>
                                    {{-- The last photo of this spot, faded over the new one; purely a visual guide --}}
                                    <img alt="" class="pointer-events-none absolute inset-0 size-full rounded-photo object-contain" data-ghost hidden>
                                </div>
                            </div>

                            {{-- Quick checks of light, focus and framing, run in this browser on a small copy; they never stop you saving --}}
                            <p class="sr-only" aria-live="polite" data-quality-status></p>
                            <section tabindex="-1" class="rounded-2xl border border-line bg-surface p-4" aria-labelledby="quality-heading" data-quality hidden>
                                <h4 id="quality-heading" class="flex items-start gap-2 font-semibold text-ink">
                                    <span class="mt-0.5 shrink-0 text-primary" data-quality-icon="issues"><x-icon name="alert-triangle" class="size-4" /></span>
                                    <span class="mt-0.5 shrink-0 text-success" data-quality-icon="clear"><x-icon name="check-circle" class="size-4" /></span>
                                    <span data-quality-heading></span>
                                </h4>
                                <ul class="mt-2 list-disc space-y-1.5 pl-5 text-sm marker:text-muted" data-quality-list></ul>
                                <p class="meta mt-2" data-quality-note></p>
                                <div class="mt-3 flex flex-wrap gap-2" data-quality-actions>
                                    <button type="button" class="btn btn-secondary" data-quality-retake><x-icon name="refresh" /> Retake or choose another</button>
                                    <button type="button" class="btn btn-quiet" data-quality-keep>Keep this photo</button>
                                </div>
                            </section>

                            {{-- Framing check: fade the previous photo in and out over the new one --}}
                            <div class="rounded-2xl border border-line bg-surface p-4" data-ghost-controls hidden>
                                <div class="flex items-center justify-between gap-3">
                                    <label for="ghost-opacity" class="flex items-center gap-2 font-semibold text-ink">
                                        <x-icon name="layers" class="size-4 text-primary" /> Previous photo overlay
                                    </label>
                                    <output for="ghost-opacity" class="text-sm font-semibold tabular-nums text-heading" data-ghost-value>40%</output>
                                </div>
                                <input type="range" id="ghost-opacity" min="0" max="100" step="5" value="40" class="mt-2 block h-11 w-full cursor-pointer"
                                    aria-describedby="ghost-help" data-ghost-range>
                                <p id="ghost-help" class="meta">
                                    Fades your last photo of this spot over the new one, so you can compare the angle, distance and framing by eye.
                                    RISE doesn’t line the photos up or measure anything.
                                </p>
                            </div>
                        </div>

                        {{-- Cells wrap and stretch, so a hidden detail never leaves a gap --}}
                        <dl class="title-block flex flex-wrap">
                            <div data-detail-row class="grow"><dt>Format</dt><dd data-detail="type" class="whitespace-nowrap"></dd></div>
                            <div data-detail-row class="grow"><dt>File size</dt><dd data-detail="size"></dd></div>
                            <div data-detail-row class="grow"><dt>Pixels</dt><dd data-detail="pixels" class="whitespace-nowrap"></dd></div>
                            <div class="basis-full">
                                <dt>Photo taken</dt>
                                <dd class="font-medium">Read from the photo’s own details when you save, if it has them</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="mt-4 flex items-start gap-3 rounded-control border border-line bg-surface p-3 text-sm" data-heic-notice hidden>
                        <x-icon name="info" class="mt-0.5 text-primary" />
                        <p>This is a HEIC photo, which some browsers can’t preview. When you save, RISE converts it to JPEG for viewing and analysis and keeps the original file privately.</p>
                    </div>

                    <button type="button" class="btn btn-secondary mt-4" aria-describedby="photo-error" data-change-photo>
                        <x-icon name="refresh" /> Retake or choose another
                    </button>
                </section>

                <x-field-error for="photo" />
            </fieldset>
        </div>

        {{-- Step 3: an optional note --}}
        <div class="grid grid-cols-[2.25rem_minmax(0,1fr)] gap-x-3 sheet sheet-solid mt-3 p-5 sm:grid-cols-[2.75rem_minmax(0,1fr)] sm:p-6 lg:col-start-1">
            <span class="step-number mt-0.5" aria-hidden="true">3</span>
            <div class="min-w-0">
                <label for="note" class="field-label text-[1.0625rem]">Note <span class="font-normal text-muted">(optional)</span></label>
                <p id="note-hint" class="field-hint">What do you notice? For example, “Looks longer near the top” or “After heavy rain.”</p>
                <textarea id="note" name="note" rows="3" maxlength="1000" class="field-control"
                    aria-describedby="note-hint note-error" @error('note') aria-invalid="true" @enderror>{{ old('note') }}</textarea>
                <x-field-error for="note" />
            </div>
        </div>

        {{-- Matches what the app does: owner-only access, no public links, nothing sent for analysis unless asked --}}
        <div class="flex items-start gap-3 pt-6 text-sm text-muted lg:col-start-1">
            <x-icon name="lock" class="mt-0.5 size-4 shrink-0 text-primary" />
            <p class="max-w-prose">
                <strong class="font-semibold text-ink">Only you can see this photo.</strong> It’s stored privately on the RISE server,
                not at a public web address. If it holds location details, RISE shows a copy without them.
                @if ($analysisReady)
                    Nothing goes to an outside service unless you later choose an AI option for it.
                @else
                    It isn’t sent to any outside service.
                @endif
            </p>
        </div>

        <div class="surface mt-6 rounded-2xl border border-line bg-surface p-4 shadow-sheet lg:col-start-1" data-upload-status hidden>
            <div class="flex items-center justify-between gap-3 text-sm font-semibold">
                <p role="status" data-upload-text>Uploading photo…</p>
                <span class="tabular-nums" aria-hidden="true" data-upload-percent>0%</span>
            </div>
            <progress class="upload-progress mt-3" max="100" value="0" aria-label="Upload progress" data-upload-progress></progress>
        </div>

        <div role="alert" class="surface mt-6 flex items-start gap-3 rounded-2xl border border-danger-line bg-danger-tint p-4 shadow-sheet lg:col-start-1" data-upload-failure hidden>
            <x-icon name="alert-circle" class="mt-0.5 text-danger" />
            <p data-failure-text></p>
        </div>

        <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:items-center lg:col-start-1">
            <button type="submit" class="btn btn-primary min-h-12 px-6 text-base" data-submit>
                <x-icon name="check-circle" /> <span data-submit-label>Save photo</span>
            </button>
            <a href="{{ $selected ? route('points.show', $selected) : route('overview') }}" class="btn btn-quiet">Cancel</a>
        </div>
    </form>
</x-layout>
