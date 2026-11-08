@use('App\Support\FollowUps')
@use('App\Support\HomeAreas')

@php
    // A saved area that isn't one of the preset rooms shows as "Other", with its name filled in.
    // A new point can arrive with an area already chosen, from that area's "Add a point" link on the overview.
    $savedArea = HomeAreas::name($point?->area ?? request()->query('area'));
    $chosenArea = old('area', match (true) {
        $savedArea === null => '',
        in_array($savedArea, HomeAreas::PRESETS, true) => $savedArea,
        default => 'other',
    });
    $otherArea = old('area_other', $chosenArea === 'other' ? $savedArea : '');
@endphp

<x-input-field label="Name of this spot" name="label" :value="$point?->label" maxlength="80" required
    placeholder="Bedroom wall beside the window"
    hint="A short name helps you find and photograph the same place each time. Describe the spot, not your address." />

{{-- Room or area: one tap on a preset, or "Other" to name it; the name field appears only for "Other" --}}
<fieldset class="area-picker mb-5" aria-describedby="area-hint">
    <legend class="field-label">Room or area (optional)</legend>
    <p id="area-hint" class="field-hint">Groups your points on the Overview. Only the room is saved, never an address or unit number.</p>

    <div class="flex flex-wrap gap-2">
        @foreach (['' => 'Not set', ...array_combine(HomeAreas::PRESETS, HomeAreas::PRESETS), 'other' => 'Other…'] as $value => $text)
            <label class="choice-chip">
                <input type="radio" name="area" value="{{ $value }}" class="sr-only" @checked($chosenArea === (string) $value)
                    @error('area') aria-invalid="true" aria-errormessage="area-error" @enderror>
                {{ $text }}
            </label>
        @endforeach
    </div>
    <x-field-error for="area" />

    <div class="area-other mt-4 max-w-sm" @if ($errors->has('area_other')) data-show @endif>
        <label for="area_other" class="field-label text-[0.9375rem]">Name this area</label>
        <input type="text" id="area_other" name="area_other" value="{{ $otherArea }}" maxlength="40" placeholder="Laundry area"
            class="field-control" @error('area_other') aria-invalid="true" aria-describedby="area_other-error" @enderror>
        <x-field-error for="area_other" />
    </div>
</fieldset>

<x-input-field label="Surface description (optional)" name="surface_description" type="textarea" :value="$point?->surface_description" maxlength="500"
    hint="What the surface is and what you can see, such as “Painted plaster, thin crack running up from the window corner.”" />

<x-input-field label="Next follow-up (optional)" name="follow_up_on" type="date" :value="$point?->follow_up_on?->toDateString()"
    :min="FollowUps::today()->toDateString()" class="max-w-xs"
    hint="The day you plan to photograph this spot again. It appears on your Overview as the day gets close. RISE doesn’t send emails or notifications." />
