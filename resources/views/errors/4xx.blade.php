{{-- Any other refused request, for example opening a form's address directly in the browser --}}
<x-error-page title="This page can’t be opened" icon="info" :code="$exception->getStatusCode()">
    <p>The link or request wasn’t one RISE can open, so nothing was changed. Start again from your Overview.</p>

    <x-slot:actions>
        <a href="{{ route('overview') }}" class="btn btn-primary"><x-icon name="home" class="size-4" /> Go to Overview</a>
    </x-slot:actions>
</x-error-page>
