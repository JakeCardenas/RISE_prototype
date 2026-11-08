@props(['heading', 'intro' => null])

@php
    $steps = [
        ['Name a spot', 'For example, “Bedroom wall beside the window.” No address or location is needed.'],
        ['Photograph it the same way', 'Same place, same distance, same light, so photos are easy to compare.'],
        ['Look back and compare', 'Optional AI analysis can mark possible crack-like areas for you to review.'],
    ];
@endphp

{{-- Log in and register: one large glass window in the RISE environment; the form sits on a solid white panel inside it --}}
<div class="flex min-h-dvh flex-col items-center justify-center px-3 pt-[max(0.75rem,env(safe-area-inset-top))] pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-6 sm:py-10">
    <div class="glass grid w-full max-w-5xl gap-2 rounded-[2rem] p-2 sm:p-2.5 lg:grid-cols-[minmax(0,1fr)_minmax(0,27rem)]">
        <section aria-labelledby="about-rise" class="flex flex-col px-4 pt-4 pb-5 sm:px-8 sm:pt-7 lg:px-9 lg:pt-9 lg:pb-8">
            <a href="{{ route('login') }}" class="-m-1 flex w-fit items-center gap-3 rounded-2xl p-1">
                <x-rise-mark class="size-10" />
                <span>
                    <span class="block text-[1.0625rem] leading-none font-bold tracking-[0.14em] text-heading">RISE</span>
                    <span class="mt-1.5 block text-xs leading-none font-medium text-muted">Home photo journal</span>
                </span>
            </a>

            <h2 id="about-rise" class="mt-6 max-w-lg text-[1.625rem] leading-[1.1] font-bold tracking-[-0.03em] text-heading sm:mt-10 sm:text-[2.125rem] lg:text-[2.5rem]">
                A dated photo record of the cracks you’re watching at home
            </h2>
            <p class="mt-3 hidden max-w-md text-[1.0625rem] text-muted sm:block">
                Name a spot once, photograph it the same way each time, and compare the photos side by side as the months pass.
            </p>

            <ol class="mt-auto hidden max-w-md space-y-4 pt-10 lg:block">
                @foreach ($steps as [$title, $text])
                    <li class="flex gap-3">
                        <span class="step-number size-6 text-xs">{{ $loop->iteration }}</span>
                        <div>
                            <p class="text-[0.9375rem] font-semibold">{{ $title }}</p>
                            <p class="mt-0.5 text-sm text-muted">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>

            <x-disclosure class="mt-8 hidden max-w-md text-muted lg:block" />
        </section>

        <div class="surface rounded-[1.5rem] bg-surface p-6 shadow-sheet sm:p-9">
            @if (session('success'))
                <x-alert>{{ session('success') }}</x-alert>
            @endif

            <h1 class="page-title text-[2rem] sm:text-[2.25rem] lg:text-[2.25rem]">{{ $heading }}</h1>

            @if ($intro)
                <p class="mt-2.5 text-[1.0625rem] text-muted">{{ $intro }}</p>
            @endif

            <div class="mt-8">
                {{ $slot }}
            </div>

            <p class="mt-8 flex items-start gap-2 border-t border-line pt-5 text-sm text-muted">
                <x-icon name="lock" class="mt-0.5 size-4 text-primary" />
                Your photos are private to your account.
            </p>
        </div>
    </div>

    {{-- On phones, how RISE works follows the window --}}
    <section aria-labelledby="how-heading" class="w-full max-w-5xl px-4 pt-10 pb-4 lg:hidden">
        <h2 id="how-heading" class="section-title">How RISE works</h2>
        <ol class="mt-4 space-y-4">
            @foreach ($steps as [$title, $text])
                <li class="flex gap-3">
                    <span class="step-number">{{ $loop->iteration }}</span>
                    <div>
                        <p class="font-semibold">{{ $title }}</p>
                        <p class="mt-0.5 text-sm text-muted">{{ $text }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
        <x-disclosure class="mt-8 border-t border-ink/10 pt-5 text-muted" />
    </section>
</div>
