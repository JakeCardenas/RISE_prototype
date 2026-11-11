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
    @elseif ($type === 'password')
        {{--
            Password fields are never re-filled after a failed submit. The eye button shows what was typed; it stays
            hidden until password-toggle.js runs. Spelling and capital-letter help stay off, so a shown password isn't
            changed or sent to a spell checker.
        --}}
        <div class="relative">
            <input type="password" id="{{ $name }}" name="{{ $name }}"
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                @error($name) aria-invalid="true" @enderror
                {{ $attributes->merge(['class' => 'field-control pr-12', 'autocapitalize' => 'none', 'autocorrect' => 'off', 'spellcheck' => 'false']) }}>
            <button type="button" class="password-toggle" aria-label="Show password" aria-controls="{{ $name }}" aria-pressed="false"
                data-password-toggle hidden>
                <x-icon name="eye" class="password-toggle-show" />
                <x-icon name="eye-off" class="password-toggle-hide" />
            </button>
        </div>
    @else
        <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}" value="{{ old($name, $value) }}"
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
