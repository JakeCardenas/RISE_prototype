@props(['type' => 'success'])

@php
    // A readable message and an icon, never colour alone; alerts are solid so they stay legible
    [$styles, $icon, $iconColor, $role] = [
        'success' => ['border-success-line bg-success-tint', 'check-circle', 'text-success', 'status'],
        'info'    => ['border-line bg-surface', 'info', 'text-primary', 'status'],
        'error'   => ['border-danger-line bg-danger-tint', 'alert-circle', 'text-danger', 'alert'],
    ][$type];
@endphp

<div role="{{ $role }}" {{ $attributes->merge(['class' => "surface mb-6 flex items-start gap-3 rounded-2xl border px-4 py-3.5 text-ink shadow-[0_1px_2px_rgb(6_16_10/0.12),0_12px_28px_-18px_rgb(6_16_10/0.6)] $styles"]) }}>
    <x-icon :name="$icon" :class="'mt-0.5 '.$iconColor" />
    <div class="min-w-0 break-words">{{ $slot }}</div>
</div>
