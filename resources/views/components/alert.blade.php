@props(['type' => 'success'])

@php
    // A readable message and an icon, never colour alone. Confirmations are a quiet capsule, announced politely;
    // errors are solid and prominent, so they stay legible and get noticed.
    [$icon, $role] = [
        'success' => ['check-circle', 'status'],
        'info'    => ['info', 'status'],
        'error'   => ['alert-circle', 'alert'],
    ][$type];
@endphp

@if ($type === 'error')
    <div role="alert" {{ $attributes->merge(['class' => 'surface mb-6 flex items-start gap-3 rounded-2xl border border-danger-line bg-danger-tint px-4 py-3.5 text-ink shadow-[0_1px_2px_rgb(6_16_10/0.12),0_12px_28px_-18px_rgb(6_16_10/0.6)]']) }}>
        <x-icon :name="$icon" class="mt-0.5 text-danger" />
        <div class="min-w-0 break-words">{{ $slot }}</div>
    </div>
@else
    <div role="{{ $role }}" {{ $attributes->merge(['class' => 'status-pill mb-5']) }}>
        <x-icon :name="$icon" class="mt-0.5 size-4 shrink-0 text-primary" />
        <span class="min-w-0 break-words">{{ $slot }}</span>
    </div>
@endif
