@props(['action', 'feedback' => null, 'id' => 'feedback', 'subject' => 'result', 'level' => 2])

@use('App\Models\AiFeedback')

{{-- The resident's own rating, kept apart from the model's words: saved with the result, never sent to the provider --}}
<section id="{{ $id }}" aria-labelledby="{{ $id }}-title" {{ $attributes->class('scroll-mt-28') }}>
    <h{{ $level }} id="{{ $id }}-title" @class(['section-title' => $level === 2, 'font-semibold text-heading' => $level !== 2])>Was this {{ $subject }} useful?</h{{ $level }}>
    <p class="mt-1 text-sm text-muted">
        Your answer is saved with this {{ $subject }}, apart from the model’s words and your photo notes. RISE doesn’t send it
        to the AI provider or use it to train a model.
    </p>

    <form method="POST" action="{{ $action }}" class="mt-4">
        @csrf
        @method('PUT')

        <fieldset>
            <legend class="sr-only">Your rating</legend>
            <div class="flex flex-wrap gap-2">
                @foreach (AiFeedback::RATINGS as $value => $text)
                    <label class="choice-chip">
                        <input type="radio" name="rating" value="{{ $value }}" class="sr-only" required @checked(old('rating', $feedback?->rating) === $value)
                            @error('rating') aria-invalid="true" aria-errormessage="rating-error" @enderror>
                        {{ $text }}
                    </label>
                @endforeach
            </div>
            <x-field-error for="rating" />
        </fieldset>

        <label for="{{ $id }}-note" class="field-label mt-5">Note <span class="font-normal text-muted">(optional)</span></label>
        <p id="{{ $id }}-note-hint" class="field-hint">For example, what the model got right or wrong.</p>
        <textarea id="{{ $id }}-note" name="feedback_note" rows="3" maxlength="{{ AiFeedback::MAX_NOTE }}" class="field-control"
            aria-describedby="{{ $id }}-note-hint feedback_note-error" @error('feedback_note') aria-invalid="true" @enderror>{{ old('feedback_note', $feedback?->note) }}</textarea>
        <x-field-error for="feedback_note" />

        <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
            <button type="submit" class="btn btn-secondary">{{ $feedback ? 'Update feedback' : 'Save feedback' }}</button>
            @if ($feedback)
                <p class="meta">Saved <x-local-time :time="$feedback->updated_at" /></p>
            @endif
        </div>
    </form>
</section>
