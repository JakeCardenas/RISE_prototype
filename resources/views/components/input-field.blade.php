@props(['label', 'name', 'value' => '', 'type' => 'text', 'hint' => null])

@php
    // Hints and errors are announced with the field they belong to
    $describedBy = trim(($hint ? "$name-hint " : '').($errors->has($name) ? "$name-error" : ''));
@endphp

<div class="mb-5">
    <label for="{{ $name }}" class="field-label">{{ $label }}</label>

    @if ($hint)
        <p id="{{ $name }}-hint" class="field-hint">{{ $hint }}</p>
    @endif

    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="5"
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @error($name) aria-invalid="true" @enderror
            {{ $attributes->merge(['class' => 'field-control']) }}>{{ old($name, $value) }}</textarea>
    @else
        {{-- Password fields are never re-filled after a failed submit --}}
        <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}"
            @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @error($name) aria-invalid="true" @enderror
            {{ $attributes->merge(['class' => 'field-control']) }}>
    @endif

    @error($name)
        <p id="{{ $name }}-error" class="field-error">
            <x-icon name="alert-circle" class="mt-0.5 size-4" />
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
