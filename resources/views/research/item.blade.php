@use('App\Models\EvaluationItem')

@php
    $chips = [
        'label' => ['Label', ['' => 'Not labelled', ...EvaluationItem::LABELS]],
        'blur' => ['Focus', ['' => 'Not recorded', ...EvaluationItem::BLUR]],
        'lighting' => ['Lighting', ['' => 'Not recorded', ...EvaluationItem::LIGHTING]],
        'surface' => ['Surface', ['' => 'Not recorded', ...EvaluationItem::SURFACES]],
        'split' => ['Use', EvaluationItem::SPLITS],
    ];
@endphp

<x-layout :title="$item->code.' · Evaluation'">
    <x-page-header :title="$item->code" :back="route('research.items.index')" back-label="Images and labels"
        description="Label this image from what you can see in it. Model results aren’t shown here, so they can’t sway the label." />

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] lg:items-start lg:gap-10">
        <section aria-labelledby="image-heading" class="min-w-0">
            <h2 id="image-heading" class="sr-only">Image</h2>
            <div class="board flex justify-center p-3 sm:p-5">
                <div class="annotate relative w-fit max-w-full" data-annotate>
                    <img src="{{ route('research.items.image', $item) }}" alt="Research image {{ $item->code }}" width="{{ $item->width }}" height="{{ $item->height }}"
                        draggable="false" class="print block h-auto max-h-[72svh] w-auto max-w-full shadow-print">
                    <svg class="pointer-events-none absolute inset-0 size-full" viewBox="0 0 1000 1000" preserveAspectRatio="none" aria-hidden="true" data-annotate-layer></svg>
                </div>
            </div>

            {{-- Drawing needs a pointer and JavaScript; boxes are optional and only used to score location --}}
            <div class="mt-3 space-y-3" data-annotate-tools hidden>
                <p class="meta">
                    For an image labelled crack-like, drag across the image to draw a box around each crack-like line. Boxes are optional and
                    are used only to score providers that return locations.
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn btn-secondary" data-annotate-undo>Remove last box</button>
                    <button type="button" class="btn btn-quiet" data-annotate-clear>Clear boxes</button>
                    <p class="meta" aria-live="polite" data-annotate-count></p>
                </div>
            </div>
        </section>

        <div class="min-w-0 space-y-6">
            <form method="POST" action="{{ route('research.items.update', $item) }}" class="sheet sheet-solid space-y-6 p-5 sm:p-7">
                @csrf
                @method('PUT')
                <input type="hidden" name="regions" value="{{ old('regions', json_encode($item->regions ?? [])) }}" data-annotate-input>

                @foreach ($chips as $field => [$legend, $options])
                    <fieldset>
                        <legend class="field-label">{{ $legend }}</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($options as $value => $text)
                                <label class="choice-chip">
                                    <input type="radio" name="{{ $field }}" value="{{ $value }}" class="sr-only" @checked((string) old($field, $item->{$field}) === (string) $value)
                                        @if ($field === 'label') data-annotate-label @endif>
                                    {{ $text }}
                                </label>
                            @endforeach
                        </div>
                        @if ($field === 'split')
                            <p class="field-hint mt-2">Only held-out images are sent in runs. Use development images to try wording, never to measure it.</p>
                        @endif
                        <x-field-error :for="$field" />
                    </fieldset>
                @endforeach

                <x-field-error for="regions" />

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="submit" name="then_next" value="1" class="btn btn-secondary">Save and label the next</button>
                </div>
            </form>

            <div class="sheet sheet-solid p-5 sm:p-7">
                <h2 class="section-title">About this image</h2>
                <dl class="title-block mt-4 grid-cols-1">
                    <div>
                        <dt>Added</dt>
                        <dd><x-local-time :time="$item->created_at" /></dd>
                    </div>
                    <div>
                        <dt>Authorization</dt>
                        <dd class="font-medium">{{ EvaluationItem::AUTHORIZATIONS[$item->authorization] ?? $item->authorization }}{{ $item->authorization_note ? ' · '.$item->authorization_note : '' }}</dd>
                    </div>
                    <div>
                        <dt>Labelled</dt>
                        <dd>@if ($item->labelled_at) <x-local-time :time="$item->labelled_at" /> @else Not yet @endif</dd>
                    </div>
                    <div>
                        <dt>Stored copy</dt>
                        <dd class="font-medium">{{ $item->width }} × {{ $item->height }} pixels, re-encoded without embedded details</dd>
                    </div>
                </dl>

                <div class="mt-5 flex flex-wrap gap-2">
                    @if ($previous)
                        <a href="{{ route('research.items.edit', $previous) }}" class="btn btn-quiet"><x-icon name="chevron-left" class="size-4" /> Newer image</a>
                    @endif
                    @if ($next)
                        <a href="{{ route('research.items.edit', $next) }}" class="btn btn-quiet">Older image <x-icon name="chevron-right" class="size-4" /></a>
                    @endif
                    @if ($nextUnlabelled && ! $item->label)
                        <a href="{{ route('research.items.edit', $nextUnlabelled) }}" class="btn btn-quiet">Skip to the next unlabelled</a>
                    @endif
                </div>
            </div>

            {{-- For withdrawn consent or a mistaken upload: the image and its results in any runs go together --}}
            <x-delete-confirm :action="route('research.items.destroy', $item)" label="Delete image…" confirm="Delete image"
                question="Delete this image and its results in every run permanently? Those runs’ figures will change. This can’t be undone." />
        </div>
    </div>
</x-layout>
