<x-error-page title="This page isn’t available" icon="lock" code="403">
    <p>Your account can’t open this page. If you followed a link, it may belong to another account.</p>

    <x-slot:actions>
        <a href="{{ route('overview') }}" class="btn btn-primary"><x-icon name="home" class="size-4" /> Go to Overview</a>
    </x-slot:actions>
</x-error-page>
