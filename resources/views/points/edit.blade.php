<x-layout :title="'Edit '.$point->label" width="narrow">
    <x-page-header title="Edit monitoring point" :back="route('points.show', $point)" :back-label="$point->label" />

    <form method="POST" action="{{ route('points.update', $point) }}" class="sheet p-5 sm:p-7" novalidate>
        @csrf
        @method('PUT')

        @include('points.partials.fields', ['point' => $point])

        <div class="mt-2 flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="{{ route('points.show', $point) }}" class="btn btn-quiet">Cancel</a>
        </div>
    </form>

    {{-- Kept apart from the form, with its own confirmation step --}}
    <section class="sheet mt-8 p-5 sm:p-7" aria-labelledby="delete-heading">
        <h2 id="delete-heading" class="flex items-center gap-2 text-lg font-semibold text-danger">
            <x-icon name="alert-triangle" /> Delete this monitoring point
        </h2>
        <p class="mt-2 max-w-prose break-words">
            This permanently deletes “{{ $point->label }}”{{ $recordCount ? ' and '.trans_choice('{1} its photo|[2,*] its :count photos', $recordCount) : '' }},
            including any AI results. It can’t be undone.
        </p>

        <x-delete-confirm class="mt-4" :action="route('points.destroy', $point)" label="Delete monitoring point…" confirm="Yes, delete permanently"
            question="Are you sure? The point and its photos will be removed from RISE." />
    </section>
</x-layout>
