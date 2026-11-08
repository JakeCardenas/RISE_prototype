@use('App\Support\Observations')

{{--
    Optional AI comparison of the two photos on screen. Nothing is sent until the resident chooses to run it, the
    provider and what is sent are stated first, and the result is never saved as the resident's own note: they can
    dismiss it, copy it, or edit it into their note and save that themselves.
--}}
<section id="ai-comparison" aria-labelledby="ai-comparison-title" class="sheet mt-10 scroll-mt-28 p-5 sm:p-7">
    <h2 id="ai-comparison-title" class="section-title flex flex-wrap items-center gap-x-2.5 gap-y-1">
        <x-icon name="scan" class="text-primary" /> AI comparison <span class="tag">Optional</span>
    </h2>

    @if (! $comparer->isConfigured())
        <p class="mt-2 max-w-prose text-muted">
            AI comparison isn’t set up on this RISE server, so nothing is sent anywhere. The person who runs RISE can turn it on; see the README.
        </p>
    @elseif ($aiComparison?->isWaiting())
        <div class="mt-3" data-analysis-waiting data-status-url="{{ route('ai-comparisons.status', $aiComparison) }}">
            <p class="flex items-center gap-2.5 font-semibold" role="status"><x-icon name="clock" class="text-primary" /> Comparing the two photos…</p>
            <p class="mt-1 text-sm text-muted">This usually takes under a minute. You can leave this page.</p>
            <p class="mt-2 text-sm" data-still-waiting hidden>
                Still waiting. The queue may not be running; the person who runs RISE can start it with <code class="rounded bg-well px-1 py-0.5 text-[0.8125rem]">php artisan queue:work</code>.
            </p>
        </div>
    @elseif ($aiComparison?->status === \App\Models\PhotoComparison::COMPLETED)
        <p class="meta mt-1">From {{ $aiComparison->provider }} · <x-local-time :time="$aiComparison->completed_at" /></p>

        <div class="mt-5 grid gap-x-8 gap-y-5 sm:grid-cols-2">
            @foreach ([
                ['Possible visible differences', $aiComparison->visibleDifferences(), 'It didn’t point out a clear difference. That doesn’t mean nothing changed.'],
                ['Possible photo differences', $aiComparison->photoDifferences(), 'It didn’t point out differences in angle, distance or light.'],
            ] as [$heading, $items, $none])
                <div>
                    <h3 class="font-semibold text-heading">{{ $heading }}</h3>
                    @if ($items)
                        <ul class="mt-2 list-disc space-y-1.5 pl-5 marker:text-primary">
                            @foreach ($items as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-2 text-muted">{{ $none }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <p class="surface mt-6 flex items-start gap-3 rounded-2xl bg-well px-4 py-3 text-sm" role="note">
            <x-icon name="info" class="mt-0.5 size-4 shrink-0 text-primary" />
            <span>AI can be wrong or miss things. It can’t measure a crack, tell what caused it, or say whether your home is safe. Look at the photos yourself, and ask a qualified professional if you’re worried.</span>
        </p>

        <div class="mt-5 flex flex-wrap items-start gap-2">
            @if ($aiComparison->asText())
                <button type="button" class="btn btn-secondary" data-copy-text="{{ $aiComparison->asText() }}" hidden>
                    <x-icon name="layers" class="size-4" /> <span data-copy-label>Copy wording</span>
                </button>
            @endif
            <form method="POST" action="{{ route('ai-comparisons.dismiss', $aiComparison) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-quiet">Dismiss</button>
            </form>
        </div>

        {{-- The resident's own note: prefilled for editing, saved only when they press Save note --}}
        @if ($aiComparison->asText())
            <details class="mt-4 rounded-2xl border border-line" @if ($errors->has('note')) open @endif>
                <summary class="flex min-h-11 items-center gap-2 px-4 text-[0.9375rem] font-semibold text-primary">
                    <x-icon name="pencil" class="size-4" /> Edit into my note for {{ Observations::label($records->search(fn ($record) => $record->is($later)) + 1) }}
                </summary>
                <form method="POST" action="{{ route('records.note.update', $later) }}" class="border-t border-line px-4 py-4">
                    @csrf
                    @method('PATCH')
                    <label for="ai-note" class="field-label">Your note</label>
                    <p id="ai-note-hint" class="field-hint">Change the wording so it says what you see. Nothing is saved until you press Save note.</p>
                    <textarea id="ai-note" name="note" rows="5" maxlength="1000" class="field-control" aria-describedby="ai-note-hint note-error"
                        @error('note') aria-invalid="true" @enderror>{{ old('note', trim(($later->note ? $later->note."\n\n" : '').$aiComparison->asText())) }}</textarea>
                    <x-field-error for="note" />
                    <button type="submit" class="btn btn-primary mt-3">Save note</button>
                </form>
            </details>
        @endif
    @else
        @if ($aiComparison?->status === \App\Models\PhotoComparison::FAILED)
            <p class="mt-2 flex items-start gap-2 font-medium text-danger" role="alert">
                <x-icon name="alert-circle" class="mt-0.5 size-4 shrink-0" /> {{ $aiComparison->failureMessage() }}
            </p>
        @endif

        <p class="mt-2 max-w-prose text-muted">
            An AI model can point out possible differences between these two photos, including differences in how they were taken. It’s a rough aid and can be wrong.
        </p>

        {{-- What leaves RISE, stated before the button that sends it --}}
        <dl class="title-block mt-4 grid-cols-1">
            <div>
                <dt>Sent to</dt>
                <dd>{{ $comparer->name() }}</dd>
            </div>
            <div>
                <dt>What is sent</dt>
                <dd class="font-medium">Smaller copies of these two photos, with location and other hidden details removed</dd>
            </div>
            <div>
                <dt>Not sent</dt>
                <dd class="font-medium">Your notes, your spot’s name or your account details</dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('points.ai-comparisons.store', $point) }}" class="mt-5">
            @csrf
            <input type="hidden" name="earlier" value="{{ $earlier->id }}">
            <input type="hidden" name="later" value="{{ $later->id }}">
            <button type="submit" class="btn btn-primary"><x-icon name="scan" /> {{ $aiComparison ? 'Try again' : 'Compare with AI' }}</button>
        </form>
    @endif
</section>
