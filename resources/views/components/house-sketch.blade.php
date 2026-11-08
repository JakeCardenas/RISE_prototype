{{--
    An original line drawing of a two-storey house front, with viewfinder corners marking one spot on a wall.
    Illustrative only: it is not anyone's home and shows no crack or detection. Lines use currentColor,
    the viewfinder uses --sketch-accent.
--}}
<svg viewBox="0 0 360 214" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
    aria-hidden="true" focusable="false" {{ $attributes->merge(['class' => 'block h-auto w-full']) }}>
    {{-- Ground and garden wall --}}
    <path d="M8 200h344" />
    <path d="M20 200v-22h34v22" opacity=".55" />

    {{-- Low gable roof with deep eaves --}}
    <path d="M46 96 160 44l114 52" />
    <path d="M46 96h228" />
    <path d="M58 96v6M262 96v6" opacity=".55" />

    {{-- Walls and the floor slab --}}
    <path d="M70 102v98M250 102v98" />
    <path d="M70 102h180" opacity=".55" />
    <path d="M70 150h180" />

    {{-- Upper floor: a window behind a slatted screen --}}
    <rect x="170" y="110" width="64" height="30" rx="1" />
    <path d="M178 110v30M186 110v30M194 110v30M202 110v30M210 110v30M218 110v30M226 110v30" opacity=".55" />

    {{-- Ground floor: door and a wide window --}}
    <rect x="86" y="160" width="26" height="40" rx="1" />
    <path d="M106 181h0" stroke-width="2.5" />
    <rect x="128" y="160" width="106" height="28" rx="1" />
    <path d="M181 160v28" opacity=".55" />

    {{-- Carport roof and post --}}
    <path d="M250 152h70M314 152v48" />

    {{-- The spot being watched, framed like the viewfinder in the app --}}
    <path d="M88 116v-8h8M126 108h8v8M134 136v8h-8M96 144h-8v-8" style="stroke: var(--sketch-accent, currentColor)" stroke-width="2" />
</svg>
