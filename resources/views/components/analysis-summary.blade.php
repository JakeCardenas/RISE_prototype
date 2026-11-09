@props(['analysis'])

@use('App\Models\PhotoAnalysis')

{{-- A one-line, text-and-icon summary of a photo's latest AI analysis --}}
@if ($analysis)
    @php
        // A written result (Gemini) is summed up by its finding; a region detector's by the areas it marked
        [$icon, $text, $tone] = match (true) {
            $analysis->isWaiting() => ['clock', 'AI analysis in progress', 'text-muted'],
            $analysis->hasWrittenResult() => match ($analysis->observation()?->finding) {
                'possible_feature' => ['scan', 'AI described a possible crack-like feature', 'text-mark-strong'],
                'no_clear_feature' => ['scan', 'AI found no clear crack-like feature', 'text-muted'],
                'uncertain' => ['scan', 'AI couldn’t tell from this photo', 'text-muted'],
                default => ['alert-circle', 'AI result can’t be shown', 'text-muted'],
            },
            $analysis->status === PhotoAnalysis::COMPLETED && $analysis->region_count > 0
                => ['scan', trans_choice('{1} AI marked 1 possible area|[2,*] AI marked :count possible areas', $analysis->region_count), 'text-mark-strong'],
            $analysis->status === PhotoAnalysis::COMPLETED => ['scan', 'AI marked no area', 'text-muted'],
            $analysis->status === PhotoAnalysis::NOT_CONFIGURED => ['info', 'AI analysis isn’t set up', 'text-muted'],
            default => ['alert-circle', 'AI analysis didn’t finish', 'text-danger'],
        };
    @endphp

    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-sm font-medium text-ink']) }}>
        <x-icon :name="$icon" :class="'size-4 '.$tone" /> {{ $text }}
    </span>
@endif
