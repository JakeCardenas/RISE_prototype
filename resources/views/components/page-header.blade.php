@props(['title', 'description' => null, 'back' => null, 'backLabel' => null])

{{-- A page opens with a large title; the way back floats above it as a small glass capsule --}}
<header class="mb-8 sm:mb-10">
    @if ($back)
        <a href="{{ $back }}" class="btn-back glass-thin mb-4">
            <x-icon name="chevron-left" class="size-[1.125rem]" />
            <span class="truncate">{{ $backLabel }}</span>
        </a>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-5">
        <div class="min-w-0 flex-1 basis-[22rem]">
            <h1 class="page-title" data-page-title>{{ $title }}</h1>

            @if ($description)
                <p class="mt-3 max-w-prose text-[1.0625rem] break-words text-muted">{{ $description }}</p>
            @endif

            {{ $meta ?? '' }}
        </div>

        @isset($actions)
            <div class="flex flex-wrap gap-2">{{ $actions }}</div>
        @endisset
    </div>
</header>
