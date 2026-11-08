<x-layout title="Notes">
    <x-page-header title="Notes"
        description="Free-form notes from the original notes app, kept separate from RISE’s photo records: they aren’t linked to a monitoring point and don’t appear in its timeline, comparison or report. To describe a photo, add a note when you save it.">
        {{-- An empty list has its own button below, so only one primary action shows at a time --}}
        @if ($notes->isNotEmpty())
            <x-slot:actions>
                <a href="{{ route('notes.create') }}" class="btn btn-primary"><x-icon name="plus" /> New note</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($notes->isEmpty())
        <section class="sheet flex flex-col items-center px-6 py-14 text-center">
            <span class="drop-card size-16"><x-icon name="notebook" class="size-7" /></span>
            <h2 class="mt-5 text-lg font-semibold text-heading">No notes yet</h2>
            <p class="mt-1 max-w-sm text-muted">Notes you write here stay private to your account.</p>
            <a href="{{ route('notes.create') }}" class="btn btn-primary mt-6"><x-icon name="plus" /> New note</a>
        </section>
    @else
        <div class="sheet max-w-4xl divide-y divide-line px-5 sm:px-7">
            @foreach ($notes as $note)
                <x-note-card :note="$note" />
            @endforeach
        </div>
    @endif
</x-layout>
