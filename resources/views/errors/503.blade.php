{{-- Maintenance mode (php artisan down). "Try again" reloads this address, so it also works when the page is pre-rendered. --}}
<x-error-page title="RISE is being updated" icon="clock" code="503">
    <p>It will be back shortly. Please try again in a few minutes.</p>

    <x-slot:actions>
        <a href="" class="btn btn-primary"><x-icon name="refresh" class="size-4" /> Try again</a>
    </x-slot:actions>
</x-error-page>
