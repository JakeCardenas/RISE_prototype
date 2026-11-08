@use('App\Support\PhotoUploads')

{{-- Shown when a photo is bigger than PHP's post_max_size, so the request never reached the form --}}
<x-layout title="Photo too large" width="narrow">
    <section aria-labelledby="too-large-heading" class="sheet px-6 py-12 sm:px-10">
        <span class="drop-card size-16"><x-icon name="image" class="size-7" /></span>
        <h1 id="too-large-heading" class="page-title mt-6">This photo is too large</h1>
        <p class="mt-3 max-w-prose text-[1.0625rem]">
            RISE accepts photos up to {{ PhotoUploads::maxLabel() }}, so this one wasn’t saved. Choose a smaller photo,
            or export a smaller JPEG copy and add that instead.
        </p>
        <a href="{{ route('records.create') }}" class="btn btn-primary mt-8"><x-icon name="arrow-left" class="size-4" /> Back to Add photo</a>
    </section>
</x-layout>
