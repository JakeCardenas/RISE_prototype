{{-- A missing page, a deleted spot or photo, or one that belongs to another account: all look the same from outside --}}
<x-error-page title="We can’t find that page" icon="search" code="404">
    <p>It may have been deleted, or the link may be incomplete. Spots and photos only open for the account that added them.</p>

    <x-slot:actions>
        <a href="{{ route('overview') }}" class="btn btn-primary"><x-icon name="home" class="size-4" /> Go to Overview</a>
    </x-slot:actions>
</x-error-page>
