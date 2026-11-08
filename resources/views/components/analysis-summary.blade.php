@props(['analysis'])

{{-- A one-line, text-and-icon summary of a photo's latest AI analysis --}}
@if ($analysis)
    @php
        [$icon, $text, $tone] = match (true) {
            $analysis->isWaiting() => ['clock', 'AI analysis in progress', 'text-muted'],
            $analysis->status === \App\Models\PhotoAnalysis::COMPLETED && $analysis->region_count > 0
                => ['scan', trans_choice('{1} AI marked 1 possible area|[2,*] AI marked :count possible areas', $analysis->region_count), 'text-mark-strong'],
            $analysis->status === \App\Models\PhotoAnalysis::COMPLETED => ['scan', 'AI marked no area', 'text-muted'],
            $analysis->status === \App\Models\PhotoAnalysis::NOT_CONFIGURED => ['info', 'AI analysis isn’t set up', 'text-muted'],
            default => ['alert-circle', 'AI analysis didn’t finish', 'text-danger'],
        };
    @endphp

    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-sm font-medium text-ink']) }}>
        <x-icon :name="$icon" :class="'size-4 '.$tone" /> {{ $text }}
    </span>
@endif
