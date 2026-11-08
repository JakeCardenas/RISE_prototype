@props(['title' => null, 'width' => 'wide', 'bare' => false])

@php
    // $railPoints and $currentPointId come from the layout view composer in AppServiceProvider (signed-in residents only)
    $railPoints ??= collect();
    $railGroups ??= collect();
    $currentPointId ??= null;
    // Area headings only appear once the resident has filed at least one point under an area
    $showAreas = $railGroups->keys()->contains(fn ($area) => $area !== \App\Support\HomeAreas::UNSORTED);
    $addPhotoUrl = route('records.create', array_filter(['point' => $currentPointId]));
    // On a point that already has a baseline, the main action is its next follow-up
    $isFollowUp = (bool) $railPoints->firstWhere('id', $currentPointId)?->photo_records_count;
    $pointCurrent = fn ($point) => $point->id === $currentPointId ? (request()->routeIs('points.show') ? 'page' : 'true') : null;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f1a14">
    <meta name="color-scheme" content="light">
    <meta name="description" content="RISE keeps a private, dated photo record of cracks and surfaces at home.">
    <title>{{ $title ? $title.' · RISE' : 'RISE' }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="on-backdrop min-h-dvh">
    <a href="#main" class="skip-link">Skip to main content</a>

    {{-- The setting: Montana Misora Homes, dimmed behind every page. Decoration only, never a resident's photo. --}}
    <div class="backdrop" aria-hidden="true">
        <picture>
            <source srcset="{{ asset('images/montana-misora-background.webp') }}" type="image/webp">
            <img src="{{ asset('images/montana-misora-background.png') }}" alt="" width="1578" height="996" decoding="async">
        </picture>
    </div>

    @if ($bare)
        {{-- Sign-in pages and the printable report draw their own full-screen layout --}}
        <main id="main">{{ $slot }}</main>
    @else
        @auth
            {{-- Desktop: a floating glass sidebar, with every monitoring point one click away --}}
            <aside class="glass-thick fixed top-3 bottom-3 left-3 z-30 hidden w-68 flex-col rounded-glass lg:flex">
                <div class="px-4 pt-5 pb-4">
                    <a href="{{ route('overview') }}" class="-m-1 flex items-center gap-3 rounded-2xl p-1">
                        <span class="brand-tile size-10"><x-rise-mark class="size-7.5" /></span>
                        <span>
                            <span class="block text-[1.0625rem] leading-none font-bold tracking-[0.14em] text-heading">RISE</span>
                            <span class="mt-1.5 block text-xs leading-none font-medium text-muted">Home photo journal</span>
                        </span>
                    </a>

                    <a href="{{ $addPhotoUrl }}" class="btn btn-primary mt-6 w-full" @if (request()->routeIs('records.create')) aria-current="page" @endif>
                        <x-icon name="camera" /> {{ $isFollowUp ? 'Add follow-up photo' : 'Add photo' }}
                    </a>
                </div>

                <nav aria-label="Main" class="flex min-h-0 flex-1 flex-col px-2.5">
                    <a href="{{ route('overview') }}" class="rail-link" @if (request()->routeIs('overview')) aria-current="page" @endif>
                        <x-icon name="home" class="size-[1.125rem]" /> Overview
                    </a>

                    <p id="rail-points" class="mt-6 mb-1.5 px-3 text-xs font-semibold text-muted">Your spots</p>
                    <ul role="list" aria-labelledby="rail-points" class="-mx-1 min-h-0 flex-1 space-y-0.5 overflow-y-auto px-1 pb-3">
                        @foreach ($railGroups as $area => $group)
                            <li @class(['pt-2 first:pt-0' => $showAreas])>
                                @if ($showAreas)
                                    <p id="rail-area-{{ $loop->index }}" class="px-3 pb-1 text-xs text-muted">{{ $area }}</p>
                                @endif
                                <ul role="list" class="space-y-0.5" @if ($showAreas) aria-labelledby="rail-area-{{ $loop->index }}" @endif>
                                    @foreach ($group as $point)
                                        <li>
                                            <a href="{{ route('points.show', $point) }}" class="rail-link justify-between" @if ($pointCurrent($point)) aria-current="{{ $pointCurrent($point) }}" @endif>
                                                <span class="truncate">{{ $point->label }}</span>
                                                <span class="flex shrink-0 items-center gap-1 text-xs text-muted tabular-nums">
                                                    <x-icon name="image" class="size-3.5" />{{ $point->photo_records_count }}<span class="sr-only"> {{ Str::plural('photo', $point->photo_records_count) }}</span>
                                                </span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                        <li>
                            <a href="{{ route('points.create') }}" class="rail-link text-primary" @if (request()->routeIs('points.create')) aria-current="page" @endif>
                                <x-icon name="plus" class="size-[1.125rem]" /> New spot
                            </a>
                        </li>
                    </ul>
                </nav>

                <div class="border-t border-ink/10 px-2.5 pt-2.5 pb-4">
                    <x-account-menu placement="rail" />
                    <x-disclosure class="mt-3 px-3 text-muted" />
                </div>
            </aside>

            {{-- Phones and tablets: a floating glass bar on top; the page title joins it once the large title scrolls away --}}
            <header class="fixed inset-x-3 top-[calc(env(safe-area-inset-top)+0.5rem)] z-30 lg:hidden">
                <div class="glass relative mx-auto flex h-14 max-w-3xl items-center justify-between gap-2 rounded-full pr-1.5 pl-4">
                    <a href="{{ route('overview') }}" class="flex min-h-11 items-center gap-2.5 rounded-full pr-2 text-heading">
                        <span class="brand-tile size-8"><x-rise-mark class="size-6" /></span>
                        <span class="brand-word text-base leading-none font-bold tracking-[0.14em]">RISE</span>
                    </a>
                    <span class="compact-title" aria-hidden="true" data-compact-title></span>
                    <x-account-menu />
                </div>
            </header>
            <div class="edge-fade-top" aria-hidden="true"></div>
        @else
            <header class="fixed inset-x-3 top-[calc(env(safe-area-inset-top)+0.5rem)] z-30">
                <div class="glass glass-dense mx-auto flex h-14 max-w-6xl items-center justify-between gap-3 rounded-full pr-1.5 pl-4">
                    <a href="{{ route('login') }}" class="flex min-h-11 items-center gap-2.5 rounded-full pr-2 text-heading">
                        <span class="brand-tile size-8"><x-rise-mark class="size-6" /></span>
                        <span class="text-base leading-none font-bold tracking-[0.14em]">RISE</span>
                    </a>
                    <nav aria-label="Account" class="flex items-center gap-1">
                        <a href="{{ route('login') }}" class="btn btn-quiet">Sign in</a>
                        <a href="{{ route('register') }}" class="btn btn-primary">Register</a>
                    </nav>
                </div>
            </header>
        @endauth

        <div @class(['flex min-h-dvh flex-col', 'lg:pl-77' => auth()->check()])>
            <main id="main" @class([
                'w-full flex-1 px-4 pt-[var(--top-bar-space)] sm:px-6',
                'pb-[var(--tab-bar-space)] lg:px-12 lg:pt-12 lg:pb-16' => auth()->check(),
                'pb-12 lg:pt-28' => ! auth()->check(),
            ])>
                <div class="mx-auto max-w-6xl">
                    <div @class(['max-w-2xl' => $width === 'narrow'])>
                        {{-- Confirmations float on the backdrop as thin glass, above the page they belong to --}}
                        @if (session('success'))
                            <x-alert class="glass-thin">{{ session('success') }}</x-alert>
                        @endif

                        @if (session('info'))
                            <x-alert type="info" class="glass-thin">{{ session('info') }}</x-alert>
                        @endif

                        {{ $slot }}
                    </div>

                    {{-- On phones the disclosure closes the page; on desktop it sits in the sidebar --}}
                    <x-disclosure @class(['mt-14 border-t border-ink/10 pt-5 text-muted', 'lg:hidden' => auth()->check()]) />
                </div>
            </main>
        </div>

        @auth
            {{-- Bottom tab bar: Overview, the main Add photo action, and the list of monitoring points --}}
            <div class="edge-fade-bottom" aria-hidden="true"></div>
            <nav aria-label="Main" class="fixed inset-x-3 bottom-[calc(env(safe-area-inset-bottom)+0.625rem)] z-30 lg:hidden">
                <div class="glass mx-auto flex max-w-md items-center justify-between gap-1 rounded-full p-1.5">
                    <a href="{{ route('overview') }}" class="tab-link" @if (request()->routeIs('overview')) aria-current="page" @endif>
                        <x-icon name="home" class="size-[1.375rem]" /> Overview
                    </a>

                    <a href="{{ $addPhotoUrl }}" @if (request()->routeIs('records.create')) aria-current="page" @endif class="btn btn-primary min-h-12 flex-1 px-4">
                        <x-icon name="camera" /> {{ $isFollowUp ? 'Add follow-up' : 'Add photo' }}
                    </a>

                    <details data-menu>
                        <summary class="tab-link">
                            <x-icon name="list" class="size-[1.375rem]" /> Spots
                        </summary>
                        <div class="menu-panel fixed inset-x-3 bottom-[calc(env(safe-area-inset-bottom)+5.25rem)] mx-auto max-h-[60svh] max-w-md origin-bottom overflow-y-auto">
                            <p id="sheet-points" class="px-3 pt-2 pb-1 text-sm font-semibold text-muted">Your spots</p>
                            <ul role="list" aria-labelledby="sheet-points">
                                @foreach ($railGroups as $area => $group)
                                    <li>
                                        @if ($showAreas)
                                            <p id="sheet-area-{{ $loop->index }}" class="px-3 pt-2 pb-0.5 text-xs text-muted">{{ $area }}</p>
                                        @endif
                                        <ul role="list" @if ($showAreas) aria-labelledby="sheet-area-{{ $loop->index }}" @endif>
                                            @foreach ($group as $point)
                                                <li>
                                                    <a href="{{ route('points.show', $point) }}" class="menu-item justify-between" @if ($pointCurrent($point)) aria-current="{{ $pointCurrent($point) }}" @endif>
                                                        <span class="truncate">{{ $point->label }}</span>
                                                        <span class="meta shrink-0">{{ trans_choice('{0} No photos|{1} 1 photo|[2,*] :count photos', $point->photo_records_count) }}</span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @endforeach
                                <li class="mt-1 border-t border-ink/10 pt-1">
                                    <a href="{{ route('points.create') }}" class="menu-item text-primary">
                                        <x-icon name="plus" class="size-4" /> New spot
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </details>
                </div>
            </nav>
        @endauth
    @endif
</body>
</html>
