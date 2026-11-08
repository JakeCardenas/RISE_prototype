<x-layout title="New note" width="narrow">
    <x-page-header title="New note" :back="route('notes.index')" back-label="Notes" />

    <form action="{{ route('notes.store') }}" method="POST" class="sheet p-5 sm:p-7">
        @csrf

        <x-input-field label="Title" name="title" maxlength="100" />
        <x-input-field label="Note" name="body" type="textarea" />

        <div class="mt-2 flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center">
            <button type="submit" class="btn btn-primary">Save note</button>
            <a href="{{ route('notes.index') }}" class="btn btn-quiet">Cancel</a>
        </div>
    </form>
</x-layout>
