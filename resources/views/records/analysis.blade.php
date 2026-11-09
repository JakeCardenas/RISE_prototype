@use('App\Models\PhotoAnalysis')
@use('App\Services\Analysis\AnalysisProviders')
@use('App\Support\AiWording')
@use('App\Support\Observations')

@php
    $position = Observations::position($record);
    $isBaseline = $position === 1;
    $status = $analysis?->status;
    $state = $analysis?->displayState();
    $regions = $status === PhotoAnalysis::COMPLETED ? $analysis->regions() : [];
    $observation = $analysis?->observation();
    // A provider that describes the photo in words (Gemini) gets the written result; a region detector's stays as it was
    $written = $status === PhotoAnalysis::COMPLETED && isset($analysis->result['observation']);
    $provider = $analysis?->provider ?? $detector->name();
    $providerName = AnalysisProviders::label($provider);
    $stillWaiting = $analysis?->status === PhotoAnalysis::PENDING && $analysis->requested_at->lt(now()->subMinutes(2));
    // While a request runs or after it fails, phones show its status before the photo, so it's never below the fold
    $statusFirst = $analysis?->isWaiting() || $status === PhotoAnalysis::FAILED;
    $nextActions = [
        'retake_photo' => [route('records.create', ['point' => $point]), 'Take another photo', 'camera'],
        'compare_with_baseline' => $isBaseline ? null : [route('points.compare', [$point, 'second' => $record->id]), 'Compare with baseline', 'columns'],
        'keep_monitoring' => [route('points.show', $point), 'Plan the next photo', 'calendar'],
    ];
@endphp

<x-layout title="AI photo analysis">
    <x-page-header title="AI photo analysis" :back="Observations::entryUrl($point, $record, $position)" :back-label="$point->label">
        <x-slot:meta>
            <p class="meta mt-3">
                <span class="font-semibold text-heading">{{ Observations::label($position) }}</span> of {{ $point->label }} · saved <x-local-time :time="$record->submitted_at" />
            </p>
        </x-slot:meta>
    </x-page-header>

    {{-- Shown in every state --}}
    <p class="surface -mt-2 mb-8 flex items-start gap-3 rounded-2xl bg-well px-4 py-3 text-sm sm:px-5" role="note">
        <x-icon name="info" class="mt-0.5 size-4 text-primary" />
        <span>AI image results can be wrong. They do not identify a crack’s cause or determine whether a building is safe. Contact a qualified professional for an assessment.</span>
    </p>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] lg:items-start lg:gap-10">
        {{-- The photo, with the model's marked regions drawn over it when there are any --}}
        <section class="analysis-view min-w-0" aria-label="Photo">
            @if ($regions)
                <fieldset class="mb-4">
                    <legend class="sr-only">Show</legend>
                    <div class="segmented glass">
                        <label class="segmented-option"><input type="radio" name="view" value="marked" class="sr-only" checked> Marked-up result</label>
                        <label class="segmented-option"><input type="radio" name="view" value="original" class="sr-only"> Original photo</label>
                    </div>
                </fieldset>
            @endif

            <div class="board flex justify-center p-3 sm:p-5">
                <div class="relative w-fit max-w-full">
                    <x-record-photo :record="$record" loading="eager"
                        :alt="Observations::label($position).' photo of '.$point->label.', saved '.$record->submitted_at->format('j M Y')"
                        class="print h-auto max-h-[72svh] w-auto max-w-full shadow-print" />

                    @if ($regions)
                        <svg class="analysis-overlay pointer-events-none absolute inset-0 size-full" viewBox="0 0 1000 1000" preserveAspectRatio="none"
                            role="img" aria-label="{{ trans_choice('{1} One possible crack-like area|[2,*] :count possible crack-like areas', count($regions)) }} outlined in magenta by the AI model">
                            @foreach ($regions as $region)
                                @if ($region['polygon'])
                                    @php($points = collect($region['polygon'])->map(fn ($point) => round($point[0] * 1000, 1).','.round($point[1] * 1000, 1))->implode(' '))
                                    <polygon points="{{ $points }}" class="mark-halo" vector-effect="non-scaling-stroke" />
                                    <polygon points="{{ $points }}" class="mark-line" vector-effect="non-scaling-stroke" />
                                @else
                                    @php([$x, $y, $w, $h] = array_map(fn ($value) => round($value * 1000, 1), $region['box']))
                                    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $w }}" height="{{ $h }}" class="mark-halo" vector-effect="non-scaling-stroke" />
                                    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $w }}" height="{{ $h }}" class="mark-line" vector-effect="non-scaling-stroke" />
                                @endif
                            @endforeach
                        </svg>
                    @endif
                </div>
            </div>

            @if ($regions)
                <p class="mt-3 flex items-center gap-2 text-sm font-medium">
                    <svg viewBox="0 0 20 14" class="h-3.5 w-5 shrink-0" aria-hidden="true" focusable="false">
                        <rect x="2" y="2" width="16" height="10" rx="2" class="mark-halo" />
                        <rect x="2" y="2" width="16" height="10" rx="2" class="mark-line" />
                    </svg>
                    Possible crack-like area detected by AI
                </p>
            @elseif ($written)
                <p class="meta mt-3">{{ $providerName }} doesn’t mark where anything is, so your photo is shown exactly as you saved it.</p>
            @endif
        </section>

        {{-- One block per state, all with the same order: what is happening, then what you can do --}}
        <section aria-labelledby="result-heading" @class(['min-w-0 space-y-6', 'max-lg:order-first' => $statusFirst])>
            @if (! $analysis || $status === PhotoAnalysis::NOT_CONFIGURED)
                <div class="sheet sheet-solid p-5 sm:p-7">
                    @if ($detector->isConfigured())
                        <h2 id="result-heading" class="flex items-center gap-2.5 section-title">
                            <x-icon name="scan" class="text-primary" /> Check this photo with AI
                        </h2>
                        @if ($provider === 'gemini')
                            <p class="mt-3">
                                An AI model can describe what it sees in this photo in a few cautious sentences, and say whether the photo’s
                                quality limits what it can see. It doesn’t mark, measure or judge anything.
                            </p>
                        @else
                            <p class="mt-3">An image model can mark areas in this photo that look like cracks, for you to review alongside your own notes.</p>
                        @endif

                        {{-- What leaves RISE, stated before the button that sends it --}}
                        <h3 class="mt-6 text-sm font-semibold text-ink">If you choose Analyze with AI</h3>
                        <dl class="title-block mt-2 grid-cols-1">
                            <div>
                                <dt>Sent to</dt>
                                <dd>{{ $providerName }}, the image-analysis service set up for this RISE server</dd>
                            </div>
                            <div>
                                <dt>Model</dt>
                                <dd>{{ $detector->modelId() }}</dd>
                            </div>
                            <div>
                                <dt>What is sent</dt>
                                <dd class="font-medium">
                                    A copy of this photo, resized to at most {{ number_format($detector->maxImageSide()) }} pixels on its longest side, with
                                    location and other embedded details removed{{ $provider === 'gemini' ? ', plus RISE’s standard instructions for the model' : '' }}
                                </dd>
                            </div>
                            <div>
                                <dt>Not sent</dt>
                                <dd class="font-medium">Your notes, the spot’s name or room, or your account details</dd>
                            </div>
                            <div>
                                <dt>Storage and training</dt>
                                <dd class="font-medium">
                                    @if ($provider === 'gemini')
                                        RISE asks Gemini not to save this request for follow-up questions (store: false). How Google handles the copy,
                                        including how long it’s kept and whether it may be used to improve Google’s products, depends on the terms of
                                        the Gemini API account this server uses
                                    @else
                                        RISE turns off {{ $providerName }}’s option to add the photo to a training dataset; how {{ $providerName }}
                                        handles the copy is covered by its own terms
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        <form method="POST" action="{{ route('records.analysis.store', $record) }}" class="mt-6" data-submit-once>
                            @csrf
                            <button type="submit" class="btn btn-primary w-full sm:w-auto"><x-icon name="scan" /> Analyze with AI</button>
                        </form>
                        <p class="mt-3 text-sm text-muted">You can keep this photo without analysing it.</p>
                    @else
                        <h2 id="result-heading" class="flex items-center gap-2.5 section-title">
                            <x-icon name="info" class="text-primary" /> AI analysis isn’t set up
                        </h2>
                        <p class="mt-3">This RISE server doesn’t have an image-analysis provider configured, so nothing has been sent anywhere. Your photo is saved and you can keep using it.</p>
                        <p class="mt-3 text-sm text-muted">The person who runs RISE can add a Google Gemini or Roboflow key; see the README.</p>
                    @endif
                </div>
            @elseif ($analysis->isWaiting())
                <x-ai-progress class="sheet sheet-solid p-5 sm:p-7" heading-id="result-heading"
                    :status-url="route('records.analysis.status', $record)" :stage="$analysis->stage()" :elapsed="$analysis->elapsedSeconds()"
                    :provider="$providerName" :requested-at="$analysis->requested_at" :still-waiting="$stillWaiting" />
            @elseif ($status === PhotoAnalysis::FAILED)
                <div class="surface rounded-sheet border border-danger-line bg-danger-tint p-5 sm:p-7">
                    <h2 id="result-heading" class="flex items-center gap-2.5 text-[1.1875rem] font-semibold tracking-[-0.015em] text-danger">
                        <x-icon name="alert-circle" /> The analysis didn’t finish
                    </h2>
                    <p class="mt-1.5 text-sm font-semibold text-muted">{{ $analysis->failureTitle() }}</p>
                    <p class="mt-3">{{ $analysis->failureMessage() }} Your photo is still saved.</p>
                    <form method="POST" action="{{ route('records.analysis.store', $record) }}" class="mt-6" data-submit-once>
                        @csrf
                        <button type="submit" @class(['btn', 'btn-primary' => $state !== 'failed', 'btn-secondary' => $state === 'failed'])><x-icon name="refresh" /> Try again</button>
                    </form>
                    <p class="mt-3 text-sm text-muted">
                        @if ($state === 'failed')
                            It may not work until the person who runs RISE changes the settings.
                        @endif
                        Trying again sends a copy of this photo, without location details, to {{ $providerName }} again.
                    </p>
                </div>
            @elseif (! $observation)
                {{-- A stored result that no longer passes RISE's checks is never shown --}}
                <div class="sheet sheet-solid p-5 sm:p-7">
                    <h2 id="result-heading" class="section-title">This result can’t be shown</h2>
                    <p class="mt-3">RISE couldn’t check the stored result, so it isn’t shown. You can ask for a new analysis.</p>
                    <form method="POST" action="{{ route('records.analysis.store', $record) }}" class="mt-5" data-submit-once>
                        @csrf
                        <button type="submit" class="btn btn-secondary"><x-icon name="refresh" /> Try again</button>
                    </form>
                </div>
            @else
                @if ($written)
                    {{-- The bounded finding first, in RISE's own words; then the model's words, clearly marked as such --}}
                    <div class="sheet sheet-solid p-5 sm:p-7">
                        <h2 id="result-heading" class="flex items-start gap-3 section-title">
                            <x-icon name="scan" class="mt-0.5 shrink-0 text-primary" />
                            <span>{{ AiWording::finding($observation->finding) }}</span>
                        </h2>
                        <p class="mt-3">{{ AiWording::findingNote($observation->finding) }}</p>

                        <h3 class="mt-6 text-sm font-semibold text-ink">In the model’s words</h3>
                        @if ($observation->summary || $observation->observations)
                            <div class="mt-2 rounded-2xl border border-line bg-canvas px-4 py-3">
                                @if ($observation->summary)
                                    <p>{{ $observation->summary }}</p>
                                @endif
                                @if ($observation->observations)
                                    <ul @class(['list-disc space-y-1.5 pl-5 marker:text-primary', 'mt-2' => $observation->summary])>
                                        @foreach ($observation->observations as $sentence)
                                            <li>{{ $sentence }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <p class="meta mt-2">RISE leaves out any sentence with a measurement, cause, judgement or advice. What’s left can still be wrong.</p>
                        @else
                            <p class="mt-2 text-muted">The model gave no description that RISE could show.</p>
                        @endif

                        <h3 class="mt-6 text-sm font-semibold text-ink">Photo quality</h3>
                        @if ($observation->limitations)
                            <ul class="mt-2 space-y-2 text-[0.9375rem]">
                                @foreach ($observation->limitations as $limitation)
                                    <li class="flex items-start gap-2.5"><x-icon name="alert-triangle" class="mt-0.5 size-4 shrink-0 text-primary" /> {{ AiWording::limitation($limitation) }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-muted">The model didn’t note anything about this photo that limits what it can see.</p>
                        @endif
                    </div>
                @else
                    <div class="sheet sheet-solid p-5 sm:p-7">
                        <h2 id="result-heading" class="flex items-start gap-3 section-title">
                            {{-- The same magenta as the outlines, so the heading and the photo read together --}}
                            <span class="mt-[0.3125rem] size-3.5 shrink-0 rounded-[3px] border-2 border-mark bg-mark/20" aria-hidden="true"></span>
                            <span>
                                @if ($regions)
                                    The model marked possible crack-like areas.
                                @else
                                    The model did not mark an area in this photo. It can still miss cracks.
                                @endif
                            </span>
                        </h2>
                        @if ($regions)
                            <p class="mt-3">
                                {{ trans_choice('{1} One area is|[2,*] :count areas are', count($regions)) }} outlined in magenta. Switch to the original photo
                                to look at the surface without markings, and compare it with your own observations.
                            </p>
                        @else
                            <p class="mt-3">Keep photographing this spot over time and compare the photos yourself.</p>
                        @endif
                    </div>
                @endif

                {{-- One step from RISE's fixed list; the model picks it, RISE words it --}}
                <div class="sheet sheet-solid p-5 sm:p-7">
                    <h2 class="section-title">Suggested next step</h2>
                    <p class="mt-2">{{ AiWording::nextStep($observation->nextStep, $isBaseline) }}</p>

                    @if ($action = $nextActions[$observation->nextStep] ?? null)
                        <a href="{{ $action[0] }}" class="btn btn-secondary mt-4"><x-icon :name="$action[2]" /> {{ $action[1] }}</a>
                    @elseif ($observation->nextStep === 'add_note')
                        {{-- The resident's own note: saved only when they press Save note --}}
                        <form method="POST" action="{{ route('records.note.update', $record) }}" class="mt-4">
                            @csrf
                            @method('PATCH')
                            <label for="note" class="field-label">Your note for this photo</label>
                            <textarea id="note" name="note" rows="4" maxlength="1000" class="field-control" aria-describedby="note-error"
                                @error('note') aria-invalid="true" @enderror>{{ old('note', $record->note) }}</textarea>
                            <x-field-error for="note" />
                            <button type="submit" class="btn btn-secondary mt-3">Save note</button>
                        </form>
                    @endif
                </div>

                <div class="sheet sheet-solid p-5 sm:p-7">
                    <h2 class="section-title">About this result</h2>
                    <dl class="title-block mt-4">
                        <div class="col-span-2">
                            <dt>Model</dt>
                            <dd>{{ $analysis->model_version ?? $analysis->model_id }}</dd>
                        </div>
                        <div>
                            <dt>Provider</dt>
                            <dd>{{ $providerName }}</dd>
                        </div>
                        <div>
                            <dt>Status</dt>
                            <dd>Completed</dd>
                        </div>
                        <div>
                            <dt>Requested</dt>
                            <dd><x-local-time :time="$analysis->requested_at" /></dd>
                        </div>
                        <div>
                            <dt>Analysed</dt>
                            <dd><x-local-time :time="$analysis->completed_at" /></dd>
                        </div>
                        @if ($written)
                            <div class="col-span-2">
                                <dt>Model score</dt>
                                <dd class="font-medium">None. This model gives no calibrated score, so RISE doesn’t show one.</dd>
                            </div>
                        @else
                            <div>
                                <dt>Areas marked</dt>
                                <dd>{{ $analysis->region_count }}</dd>
                            </div>
                            <div>
                                <dt>Score threshold</dt>
                                <dd>{{ number_format($analysis->confidence_threshold, 2) }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($regions)
                        <details class="mt-4">
                            <summary class="link inline-flex min-h-11 items-center gap-1.5">
                                <x-icon name="chevron-down" class="size-4" /> Model score for each area
                            </summary>
                            <p class="mt-1 text-sm text-muted">
                                A model score shows how closely an area matched what the model learned to mark. It isn’t the chance that a crack
                                is real, and it says nothing about size, cause or safety.
                            </p>
                            <ol class="mt-3 divide-y divide-line rounded-control border border-line text-sm tabular-nums">
                                @foreach ($regions as $region)
                                    <li class="flex flex-wrap justify-between gap-x-4 px-3.5 py-2">
                                        <span class="font-medium">Area {{ $loop->iteration }}</span>
                                        <span>model score {{ number_format($region['score'], 2) }}{{ $region['polygon'] ? '' : ' (box only; the model returned no outline)' }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </details>
                    @endif

                    <p class="mt-5 text-sm text-muted">
                        @if ($written)
                            No person has checked this result. Any accuracy figures for this model come from a separate evaluation on
                            human-labelled research photos.
                        @else
                            Benchmark figures published for this model come from its own test images. They aren’t RISE results and don’t show how
                            it performs on photos of homes like yours.
                        @endif
                    </p>
                </div>

                <x-ai-feedback :action="route('analyses.feedback', $analysis)" :feedback="$analysis->feedback" class="sheet sheet-solid p-5 sm:p-7" />
            @endif
        </section>
    </div>
</x-layout>
