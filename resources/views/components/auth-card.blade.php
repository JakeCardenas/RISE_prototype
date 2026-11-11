@props(['heading', 'intro' => null])

{{--
    Sign in and register: one glass window in the RISE environment, split between a crisp view of home with RISE's
    promise and the form on a lightly translucent sage-white panel. On phones the photo becomes a short banner above
    the form. The photo comes first in reading order; on wide screens it sits to the right.
--}}
<div class="flex min-h-dvh flex-col items-center justify-center px-3 pt-[max(0.75rem,env(safe-area-inset-top))] pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-6 sm:py-10">
    <div class="glass glass-shell glass-enter grid w-full max-w-lg gap-2 rounded-[2rem] p-2 sm:p-2.5 lg:min-h-[42rem] lg:max-w-[68rem] lg:grid-cols-[minmax(0,27rem)_minmax(0,1fr)]">
        <section aria-label="About RISE" class="auth-photo flex min-h-40 flex-col justify-end p-5 sm:min-h-52 sm:p-7 lg:order-last lg:p-10">
            <picture>
                <source srcset="{{ asset('images/montana-misora-background.webp') }}" type="image/webp">
                <img src="{{ asset('images/montana-misora-background.png') }}" alt="" width="1578" height="996" decoding="async">
            </picture>
            <p class="max-w-md text-[1.375rem] leading-[1.15] font-bold tracking-[-0.025em] text-balance text-white sm:text-[1.75rem] lg:text-[2.375rem]">
                Track cracks over time, one photo at a time.
            </p>
            <p class="mt-2 text-[0.9375rem] text-white/90 sm:text-base lg:mt-3 lg:text-[1.0625rem]">A calm, private record of your home.</p>
        </section>

        {{-- The brand at the top, the form in the middle, the privacy promise at the foot --}}
        <div class="sheet sheet-auth flex flex-col p-6 sm:p-9">
            <a href="{{ route('login') }}" class="-m-1.5 flex w-fit items-center gap-3.5 rounded-[1.25rem] p-1.5 sm:gap-4">
                <span class="brand-tile brand-tile-hero size-12 sm:size-14 lg:size-16">
                    <x-rise-mark class="size-9 sm:size-10.5 lg:size-12" />
                </span>
                <span class="text-[1.375rem] leading-none font-extrabold tracking-[0.12em] text-heading sm:text-[1.625rem]">RISE</span>
            </a>

            <div class="my-auto pt-8 sm:pt-10">
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
            </div>

            <p class="mt-7 flex items-start gap-2 border-t border-line pt-5 text-sm text-muted">
                <x-icon name="lock" class="mt-0.5 size-4 shrink-0 text-primary" />
                Your photos are private to your account.
            </p>
        </div>
    </div>

    <x-university-lockup class="mt-6 flex px-4 pb-2 text-left" />
</div>
