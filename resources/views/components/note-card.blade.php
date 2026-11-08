@props(['note'])

{{-- One note in the Notes list: title, text, when it was last changed, and its actions --}}
<article aria-labelledby="note-{{ $note->id }}-title" class="grid gap-x-8 gap-y-3 py-6 sm:grid-cols-[minmax(0,1fr)_auto]">
    <div class="min-w-0">
        <h2 id="note-{{ $note->id }}-title" class="text-lg font-semibold tracking-[-0.01em] break-words text-heading">{{ $note->title }}</h2>
        @if ($note->updated_at)
            <p class="meta mt-0.5">Last changed <x-local-time :time="$note->updated_at" /></p>
        @endif
        <p class="mt-3 max-w-prose break-words whitespace-pre-line">{{ $note->body }}</p>
    </div>

    <div class="flex flex-wrap items-start gap-2 sm:justify-end">
        <a href="{{ route('notes.edit', $note) }}" class="btn btn-secondary">
            <x-icon name="pencil" class="size-4" /> Edit <span class="sr-only">“{{ $note->title }}”</span>
        </a>
        <x-delete-confirm :action="route('notes.destroy', $note)" label="Delete…" confirm="Delete note"
            question="Delete this note permanently? This can’t be undone." class="max-sm:basis-full" />
    </div>
</article>
