@props(['title', 'icon' => 'info', 'code' => null])

{{--
    An error page in the sign-in window's glass: the RISE name, what happened in plain words, and a way on. It loads
    nothing from the database and doesn't depend on who is signed in, so it still shows when the server itself failed
    or before RISE knew who was signed in. The code at the foot helps whoever looks into the problem.
--}}
<x-layout :title="$title" bare>
    <div class="flex min-h-dvh flex-col items-center justify-center px-3 pt-[max(0.75rem,env(safe-area-inset-top))] pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-6 sm:py-10">
        <div class="glass glass-shell glass-enter w-full max-w-lg rounded-[2rem] p-2 sm:p-2.5">
            <section aria-labelledby="error-title" class="sheet sheet-auth p-6 sm:p-9">
                <a href="{{ route('overview') }}" class="-m-1.5 flex w-fit items-center gap-3 rounded-[1.25rem] p-1.5">
                    <span class="brand-tile size-11"><x-rise-mark class="size-8" /></span>
                    <span class="text-xl leading-none font-extrabold tracking-[0.12em] text-heading">RISE</span>
                </a>

                <span class="drop-card mt-10 size-14" aria-hidden="true"><x-icon :name="$icon" class="size-6" /></span>
                <h1 id="error-title" class="page-title mt-5 text-[1.875rem] text-balance sm:text-[2.125rem]">{{ $title }}</h1>
                <div class="mt-3 max-w-prose space-y-3 text-[1.0625rem] text-muted">{{ $slot }}</div>

                <div class="mt-8 flex flex-wrap gap-2">
                    {{ $actions ?? '' }}
                </div>

                @if ($code)
                    <p class="meta mt-8 border-t border-line pt-4">Error {{ $code }}</p>
                @endif
            </section>
        </div>
    </div>
</x-layout>
