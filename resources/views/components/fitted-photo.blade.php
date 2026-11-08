@props(['record', 'alt'])

{{-- A resident's whole photo at its own shape: as large as fits within --photo-max in height and the space in width. Never cropped. --}}
<x-record-photo :record="$record" :alt="$alt"
    :style="$record->viewWidth() && $record->viewHeight() ? '--photo-ratio: '.round($record->viewWidth() / $record->viewHeight(), 4) : null"
    {{ $attributes->merge(['class' => 'print mx-auto h-auto max-h-[var(--photo-max,30rem)] w-full max-w-[min(100%,calc(var(--photo-max,30rem)*var(--photo-ratio,1.3333)))] object-contain']) }} />
