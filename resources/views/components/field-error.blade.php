@props(['for'])

{{-- Always rendered so the photo form script can fill it in; hidden while there is no error --}}
<p id="{{ $for }}-error" class="field-error" data-error-for="{{ $for }}" @unless ($errors->has($for)) hidden @endunless>
    <x-icon name="alert-circle" class="mt-0.5 size-4" />
    <span data-error-text>{{ $errors->first($for) }}</span>
</p>
