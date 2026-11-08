@use('App\Models\EvaluationItem')

@php
    // Errors on one of several files arrive as images.0, images.1…
    $imageError = $errors->first('images') ?: $errors->first('images.*');
@endphp

<x-layout title="Images and labels · Evaluation">
    <x-page-header title="Images and labels" description="Research images for the capstone evaluation, each labelled by a person. They’re never residents’ RISE records." />

    @include('research.partials.nav')

    <section aria-labelledby="upload-heading" class="sheet sheet-solid p-5 sm:p-7">
        <h2 id="upload-heading" class="section-title">Add research images</h2>
        <p class="mt-2 max-w-prose text-sm text-muted">
            Only add images you’re authorized to use for this evaluation. RISE keeps a re-encoded copy without location or other
            embedded details, never the file you upload, and sends nothing anywhere until you start a run.
        </p>

        <form method="POST" action="{{ route('research.items.store') }}" enctype="multipart/form-data" class="mt-5 max-w-2xl space-y-6">
            @csrf

            <div>
                <label for="images" class="field-label">Images</label>
                <p id="images-hint" class="field-hint">JPEG, PNG or WebP, up to 20 at a time.</p>
                <input type="file" id="images" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="field-control"
                    aria-describedby="images-hint images-error" @if ($imageError) aria-invalid="true" @endif>
                <p id="images-error" class="field-error" @unless ($imageError) hidden @endunless>
                    <x-icon name="alert-circle" class="mt-0.5 size-4" /> <span>{{ $imageError }}</span>
                </p>
            </div>

            <fieldset>
                <legend class="field-label">What allows you to use them</legend>
                <div class="space-y-2.5">
                    @foreach (EvaluationItem::AUTHORIZATIONS as $value => $text)
                        <label class="flex items-start gap-3">
                            <input type="radio" name="authorization" value="{{ $value }}" class="mt-1 size-4 shrink-0" @checked(old('authorization') === $value)
                                @error('authorization') aria-invalid="true" aria-errormessage="authorization-error" @enderror>
                            <span>{{ $text }}</span>
                        </label>
                    @endforeach
                </div>
                <x-field-error for="authorization" />
            </fieldset>

            <div>
                <label for="authorization_note" class="field-label">Reference <span class="font-normal text-muted">(optional)</span></label>
                <p id="authorization_note-hint" class="field-hint">For example, a consent form number. Don’t write names or addresses.</p>
                <input type="text" id="authorization_note" name="authorization_note" maxlength="300" value="{{ old('authorization_note') }}" class="field-control"
                    aria-describedby="authorization_note-hint authorization_note-error">
                <x-field-error for="authorization_note" />
            </div>

            <div>
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="confirm" value="1" class="mt-1 size-4 shrink-0" @checked(old('confirm'))
                        @error('confirm') aria-invalid="true" aria-errormessage="confirm-error" @enderror>
                    <span>
                        I’m authorized to use these images for the RISE evaluation. Any photo from a resident is used only with their
                        specific, written opt-in, and none shows people, addresses or other identifying details.
                    </span>
                </label>
                <x-field-error for="confirm" />
            </div>

            <button type="submit" class="btn btn-primary"><x-icon name="upload" /> Add images</button>
        </form>
    </section>

    <section aria-labelledby="list-heading" class="sheet sheet-solid mt-6 p-5 sm:p-7">
        <h2 id="list-heading" class="section-title">{{ trans_choice('{0} No images yet|{1} 1 image|[2,*] :count images', $items->total()) }}</h2>

        @if ($items->isEmpty())
            <p class="mt-3 text-muted">Add images above, then label each one. Metrics stay unavailable until labelled held-out images have been run.</p>
        @else
            <ul role="list" class="mt-4 divide-y divide-line">
                @foreach ($items as $item)
                    <li>
                        <a href="{{ route('research.items.edit', $item) }}" class="flex items-center gap-4 rounded-2xl px-2 py-3 hover:bg-well">
                            <img src="{{ route('research.items.image', $item) }}" alt="" width="{{ $item->width }}" height="{{ $item->height }}" loading="lazy"
                                class="h-16 w-20 shrink-0 rounded-lg bg-well object-contain">
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-heading tabular-nums">{{ $item->code }}</span>
                                <span class="mt-0.5 block text-sm text-muted">
                                    {{ $item->label ? EvaluationItem::LABELS[$item->label] : 'Not labelled yet' }}
                                    · {{ EvaluationItem::SPLITS[$item->split] }}
                                    @if ($item->regions)
                                        · {{ trans_choice('{1} 1 box|[2,*] :count boxes', count($item->regions)) }}
                                    @endif
                                    @foreach (['blur' => EvaluationItem::BLUR, 'lighting' => EvaluationItem::LIGHTING, 'surface' => EvaluationItem::SURFACES] as $field => $values)
                                        @if ($item->{$field})
                                            · {{ $values[$item->{$field}] }}
                                        @endif
                                    @endforeach
                                </span>
                            </span>
                            <x-icon name="chevron-right" class="size-4 shrink-0 text-muted" />
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($items->hasPages())
                <nav aria-label="Pages" class="mt-5 flex items-center justify-between gap-3 border-t border-line pt-4">
                    @if ($items->previousPageUrl())
                        <a href="{{ $items->previousPageUrl() }}" class="btn btn-secondary"><x-icon name="chevron-left" class="size-4" /> Newer</a>
                    @else
                        <span></span>
                    @endif
                    <span class="meta">Page {{ $items->currentPage() }} of {{ $items->lastPage() }}</span>
                    @if ($items->nextPageUrl())
                        <a href="{{ $items->nextPageUrl() }}" class="btn btn-secondary">Older <x-icon name="chevron-right" class="size-4" /></a>
                    @else
                        <span></span>
                    @endif
                </nav>
            @endif
        @endif
    </section>
</x-layout>
