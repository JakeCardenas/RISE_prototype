@props(['heading', 'intro' => null])

{{-- Sign in and register: one glass window in the RISE environment, with the form on a solid white panel inside it --}}
<div class="flex min-h-dvh flex-col items-center justify-center px-3 pt-[max(0.75rem,env(safe-area-inset-top))] pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-6 sm:py-10">
    <div class="glass grid w-full max-w-4xl gap-2 rounded-[2rem] p-2 sm:p-2.5 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)]">
        <section aria-label="About RISE" class="flex flex-col px-4 pt-4 pb-3 sm:px-8 sm:pt-7 lg:px-9 lg:py-9">
            <a href="{{ route('login') }}" class="-m-1 flex w-fit items-center gap-3 rounded-2xl p-1">
                <x-rise-mark class="size-10" />
                <span>
                    <span class="block text-[1.0625rem] leading-none font-bold tracking-[0.14em] text-heading">RISE</span>
                    <span class="mt-1.5 block text-xs leading-none font-medium text-muted">Home photo journal</span>
                </span>
            </a>

            <p class="mt-5 max-w-sm text-[1.5rem] leading-[1.15] font-bold tracking-[-0.03em] text-heading sm:mt-8 sm:text-[2rem] lg:mt-auto lg:text-[2.375rem]">
                Track cracks over time, one photo at a time.
            </p>

            <x-disclosure class="mt-6 hidden max-w-sm text-muted lg:block" />
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

    <x-disclosure class="mt-5 max-w-md px-4 text-center text-muted lg:hidden" />
</div>
