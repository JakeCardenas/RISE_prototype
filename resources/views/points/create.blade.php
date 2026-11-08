<x-layout title="New spot">
    <x-page-header title="New spot" :back="route('overview')" back-label="Overview"
        description="One place you’ll photograph again and again, like a crack above a door. Use a separate spot for each place." />

    <div class="grid items-start gap-10 lg:grid-cols-[minmax(0,38rem)_minmax(0,1fr)]">
        <form method="POST" action="{{ route('points.store') }}" class="sheet p-5 sm:p-7" novalidate>
            @csrf

            @include('points.partials.fields', ['point' => null])

            <div class="mt-2 flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center">
                <button type="submit" class="btn btn-primary">Create spot</button>
                <a href="{{ route('overview') }}" class="btn btn-quiet">Cancel</a>
            </div>
        </form>

        <aside aria-labelledby="ideas-heading" class="sheet grid-paper p-6 sm:p-7">
            <x-house-sketch class="max-w-xs text-heading/70 [--sketch-accent:var(--color-primary)]" />
            <h2 id="ideas-heading" class="mt-6 section-title">Good spots to watch</h2>
            <ul class="mt-3 space-y-2">
                @foreach (['Bedroom wall beside the window', 'Kitchen ceiling corner', 'Living room wall by the door frame', 'Outside wall near the gate'] as $example)
                    <li class="flex items-start gap-2.5"><x-icon name="check-circle" class="mt-0.5 size-4 text-primary" /> {{ $example }}</li>
                @endforeach
            </ul>
            <p class="mt-5 text-sm text-muted">Next, you’ll add its first photo.</p>
        </aside>
    </div>
</x-layout>
