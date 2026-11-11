{{-- Only shown when debugging is off; it never claims that something was or wasn't saved, because it can't know --}}
<x-error-page title="Something went wrong" icon="alert-circle" code="500">
    <p>RISE ran into a problem on the server and couldn’t finish this. If you were saving something, check whether it was saved before you try again.</p>

    <x-slot:actions>
        @if (request()->isMethod('GET'))
            <a href="{{ request()->fullUrl() }}" class="btn btn-primary"><x-icon name="refresh" class="size-4" /> Try again</a>
        @endif
        <a href="{{ route('overview') }}" @class(['btn', 'btn-secondary' => request()->isMethod('GET'), 'btn-primary' => ! request()->isMethod('GET')])>
            <x-icon name="home" class="size-4" /> Go to Overview
        </a>
    </x-slot:actions>
</x-error-page>
