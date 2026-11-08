<x-layout title="Edit note" width="narrow">
    <x-page-header title="Edit note" :back="route('notes.index')" back-label="Notes" />

    <form action="{{ route('notes.update', $note) }}" method="POST" class="sheet p-5 sm:p-7">
        @csrf
        @method('PUT')

        <x-input-field label="Title" name="title" :value="$note->title" maxlength="100" />
        <x-input-field label="Note" name="body" type="textarea" :value="$note->body" />

        <div class="mt-2 flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="{{ route('notes.index') }}" class="btn btn-quiet">Cancel</a>
        </div>
    </form>
</x-layout>
