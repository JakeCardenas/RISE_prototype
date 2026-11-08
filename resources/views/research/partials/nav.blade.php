{{-- The workspace's two pages, apart from residents' spots and photos --}}
<nav aria-label="Evaluation workspace" class="mb-8">
    <div class="segmented glass">
        <a href="{{ route('research.index') }}" class="segmented-option" @if (request()->routeIs('research.index', 'research.runs.*')) aria-current="page" @endif>
            <x-icon name="list" class="size-4" /> Runs and results
        </a>
        <a href="{{ route('research.items.index') }}" class="segmented-option" @if (request()->routeIs('research.items.*')) aria-current="page" @endif>
            <x-icon name="image" class="size-4" /> Images and labels
        </a>
    </div>
</nav>
