@use('App\Support\PhotoUploads')

{{-- Shown when a photo is bigger than PHP's post_max_size, so the request never reached the form (or the session) --}}
<x-error-page title="This photo is too large" icon="image" code="413">
    <p>
        RISE accepts photos up to {{ PhotoUploads::maxLabel() }}, so this one wasn’t saved. Choose a smaller photo,
        or export a smaller JPEG copy and add that instead.
    </p>

    <x-slot:actions>
        <a href="{{ route('records.create') }}" class="btn btn-primary"><x-icon name="arrow-left" class="size-4" /> Back to Add photo</a>
    </x-slot:actions>
</x-error-page>
