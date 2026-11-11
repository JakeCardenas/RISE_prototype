@props(['placement' => 'top'])

@php
    $user = auth()->user();
    $initials = Str::of($user->name)->explode(' ')->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('');
@endphp

{{-- The signed-in resident and Sign out; in the sidebar it opens upwards, in the phone's top bar it drops down --}}
<details class="relative" data-menu>
    @if ($placement === 'rail')
        <summary class="rail-link w-full gap-3 pr-2">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand text-xs font-bold text-white" aria-hidden="true">{{ $initials }}</span>
            <span class="min-w-0 flex-1 truncate font-semibold text-ink">{{ $user->name }}</span>
            <span class="sr-only">, account</span>
            <x-icon name="chevron-up" class="size-4" />
        </summary>
    @else
        <summary class="flex min-h-11 items-center gap-1 rounded-full p-0.5 pr-2 text-heading hover:bg-white/12">
            <span class="flex size-10 items-center justify-center rounded-full bg-brand text-xs font-bold text-white" aria-hidden="true">{{ $initials }}</span>
            <span class="sr-only">Account</span>
            <x-icon name="chevron-down" class="size-4 text-muted" />
        </summary>
    @endif

    <div @class([
        'menu-panel absolute',
        'inset-x-0 bottom-full mb-2 origin-bottom' => $placement === 'rail',
        'top-full right-0 mt-3 w-72 max-w-[calc(100vw-1.5rem)] origin-top-right' => $placement !== 'rail',
    ])>
        <div class="px-3 pt-2 pb-3">
            <p class="text-xs font-semibold text-muted">Signed in as</p>
            <p class="mt-0.5 truncate font-semibold text-ink">{{ $user->name }}</p>
            <p class="truncate text-sm text-muted">{{ $user->email }}</p>
        </div>
        @if ($user->is_researcher)
            {{-- Only researcher accounts see the capstone evaluation workspace --}}
            <a href="{{ route('research.index') }}" class="menu-item border-t border-ink/10" @if (request()->routeIs('research.*')) aria-current="page" @endif>
                <x-icon name="list" class="size-4 text-muted" /> Evaluation workspace
            </a>
        @endif
        <form action="{{ route('logout') }}" method="POST" class="border-t border-ink/10 pt-1.5">
            @csrf
            <button type="submit" class="menu-item">
                <x-icon name="log-out" class="size-4 text-muted" /> Sign out
            </button>
        </form>
    </div>
</details>
