@props(['record', 'alt'])

{{-- A private photo, streamed only to its owner through an authenticated route --}}
<img src="{{ route('records.photo', $record) }}" alt="{{ $alt }}"
    @if ($record->viewWidth() && $record->viewHeight()) width="{{ $record->viewWidth() }}" height="{{ $record->viewHeight() }}" @endif
    {{ $attributes->merge(['class' => 'block', 'loading' => 'lazy', 'decoding' => 'async']) }}>
