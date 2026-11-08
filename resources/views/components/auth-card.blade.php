@props(['heading', 'intro' => null])

{{-- Sign in and register: one clear glass window in the RISE environment, with the form on a solid white panel inside it --}}
<div class="flex min-h-dvh flex-col items-center justify-center px-3 pt-[max(0.75rem,env(safe-area-inset-top))] pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-6 sm:py-10">
    <div class="glass glass-enter grid w-full max-w-4xl gap-2 rounded-[2rem] p-2 sm:p-2.5 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)]">
        {{-- The brand and its promise together at the top; what RISE is and whose study it belongs to at the foot --}}
        <section aria-label="About RISE" class="flex flex-col px-4 pt-4 pb-3 sm:px-8 sm:pt-7 lg:px-9 lg:py-9">
            <a href="{{ route('login') }}" class="-m-1.5 flex w-fit items-center gap-3.5 rounded-[1.25rem] p-1.5 sm:gap-4 lg:gap-5 lg:rounded-[1.5rem]">
                <span class="brand-tile brand-tile-hero size-14 sm:size-16 lg:size-20">
                    <x-rise-mark class="size-10.5 sm:size-12 lg:size-15" />
                </span>
                <span>
                    <span class="block text-[1.625rem] leading-none font-extrabold tracking-[0.12em] text-heading sm:text-[1.875rem] lg:text-[2rem]">RISE</span>
                    <span class="mt-2 block text-sm leading-none font-medium text-muted lg:text-[0.9375rem]">Home photo journal</span>
                </span>
            </a>

            <p class="mt-6 max-w-sm text-[1.5rem] leading-[1.15] font-bold tracking-[-0.03em] text-balance text-heading sm:mt-8 sm:text-[2rem] lg:mt-11 lg:text-[2.375rem]">
                Track cracks over time, one photo at a time.
            </p>

            <div class="mt-auto hidden max-w-sm pt-12 lg:block">
                <x-disclosure class="text-muted" />
                <x-university-lockup class="mt-6 flex" />
            </div>
        </section>

        <div class="surface rounded-[1.5rem] bg-surface p-6 shadow-sheet sm:p-9">
            @if (session('success'))
                <x-alert>{{ session('success') }}</x-alert>
            @endif

            <h1 class="page-title text-[2rem] sm:text-[2.25rem] lg:text-[2.25rem]">{{ $heading }}</h1>

            @if ($intro)
                <p class="mt-2 text-[1.0625rem] text-muted">{{ $intro }}</p>
            @endif

            <div class="mt-7">
                {{ $slot }}
            </div>

            <p class="mt-7 flex items-start gap-2 border-t border-line pt-5 text-sm text-muted">
                <x-icon name="lock" class="mt-0.5 size-4 shrink-0 text-primary" />
                Your photos are private to your account.
            </p>
        </div>
    </div>

    <div class="mt-6 flex max-w-md flex-col items-center gap-5 px-4 pb-2 text-center lg:hidden">
        <x-disclosure class="text-muted" />
        <x-university-lockup class="flex text-left" />
    </div>
</div>
